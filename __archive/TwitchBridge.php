<?php

class TwitchBridge {
    private PrivateLoader $privateLoader;
    private Tracker $tracker;
    private string $clientId;
    private string $clientSecret;
    private string $senderId;

    private string $authCallback;

    public function __construct(){
        $this->privateLoader = new PrivateLoader('twitch');
        $this->tracker = new Tracker(Tracker::COMMON_LOG);
        $this->clientId = $this->privateLoader->getDetail('clientId');
        $this->clientSecret = $this->privateLoader->getDetail('clientSecret');
        $this->senderId = $this->privateLoader->getDetail('senderId');
        $this->authCallback = $this->privateLoader->getDetail('authCallbackPath');
    }

    // AUTH: Public functions
    public function sendForCode(): void {
        $_SESSION['twitchAuthState'] = bin2hex(random_bytes(16));
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->authCallback,
            'response_type' => 'code',
            'scope' => 'user:write:chat chat:read',
            'state' => $_SESSION['twitchAuthState'],
        ]);

        $headerUrl = 'Location: ' . $this->privateLoader->getDetail('authUrl') . $params;
        header($headerUrl);
        exit;
    }

    public function getAccessToken(): ?string {
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            $this->tracker->error("No code provided in query parameters.", __FILE__);
            return null;
        }

        $state = $_GET['state'] ?? '';
        if (!$this->verifyTwitchState((string) $state)) {
            $this->tracker->error("Invalid Twitch auth state. Expected session state present: " . (empty($_SESSION['twitchAuthState']) ? 'no' : 'yes'), __FILE__);
            return null;
        }

        $this->tracker->info("Authorization code received: " . $code, __FILE__);
        $post = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->authCallback,
        ];

        $this->tracker->info("Exchanging authorization code for access token.", __FILE__);
        $accessToken = $this->exchangeCodeForToken($post);
        if ($accessToken === null) {
            $this->tracker->error("Failed to acquire Twitch access token.", __FILE__);
            return null;
        }

        $this->tracker->info("Twitch access token successfully acquired.", __FILE__);
        return $accessToken;
    }

    public function verifyTwitchState(string $state): bool {
        $expected = $_SESSION['twitchAuthState'] ?? '';
        return $expected !== '' && $state !== '' && hash_equals($expected, $state);
    }

    public function getUserData(string $accessToken): bool {
        $response = CurlController::get($this->privateLoader->getDetail('usersUrl'))
            ->headers([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
            ])
            ->timeout(4)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error("Failed to fetch user data from Twitch API. " . $response->summary(), __FILE__);
            return false;
        }

        $userData = $response->json();
        if (!is_array($userData) || empty($userData['data'][0])) {
            $this->tracker->error("Invalid user data response received.", __FILE__);
            return false;
        }

        $user = $userData['data'][0];
        $_SESSION['twitchLoginName'] = $user['login'] ?? '';
        $_SESSION['twitchDisplayName'] = $user['display_name'] ?? $_SESSION['twitchLoginName'];
        $_SESSION['twitchUserId'] = $user['id'] ?? '';
        $_SESSION['twitchToken'] = $accessToken;
        return true;
    }

    public function getAppAccessToken(): ?array {
        $response = CurlController::postForm($this->privateLoader->getDetail('tokenUrl'), [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ])
            ->userAgent('XogoriaTwitchBridge/1.0')
            ->timeout(6)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error('Failed to acquire Twitch app access token. ' . $response->summary(), __FILE__);
            return null;
        }

        $tokenData = $response->json();
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            $this->tracker->error('Invalid Twitch app token response received.', __FILE__);
            return null;
        }

        return [
            'access_token' => (string) $tokenData['access_token'],
            'expires_in' => (int) ($tokenData['expires_in'] ?? 3600),
        ];
    }

    public static function getPostLoginRedirect(): string {
        $target = '';
        if (!empty($_SESSION['redirectAfterTwitchLogin'])) {
            $target = (string) $_SESSION['redirectAfterTwitchLogin'];
            $target = forceRelativeUrl($target);

            unset($_SESSION['redirectAfterTwitchLogin']);
        } else {
            $target = '/interact.php';
        }
        return $target;
    }

    // --------------------------------------------------
    // AUTH: Helper functions
    // --------------------------------------------------

    private function exchangeCodeForToken(array $post): ?string {
        $response = CurlController::postForm($this->privateLoader->getDetail('tokenUrl'), $post)
            ->userAgent('XogoriaAuth/1.0')
            ->timeout(6)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error("exchangeCodeForToken failed. " . $response->summary(), __FILE__);
            return null;
        }

        $tokenData = $response->json();
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            $this->tracker->error("Invalid token response received.", __FILE__);
            return null;
        }

        return (string) $tokenData['access_token'];
    }

    // --------------------------------------------------
    // CHAT: Public functions
    // --------------------------------------------------

    public function sendChatMessage(string $message): bool {
        $accessToken = $this->getSessionToken();
        if ($accessToken === null) {
            $this->tracker->error('No Twitch token found in session.', __FILE__);
            return false;
        }

        if (!$this->validateUserToken($accessToken)) {
            return false;
        }

        $broadcasterId = $this->privateLoader->getDetail('broadcasterId');
        if ($broadcasterId === null || $broadcasterId === '') {
            $this->tracker->error('No Twitch broadcaster ID configured.', __FILE__);
            return false;
        }

        $messageUrl = rtrim($this->privateLoader->getDetail('messageUrl'), '?&');
        if ($messageUrl === '') {
            $this->tracker->error('No Twitch chat message URL configured.', __FILE__);
            return false;
        }

        $response = CurlController::postJson($messageUrl, [
            'broadcaster_id' => (string) $broadcasterId,
            'sender_id' => $this->senderId,
            'message' => $message,
        ])
            ->headers([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $accessToken,
            ])
            ->timeout(8)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error('Failed to send chat message to Twitch API. ' . $response->summary(), __FILE__);
            return false;
        }

        $responseData = $response->json();
        $messageResult = is_array($responseData) ? ($responseData['data'][0] ?? null) : null;
        if (!is_array($messageResult)) {
            $this->tracker->error('Twitch chat message response did not include send status. ' . $response->summary(), __FILE__);
            return false;
        }

        if (($messageResult['is_sent'] ?? false) !== true) {
            $dropReason = is_array($messageResult['drop_reason'] ?? null) ? $messageResult['drop_reason'] : [];
            $dropCode = (string) ($dropReason['code'] ?? 'unknown');
            $dropMessage = (string) ($dropReason['message'] ?? 'No drop reason returned.');
            $this->tracker->error('Twitch accepted chat message request but dropped the message. Code=' . $dropCode . ' Message=' . $dropMessage, __FILE__);
            return false;
        }

        $messageId = (string) ($messageResult['message_id'] ?? '');
        $this->tracker->info("Chat message sent successfully. Message ID: " . $messageId . ' Text: ' . substr($message, 0, 100), __FILE__);
        return true;
    }

    // --------------------------------------------------
    // CHAT: Helper functions
    // --------------------------------------------------

    private function validateUserToken(string $accessToken): bool {
        $response = CurlController::get($this->privateLoader->getDetail('validateUrl'))
            ->authorization('OAuth ' . $accessToken)
            ->timeout(6)
            ->send();

        if (!$response->isOk()) {
            $this->tracker->error('Failed to validate user token with Twitch API. ' . $response->summary(), __FILE__);
            return false;
        }

        $data = $response->json();
        $this->senderId = is_array($data) ? (string) ($data['user_id'] ?? '') : '';
        $scopes = is_array($data) ? (array) ($data['scopes'] ?? []) : [];
        $tokenClientId = is_array($data) ? (string) ($data['client_id'] ?? '') : '';

        if ($this->senderId === '') {
            $this->tracker->error('User token validation failed: no user_id in response.', __FILE__);
            return false;
        }

        if ($tokenClientId !== $this->clientId) {
            $this->tracker->info('Client ID mismatch. Reconfigured=' . $this->clientId . ' token=' . $tokenClientId, __FILE__);
            $this->clientId = $tokenClientId;
        }

        $canWriteChat = in_array('user:write:chat', $scopes, true);
        if (!$canWriteChat) {
            $this->tracker->error('User token validation failed: missing scope user:write:chat.', __FILE__);
            return false;
        }
        return true;
    }

    // --------------------------------------------------
    // CLIP DOWNLOAD: Public functions
    // --------------------------------------------------

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

    // --------------------------------------------------
    // CLIP DOWNLOAD: Helper functions
    // --------------------------------------------------

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

    private function getSessionToken(): ?string {
        $accessToken = $_SESSION['twitchToken'] ?? '';
        if ($accessToken === '') {
            return null;
        }
        return $accessToken;
    }
}
