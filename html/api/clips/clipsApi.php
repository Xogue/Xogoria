<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$services = new ServiceFactory();
$mode = strtolower((string) ($_GET['mode'] ?? 'recent'));
$range = strtolower((string) ($_GET['range'] ?? 'month'));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

try {
    $stored = $services->clipManager()->getAllReviewedClipInfo();
    try { $twitchClips = $services->twitchClipService()->recent($limit); }
    catch (Throwable $error) { $twitchClips = []; $services->logger(Logger::CHANNEL_API)->warning('Using stored clips because Twitch refresh failed', ['error' => $error->getMessage()]); }

    $merged = $stored;
    foreach ($twitchClips as $clip) {
        $id = $clip['id'];
        if (isset($stored[$id])) {
            $merged[$id] = array_merge($stored[$id], $clip);
        }
    }
    $clips = array_values(array_filter($merged, static fn(array $clip): bool => $clip['reviewStatus'] === 1 && !empty($clip['enabled'])));
    if ($mode === 'top') { usort($clips, static fn(array $a, array $b): int => ($b['viewCount'] ?? 0) <=> ($a['viewCount'] ?? 0)); }
    else { usort($clips, static fn(array $a, array $b): int => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''))); }
    $clips = array_slice($clips, 0, $limit);

    $payload = array_map(static fn(array $clip): array => [
        'id' => $clip['id'],
        'url' => $clip['url'] ?? ('https://clips.twitch.tv/' . $clip['id']),
        'title' => $clip['title'] ?? '',
        'display_title' => $clip['customTitle'] ?: ($clip['title'] ?? 'Stored clip'),
        'creator_name' => $clip['creatorName'] ?? '',
        'view_count' => (int) ($clip['viewCount'] ?? 0),
        'created_at' => $clip['createdAt'] ?? '',
        'thumbnail_url' => $clip['thumbnailUrl'] ?? '',
        'duration' => (float) ($clip['duration'] ?? 0),
        'is_favorite' => !empty($clip['favorite']),
        'play_count' => (int) ($clip['playCount'] ?? 0),
        'max_duration' => (float) ($clip['maxDuration'] ?? 0),
        'start_offset' => (float) ($clip['startOffset'] ?? 0),
        'enabled' => !isset($clip['enabled']) || !empty($clip['enabled']),
        'has_local_file' => !empty($clip['localUrl']),
        'local_url' => $clip['localUrl'] ?? null,
    ], $clips);

    ApiController::sendJson(['success' => true, 'ok' => true, 'mode' => $mode, 'range' => $range, 'clips' => $payload]);
} catch (Throwable $error) {
    $services->logger(Logger::CHANNEL_API)->exception($error, ['endpoint' => 'clipsApi']);
    ApiController::error('Clips could not be loaded.', 500);
}
