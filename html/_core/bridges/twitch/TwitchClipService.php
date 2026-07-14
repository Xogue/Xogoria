<?php

final class TwitchClipService {
    public function __construct(private ConfigManager $config, private Logger $logger) { }

    public function recent(int $limit = 50): array {
        $private = $this->config->getPrivateStore();
        $token = $private->getTwitchToken();
        $clientId = $private->getClientId();
        $broadcasterId = $private->getSenderId();
        if ($token === '' || $clientId === '' || $broadcasterId === '') {
            throw new RuntimeException('Twitch clip credentials are incomplete');
        }

        $url = $this->config->getClipsUrl() . '?' . http_build_query([
            'broadcaster_id' => $broadcasterId,
            'first' => max(1, min(100, $limit)),
        ]);
        $data = $this->getJson($url, $clientId, $token);
        return array_map(fn(array $clip): array => $this->normalize($clip), (array) ($data['data'] ?? []));
    }

    public function twitchUrl(string $clipId): string {
        return 'https://clips.twitch.tv/' . rawurlencode($clipId);
    }

    private function getJson(string $url, string $clientId, string $token): array {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Client-ID: {$clientId}\r\nAuthorization: Bearer {$token}\r\nAccept: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = $this->statusCode($http_response_header ?? []);
        if ($body === false || $status < 200 || $status >= 300) {
            $this->logger->error('Twitch clips request failed', ['status' => $status]);
            throw new RuntimeException("Twitch clips request failed with status {$status}");
        }
        try { return json_decode($body, true, flags: JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new RuntimeException('Twitch returned invalid clip data', previous: $error); }
    }

    private function statusCode(array $headers): int {
        return isset($headers[0]) && preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches) ? (int) $matches[1] : 0;
    }

    private function normalize(array $clip): array {
        return [
            'id' => (string) ($clip['id'] ?? ''),
            'url' => (string) ($clip['url'] ?? ''),
            'embedUrl' => (string) ($clip['embed_url'] ?? ''),
            'title' => (string) ($clip['title'] ?? ''),
            'creatorName' => (string) ($clip['creator_name'] ?? ''),
            'viewCount' => (int) ($clip['view_count'] ?? 0),
            'createdAt' => (string) ($clip['created_at'] ?? ''),
            'thumbnailUrl' => (string) ($clip['thumbnail_url'] ?? ''),
            'duration' => (float) ($clip['duration'] ?? 0),
        ];
    }
}
