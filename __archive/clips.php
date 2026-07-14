<?php
// Twitch Clips API for Xogoria
// Returns recent or top-viewed clips for the configured channel and merges
// them with local metadata (favorites, play counts, overrides).

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_libs/clips_lib.php';
require_once __DIR__ . '/../_libs/config_bridge.php';

// Simple debug logger (server-side only). Writes to _assets/logs/clips_api.log.
$__CLIPS_API_LOG = __DIR__ . '/../_assets/logs/clips_api.log';
function xog_clips_api_log(string $msg): void {
    global $__CLIPS_API_LOG;
    try {
        $dir = dirname($__CLIPS_API_LOG);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        @file_put_contents($__CLIPS_API_LOG, date('c') . ' ' . $msg . "\n", FILE_APPEND);
    } catch (Throwable $e) {
        // swallow logging errors
    }
}

xog_clips_api_log('BEGIN clips.php ' . ($_SERVER['QUERY_STRING'] ?? ''));

try {
    // ----------------------------
    // Basic input handling
    // ----------------------------
    $mode = strtolower($_GET['mode'] ?? ($_GET['sort'] ?? 'recent'));
    if ($mode !== 'top' && $mode !== 'popular') {
        $mode = 'recent';
    }
    if ($mode === 'popular') {
        $mode = 'top';
    }

    $limit = $_GET['limit'] ?? ($_GET['count'] ?? 8);
    $limit = (int)$limit;
    if ($limit < 1) { $limit = 1; }
    if ($limit > 100) { $limit = 100; } // match viewer/admin expectations

    $range = strtolower($_GET['range'] ?? 'month'); // for "top" mode only
    $allowedRanges = ['day', 'week', 'month', 'all'];
    if (!in_array($range, $allowedRanges, true)) {
        $range = 'month';
    }

    $includeDisabled = !empty($_GET['include_disabled']);

    xog_clips_api_log("mode=$mode limit=$limit range=$range includeDisabled=" . ($includeDisabled ? '1' : '0'));

    // ----------------------------
    // Load Twitch configuration
    // ----------------------------
    $appCfg = cfg_twitch_app();
    $clientId = getenv('TWITCH_CLIENT_ID') ?: (string)($appCfg['client_id'] ?? '');
    $clientSecret = getenv('TWITCH_CLIENT_SECRET') ?: (string)($appCfg['client_secret'] ?? '');
    $appToken = getenv('TWITCH_APP_ACCESS_TOKEN') ?: (string)($appCfg['app_token'] ?? '');

    xog_clips_api_log('cfg clientId=' . ($clientId !== '' ? 'set' : 'missing') . ' token=' . ($appToken !== '' ? 'set' : 'missing'));

    if ($clientId === '' || $appToken === '') {
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => 'Twitch credentials not configured.',
        ]);
        xog_clips_api_log('ERROR missing credentials');
        return;
    }

    // Determine the channel login from system vars (bot config) if possible
    $botCfg = cfg_twitch_bot();
    $channel = $botCfg['channel'] ?? 'Xogue29';
    $channel = ltrim(trim($channel), '#');

    xog_clips_api_log('channel=' . $channel);

    // ----------------------------
    // Twitch helper functions
    // ----------------------------
    /**
     * Fetch a JSON payload from a Twitch Helix URL using the provided app token.
     *
     * @param string      $url
     * @param string      $clientId
     * @param string      $token
     * @param int|null    $statusCode
     * @return array|null
     */
    function xog_twitch_get_json(string $url, string $clientId, string $token, ?int &$statusCode = null): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "Client-ID: $clientId\r\nAuthorization: Bearer $token\r\n",
                'timeout'       => 8,
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        $statusCode = null;

        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $m)) {
                $statusCode = (int)$m[1];
            }
        }

        if ($resp === false) {
            return null;
        }

        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return null;
        }
        return $json;
    }

    /**
     * Request a fresh app access token using client credentials.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @return array|null
     */
    function xog_twitch_fetch_app_token(string $clientId, string $clientSecret): ?array
    {
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $tokenUrl = 'https://id.twitch.tv/oauth2/token';
        $post = http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'client_credentials',
        ], '', '&');

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $post,
                'timeout'       => 8,
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($tokenUrl, false, $ctx);
        if ($resp === false) {
            return null;
        }

        $json = json_decode($resp, true);
        if (!is_array($json) || empty($json['access_token'])) {
            return null;
        }
        return $json;
    }

    /**
     * Persist an updated app token back into system_vars.ini when possible.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $appToken
     */
    function xog_twitch_persist_app_token(string $clientId, string $clientSecret, string $appToken): void
    {
        try {
            cfg_system_vars_set([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'app_token' => $appToken,
            ]);
        } catch (Throwable $e) {
            // swallow write errors; token will still work for this request
        }
    }

    // ----------------------------
    // Resolve broadcaster_id from login
    // ----------------------------
    $status = null;
    $userUrl = 'https://api.twitch.tv/helix/users?login=' . rawurlencode($channel);
    xog_clips_api_log('CALL users ' . $userUrl);
    $userJson = xog_twitch_get_json($userUrl, $clientId, $appToken, $status);
    xog_clips_api_log('users status=' . (string)$status);

    // If the token is invalid/expired, try to refresh once using client_secret.
    if (($status === 401 || $status === 403) && $clientSecret !== '') {
        xog_clips_api_log('users unauthorized; attempting token refresh');
        $tok = xog_twitch_fetch_app_token($clientId, $clientSecret);
        if (is_array($tok) && !empty($tok['access_token'])) {
            $appToken = (string)$tok['access_token'];
            xog_twitch_persist_app_token($clientId, $clientSecret, $appToken);
            $status = null;
            $userJson = xog_twitch_get_json($userUrl, $clientId, $appToken, $status);
            xog_clips_api_log('users retry status=' . (string)$status);
        } else {
            xog_clips_api_log('token refresh failed');
        }
    }

    if (!$userJson || empty($userJson['data'][0]['id'])) {
        http_response_code(502);
        echo json_encode([
            'ok'     => false,
            'error'  => 'Unable to resolve Twitch broadcaster.',
            'status' => $status,
        ]);
        xog_clips_api_log('ERROR unable to resolve broadcaster');
        return;
    }

    $broadcasterId = (string)$userJson['data'][0]['id'];
    xog_clips_api_log('broadcasterId=' . $broadcasterId);

    // ----------------------------
    // Build clips request
    // ----------------------------
    $clipsUrl = 'https://api.twitch.tv/helix/clips?broadcaster_id=' . rawurlencode($broadcasterId) .
                '&first=' . $limit;

    // For "top" mode, optionally constrain the time window; for "recent" we let Twitch order by recency.
    if ($mode === 'top' && $range !== 'all') {
        $now = time();
        $seconds = 30 * 86400; // default ~month
        if ($range === 'day') {
            $seconds = 86400;
        } elseif ($range === 'week') {
            $seconds = 7 * 86400;
        } elseif ($range === 'month') {
            $seconds = 30 * 86400;
        }
        $started = gmdate('c', $now - $seconds);
        $ended   = gmdate('c', $now);
        $clipsUrl .= '&started_at=' . rawurlencode($started) . '&ended_at=' . rawurlencode($ended);
    }

    xog_clips_api_log('CALL clips ' . $clipsUrl);
    $status = null;
    $clipsJson = xog_twitch_get_json($clipsUrl, $clientId, $appToken, $status);
    xog_clips_api_log('clips status=' . (string)$status);

    if (!$clipsJson || !isset($clipsJson['data']) || !is_array($clipsJson['data'])) {
        http_response_code(502);
        echo json_encode([
            'ok'     => false,
            'error'  => 'Unable to fetch clips from Twitch.',
            'status' => $status,
        ]);
        xog_clips_api_log('ERROR unable to fetch clips');
        return;
    }

    $rawClips = $clipsJson['data'];
    xog_clips_api_log('clips count=' . count($rawClips));

    // For top mode, order by view_count descending ourselves to be explicit.
    if ($mode === 'top') {
        usort($rawClips, function ($a, $b) {
            $va = isset($a['view_count']) ? (int)$a['view_count'] : 0;
            $vb = isset($b['view_count']) ? (int)$b['view_count'] : 0;
            return $vb <=> $va;
        });
    }

    // ----------------------------
    // Merge with local metadata and normalize fields for the front-end
    // ----------------------------
    $clipIds = [];
    foreach ($rawClips as $row) {
        if (isset($row['id']) && $row['id'] !== '') {
            $clipIds[] = (string)$row['id'];
        }
    }

    $metaMap = xog_clips_meta_for_ids($clipIds);

    $clips = [];
    foreach ($rawClips as $clip) {
        if (count($clips) >= $limit) {
            break;
        }
        $id = isset($clip['id']) ? (string)$clip['id'] : '';
        if ($id === '') {
            continue;
        }
        $meta = $metaMap[$id] ?? null;
        $enabled = $meta ? !empty($meta['enabled']) : true;
        if (!$includeDisabled && !$enabled) {
            continue;
        }
        $title = isset($clip['title']) ? (string)$clip['title'] : '';
        $customTitle = $meta && isset($meta['custom_title']) ? (string)$meta['custom_title'] : null;

        $clips[] = [
            'id'             => $id,
            'url'            => isset($clip['url']) ? (string)$clip['url'] : '',
            'embed_url'      => isset($clip['embed_url']) ? (string)$clip['embed_url'] : '',
            'title'          => $title,
            'display_title'  => $customTitle !== null && $customTitle !== '' ? $customTitle : $title,
            'creator_name'   => isset($clip['creator_name']) ? (string)$clip['creator_name'] : '',
            'view_count'     => isset($clip['view_count']) ? (int)$clip['view_count'] : 0,
            'created_at'     => isset($clip['created_at']) ? (string)$clip['created_at'] : '',
            'thumbnail_url'  => isset($clip['thumbnail_url']) ? (string)$clip['thumbnail_url'] : '',
            'duration'       => isset($clip['duration']) ? (float)$clip['duration'] : null,
            'is_favorite'    => $meta ? !empty($meta['is_favorite']) : false,
            'play_count'     => $meta && isset($meta['play_count']) ? (int)$meta['play_count'] : 0,
            'max_duration'   => $meta && isset($meta['max_duration']) ? (float)$meta['max_duration'] : 0,
            'start_offset'   => $meta && isset($meta['start_offset']) ? (float)$meta['start_offset'] : 0,
            'enabled'        => $enabled,
            'admin_seen'     => $meta && isset($meta['admin_seen']) && $meta['admin_seen'],
            'review_status'  => $meta && isset($meta['review_status']) ? (int)$meta['review_status'] : 0,
            'has_local_file' => $meta && isset($meta['has_local_file']) ? (bool)$meta['has_local_file'] : false,
            'local_url'      => $meta && isset($meta['local_url']) ? (string)$meta['local_url'] : null,
            'audio_normalized' => $meta && isset($meta['audio_normalized']) ? (bool)$meta['audio_normalized'] : false,
        ];
    }

    // Ensure that all stored clips (review_status = 1) are represented,
    // even if Twitch's /clips listing no longer returns them (e.g., very
    // old or removed clips that still exist in our local library).
    $storedMeta = xog_clips_all_stored_meta();
    foreach ($storedMeta as $storedId => $meta) {
        if (in_array($storedId, $clipIds, true)) {
            continue; // already represented above
        }
        $enabled = !empty($meta['enabled']);
        if (!$includeDisabled && !$enabled) {
            continue;
        }
        $title = isset($meta['custom_title']) && $meta['custom_title'] !== ''
            ? (string)$meta['custom_title']
            : 'Stored clip';

        $clips[] = [
            'id'             => $storedId,
            'url'            => '',
            'embed_url'      => '',
            'title'          => $title,
            'display_title'  => $title,
            'creator_name'   => '',
            'view_count'     => 0,
            'created_at'     => '',
            'thumbnail_url'  => '',
            'duration'       => null,
            'is_favorite'    => !empty($meta['is_favorite']),
            'play_count'     => isset($meta['play_count']) ? (int)$meta['play_count'] : 0,
            'max_duration'   => isset($meta['max_duration']) ? (float)$meta['max_duration'] : 0,
            'start_offset'   => isset($meta['start_offset']) ? (float)$meta['start_offset'] : 0,
            'enabled'        => $enabled,
            'admin_seen'     => !empty($meta['admin_seen']),
            'review_status'  => isset($meta['review_status']) ? (int)$meta['review_status'] : 1,
            'has_local_file' => !empty($meta['has_local_file']),
            'local_url'      => isset($meta['local_url']) ? (string)$meta['local_url'] : null,
            'audio_normalized' => !empty($meta['audio_normalized']),
        ];
    }

    echo json_encode([
        'ok'             => true,
        'mode'           => $mode,
        'range'          => $range,
        'limit'          => $limit,
        'channel'        => $channel,
        'broadcaster_id' => $broadcasterId,
        'clips'          => $clips,
    ]);

    xog_clips_api_log('END ok clips=' . count($clips));
} catch (Throwable $e) {
    xog_clips_api_log('EXCEPTION ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Internal server error',
    ]);
}

?>
