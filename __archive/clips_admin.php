<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$action = strtolower((string)($_POST['action'] ?? ''));
$clipId = trim((string)($_POST['clip_id'] ?? ''));
// Most actions operate on a single clip and require clip_id, but
// batch operations like normalize_batch do not.
if ($action !== 'normalize_batch' && $clipId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing clip_id']);
    exit;
}

$db = xog_clips_db();
if (!$db) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database not available']);
    exit;
}

if ($action === 'update') {
    $customTitle = isset($_POST['custom_title']) ? trim((string)$_POST['custom_title']) : null;
    if ($customTitle !== null && $customTitle === '') {
        $customTitle = null;
    }
    $isFavorite = !empty($_POST['is_favorite']);
    $enabled = !isset($_POST['enabled']) || $_POST['enabled'] !== '0';
    $maxDuration = isset($_POST['max_duration']) ? (float)$_POST['max_duration'] : 0;
    if ($maxDuration < 0) {
        $maxDuration = 0;
    }
    $startOffset = isset($_POST['start_offset']) ? (float)$_POST['start_offset'] : 0;
    if ($startOffset < 0) {
        $startOffset = 0;
    }
    $playCount = isset($_POST['play_count']) ? (int)$_POST['play_count'] : null;
    if ($playCount !== null && $playCount < 0) {
        $playCount = 0;
    }

    $fields = [
        'custom_title' => $customTitle,
        'is_favorite'  => $isFavorite,
        'enabled'      => $enabled,
        'max_duration' => $maxDuration,
        'start_offset' => $startOffset,
    ];
    if ($playCount !== null) {
        $fields['play_count'] = $playCount;
    }

    $ok = xog_clips_upsert_meta($clipId, $fields);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to save clip metadata']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'approve') {
    // Mark a clip as approved/stored; this is the canonical set used
    // by the site/BRB players. We do not yet upload to external
    // storage here; that will be wired in later.
    $stmt = $db->prepare(
        'INSERT INTO stream_clips (clip_id, enabled, review_status, admin_seen, admin_seen_at)
         VALUES (?, 1, 1, 1, NOW())
         ON DUPLICATE KEY UPDATE
            enabled = 1,
            review_status = 1,
            admin_seen = 1,
            admin_seen_at = IFNULL(admin_seen_at, NOW())'
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to approve clip']);
        exit;
    }
    $stmt->bind_param('s', $clipId);
    $stmt->execute();
    $stmt->close();

    // Persist a snapshot of Twitch clip metadata at approval time so that
    // even if Helix stops returning this clip, we can still render full
    // cards (title, creator, views, thumbnail, etc.).
    try {
        $twFields = [
            'tw_title'         => isset($_POST['clip_title']) ? (string)$_POST['clip_title'] : null,
            'tw_creator'       => isset($_POST['clip_creator']) ? (string)$_POST['clip_creator'] : null,
            'tw_view_count'    => isset($_POST['clip_view_count']) ? (int)$_POST['clip_view_count'] : 0,
            'tw_created_at'    => isset($_POST['clip_created_at']) ? (string)$_POST['clip_created_at'] : null,
            'tw_thumbnail_url' => isset($_POST['clip_thumbnail']) ? (string)$_POST['clip_thumbnail'] : null,
            'tw_duration'      => isset($_POST['clip_duration']) ? (float)$_POST['clip_duration'] : null,
        ];
        xog_clips_store_twitch_snapshot($clipId, $twFields);
    } catch (Throwable $e) {
        // snapshot failure is non-fatal
    }

    // Best-effort: download the Twitch clip MP4 and upload to B2.
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clip_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $clipId) . '.mp4';
    $uploadedUrl = null;
    try {
        if (xog_twitch_download_clip_mp4($clipId, $tmpFile)) {
            $publicUrl = xog_b2_upload_clip_file($clipId, $tmpFile);
            if ($publicUrl) {
                xog_clips_set_local_file($clipId, $publicUrl, true);
                $uploadedUrl = $publicUrl;
            }
        }
    } catch (Throwable $e) {
        xog_b2_log('UPLOAD_EXCEPTION clip=' . $clipId . ' msg=' . $e->getMessage());
    } finally {
        if (is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    echo json_encode(['ok' => true, 'uploaded' => (bool)$uploadedUrl]);
    exit;
}

if ($action === 'normalize_batch') {
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 2;
    if ($limit < 1) { $limit = 1; }
    if ($limit > 5) { $limit = 5; }

    // Select candidates: stored clips that have a local file but have not yet
    // been normalized. Keep the query simple for shared hosting.
    $sql = "SELECT clip_id, local_url
            FROM stream_clips
            WHERE has_local_file = 1 AND (audio_normalized IS NULL OR audio_normalized = 0)
            LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        // Most likely a schema mismatch (e.g., audio_normalized column missing)
        // or lack of ALTER privileges. Log the mysqli error for debugging.
        $err = $db->error;
        xog_b2_log('NORM_SELECT_PREP_FAIL err=' . $err);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to select clips for normalization: ' . $err]);
        exit;
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $stmt->bind_result($cid, $url);
    $rows = [];
    while ($stmt->fetch()) {
        $rows[] = ['clip_id' => $cid, 'local_url' => $url];
    }
    $stmt->close();

    if (!$rows) {
        echo json_encode(['ok' => true, 'normalized_count' => 0]);
        exit;
    }

    $ffmpegPath = '/home/xogoyssg/ffmpeg';
    $normalizedCount = 0;

    foreach ($rows as $row) {
        $clipId = $row['clip_id'];
        $srcUrl = $row['local_url'] ?? '';
        if ($clipId === '' || $srcUrl === '') {
            continue;
        }

        $tmpIn  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'norm_in_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $clipId) . '.mp4';
        $tmpOut = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'norm_out_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $clipId) . '.mp4';

        try {
            // Download existing MP4 from B2.
            $in = @fopen($srcUrl, 'rb');
            if (!$in) {
                xog_b2_log('NORM_DL_FAIL clip=' . $clipId . ' url=' . $srcUrl);
                continue;
            }
            $out = @fopen($tmpIn, 'wb');
            if (!$out) {
                @fclose($in);
                xog_b2_log('NORM_TMP_FAIL clip=' . $clipId . ' path=' . $tmpIn);
                continue;
            }
            stream_copy_to_stream($in, $out);
            @fclose($in);
            @fclose($out);

            if (!is_file($tmpIn) || filesize($tmpIn) <= 0) {
                xog_b2_log('NORM_DL_EMPTY clip=' . $clipId);
                @unlink($tmpIn);
                continue;
            }

            // Run ffmpeg loudness normalization, copying video stream.
            $cmd = escapeshellcmd($ffmpegPath) . ' -y -i ' . escapeshellarg($tmpIn) .
                ' -af "loudnorm=I=-14:LRA=11:TP=-1.5" -c:v copy ' . escapeshellarg($tmpOut) . ' 2>&1';
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            if ($code !== 0 || !is_file($tmpOut) || filesize($tmpOut) <= 0) {
                xog_b2_log('NORM_FFMPEG_FAIL clip=' . $clipId . ' code=' . $code . ' cmd=' . $cmd);
                if ($output) {
                    xog_b2_log('NORM_FFMPEG_OUT clip=' . $clipId . ' out=' . implode("\n", $output));
                }
                @unlink($tmpIn);
                @unlink($tmpOut);
                continue;
            }

            // Upload normalized file back to B2 (overwriting existing key).
            $publicUrl = xog_b2_upload_clip_file($clipId, $tmpOut);
            if ($publicUrl) {
                xog_clips_set_local_file($clipId, $publicUrl, true);
                xog_clips_set_audio_normalized($clipId, true);
                $normalizedCount++;
            } else {
                xog_b2_log('NORM_B2_FAIL clip=' . $clipId);
            }
        } catch (Throwable $e) {
            xog_b2_log('NORM_EXCEPTION clip=' . $clipId . ' msg=' . $e->getMessage());
        } finally {
            if (is_file($tmpIn)) { @unlink($tmpIn); }
            if (is_file($tmpOut)) { @unlink($tmpOut); }
        }
    }

    echo json_encode(['ok' => true, 'normalized_count' => $normalizedCount]);
    exit;
}

if ($action === 'deny') {
    // Mark a clip as explicitly denied so it drops out of the
    // default review queue. It can still be viewed by switching
    // the admin filter to "denied".
    $stmt = $db->prepare(
        'INSERT INTO stream_clips (clip_id, enabled, review_status, admin_seen, admin_seen_at)
         VALUES (?, 0, 2, 1, NOW())
         ON DUPLICATE KEY UPDATE
            enabled = 0,
            review_status = 2,
            admin_seen = 1,
            admin_seen_at = IFNULL(admin_seen_at, NOW())'
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to deny clip']);
        exit;
    }
    $stmt->bind_param('s', $clipId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mark_seen') {
    // Mark clip as seen in admin (first time only keeps earliest timestamp)
    $stmt = $db->prepare(
        'INSERT INTO stream_clips (clip_id, admin_seen, admin_seen_at)
         VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE admin_seen = 1, admin_seen_at = IFNULL(admin_seen_at, NOW())'
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to mark clip as seen']);
        exit;
    }
    $stmt->bind_param('s', $clipId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    // Soft delete: mark as disabled
    $ok = xog_clips_upsert_meta($clipId, [
        'enabled' => false,
    ]);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to delete clip']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'reset_play_count') {
    // Reset play_count to zero while keeping other metadata intact.
    $stmt = $db->prepare('INSERT INTO stream_clips (clip_id, play_count) VALUES (?, 0)
                          ON DUPLICATE KEY UPDATE play_count = 0');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to prepare reset']);
        exit;
    }
    $stmt->bind_param('s', $clipId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);

?>
