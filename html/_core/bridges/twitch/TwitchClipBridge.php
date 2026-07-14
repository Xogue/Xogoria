<?php

class TwitchClipBridge {
    private function getClipJson(string $url) {
        $response = CurlController::get($url)
            ->headers([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $this->privateLoader->getDetail('token'),
            ])
            ->timeout(10)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error('Failed to get clip data from Twitch API. ' . $response->summary(), __FILE__);
            return null;
        }

        $jsonData = $response->json();
        if (!is_array($jsonData)) {
            $this->tracker->error('Failed to decode clip JSON data from Twitch API.', __FILE__);
            return null;
        }
        return $jsonData;
    }

    private function getDownloadUrl(string $clipId): ?string {
        $queryString = http_build_query([
            'broadcaster_id' => $this->privateLoader->getDetail('broadcasterId'),
            'editor_id' => $this->privateLoader->getDetail('editorId'),
            'clip_id' => $clipId,
        ]);
        $url = $this->privateLoader->getDetail('clipDownloadUrl') . $queryString;
        $jsonData = $this->getClipJson($url);
        if ($jsonData === null || empty($jsonData['data'][0])) {
            $this->tracker->error('No download URL found for clip ID: ' . $clipId, __FILE__);
            return null;
        }
        $row = $jsonData['data'][0];
        $landscapeUrl = isset($row['landscape_download_url']) ? (string) $row['landscape_download_url'] : '';
        $portraitUrl = isset($row['portrait_download_url']) ? (string) $row['portrait_download_url'] : '';
        $downloadUrl = $landscapeUrl !== '' ? $landscapeUrl : $portraitUrl;
        if ($downloadUrl === '') {
            $this->tracker->error('No valid download URL found for clip ID: ' . $clipId, __FILE__);
            return null;
        }
        return $downloadUrl;
    }

    private function getClipVideoFromThumbnail(string $thumbnail): ?string {
        $parts = explode('-preview-', $thumbnail);
        if (count($parts) < 2) {
            $this->tracker->error('Invalid thumbnail URL format: ' . $thumbnail, __FILE__);
            return null;
        }

        return $parts[0] . '.mp4';
    }

    public function downloadClip(string $clipId, string $destPath): bool {
        $downloadUrl = $this->getDownloadUrl($clipId);
        if ($downloadUrl === null) {
            $this->tracker->info('No download URL available from official API. Trying to get it from thumbnail heuristic.', __FILE__);
            $downloadUrl = $this->getClipVideoFromThumbnail($clipId);
            if ($downloadUrl === null) {
                $this->tracker->error('Failed to derive download URL from thumbnail for clip ID: ' . $clipId, __FILE__);
                return false;
            }
        }

        try {
            $response = CurlController::download($downloadUrl, $destPath)->send();
        } catch (\RuntimeException $exception) {
            $this->tracker->error($exception->getMessage(), __FILE__);
            return false;
        }

        if (!$response->isOk()) {
            $this->tracker->error('Failed to download clip ID: ' . $clipId . '. ' . $response->summary(), __FILE__);
            return false;
        }

        $isFileValid = is_file($destPath) && filesize($destPath) > 0;
        if (!$isFileValid) {
            $this->tracker->error('Downloaded file is invalid or empty: ' . $destPath, __FILE__);
            return false;
        }

        return $isFileValid;
    }
}