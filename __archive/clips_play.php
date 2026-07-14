<?php
// Lightweight endpoint to record that a clip has been played once.
// Used by the BRB overlay rotation to track favorite play counts.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_libs/clips_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $clipId = $_POST['clip_id'] ?? '';
} else {
    $clipId = $_GET['clip_id'] ?? '';
}
$clipId = trim((string)$clipId);

if ($clipId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing clip_id']);
    exit;
}

// Increment play count; this is intentionally unauthenticated because the
// value is only used for relative ordering of favorites.
try {
    xog_clips_increment_play($clipId);
} catch (Throwable $e) {
    // swallow errors and still respond ok to avoid breaking overlay
}

echo json_encode(['ok' => true, 'clip_id' => $clipId]);

?>
