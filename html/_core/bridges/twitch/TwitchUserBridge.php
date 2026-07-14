<?php

class TwitchUserBridge {
    private TwitchUserContext $twitchUserContext;
    private string $senderId;
    private string $clientId;

    public function __construct(TwitchUserContext $twitchUserContext) {
        $this->twitchUserContext = $twitchUserContext;
        $this->clientId = $twitchUserContext->getClientId();
    }

    private function validateUserToken(string $accessToken): bool {
        $request = new CurlController($this->twitchUserContext->getValidateUrl(), '');
        $request->setAuthorization('OAuth ' . $accessToken);
        $request->setTimeout(6);
        $responseBody = $request->send();
        $response = json_decode($responseBody, true);

        if (!is_array($response)) {
            return false;
        }

        $data = $response;
        $this->senderId = is_array($data) ? (string) ($data['user_id'] ?? '') : '';
        $scopes = is_array($data) ? (array) ($data['scopes'] ?? []) : [];
        $tokenClientId = is_array($data) ? (string) ($data['client_id'] ?? '') : '';

        if ($this->senderId === '') {
            return false;
        }

        if ($tokenClientId !== $this->clientId) {
            $this->clientId = $tokenClientId;
        }

        $canWriteChat = in_array('user:write:chat', $scopes, true);
        if (!$canWriteChat) {
            return false;
        }
        return true;
    }

    public function sendChatMessage(string $message): bool {
        $accessToken = $this->getSessionToken();
        if ($accessToken === null) {
            return false;
        }

        if (!$this->validateUserToken($accessToken)) {
            return false;
        }

        $broadcasterId = $this->twitchUserContext->getSenderId();
        if ($broadcasterId === null || $broadcasterId === '') {
            return false;
        }

        $messageUrl = rtrim($this->twitchUserContext->getMessageUrl(), '?&');
        if ($messageUrl === '') {
            return false;
        }

        $postData = json_encode([
            'broadcaster_id' => (string) $broadcasterId,
            'sender_id' => $this->senderId,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE );

        $request = new CurlController($messageUrl, '');
        $request->setToPost();
        $request->setAccept( 'application/json' );
        $request->setContentType( 'application/json' );
        $request->setPostData($postData);
        $request->setClientId($this->clientId);
        $request->setAuthorization('Bearer ' . $accessToken);
        $request->setTimeout(8);
        $request->setCurlHeaders();
        $responseBody = $request->send();
        $tokenData = json_decode($responseBody, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return false;
        }

        $messageResult = is_array($tokenData) ? ($tokenData['data'][0] ?? null) : null;
        if (!is_array($messageResult)) {
            return false;
        }

        if (($messageResult['is_sent'] ?? false) !== true) {
            return false;
        }

        $messageId = (string) ($messageResult['message_id'] ?? '');
        return true;
    }

    private function getSessionToken(): ?string {
        $accessToken = $_SESSION['twitchToken'] ?? '';
        if ($accessToken === '') {
            return null;
        }
        return $accessToken;
    }

    public function getUserData(string $accessToken): bool {
        $request = new CurlController($this->twitchUserContext->getUsersUrl(), '');
        $request->setClientId($this->clientId);
        $request->setAuthorization('Bearer ' . $accessToken);
        $request->setTimeout(4);
        $request->setCurlHeaders();
        $responseBody = $request->send();
        $userData = json_decode($responseBody, true);
        
        if (!is_array($userData) || empty($userData['data'][0])) {
            return false;
        }

        $user = $userData['data'][0];
        $_SESSION['twitchLoginName'] = $user['login'] ?? '';
        $_SESSION['twitchDisplayName'] = $user['display_name'] ?? $_SESSION['twitchLoginName'];
        $_SESSION['twitchUserId'] = $user['id'] ?? '';
        $_SESSION['twitchToken'] = $accessToken;
        return true;
    }
}