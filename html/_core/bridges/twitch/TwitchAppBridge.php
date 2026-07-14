<?php

class TwitchAppBridge {
    private TwitchAppContext $twitchAppContext;

    public function __construct(TwitchAppContext $twitchAppContext){
        $this->twitchAppContext = $twitchAppContext;
    }

    // AUTH: Public functions
    public function sendForCode(): void {
        $_SESSION['twitchAuthState'] = bin2hex(random_bytes(16));
        $params = $this->twitchAppContext->getCodeQuery();
        
        $params = http_build_query([
            'client_id' => $this->twitchAppContext->getClientId(),
            'redirect_uri' => $this->twitchAppContext->getAuthCallback(),
            'response_type' => 'code',
            'scope' => 'user:write:chat chat:read',
            'state' => $_SESSION['twitchAuthState'],
        ]);

        $headerUrl = 'Location: ' . $this->twitchAppContext->getAuthUrl() . '?' . $params;
        header($headerUrl);
        exit;
    }

    public function getAccessToken(): ?string {
        $codePass = $this->checkCode();
        $statePass = $this->checkState();

        if (!$codePass || !$statePass) {
            return null;
        }

        $post = [
            'client_id' => $this->twitchAppContext->getClientId(),
            'client_secret' => $this->twitchAppContext->getClientSecret(),
            'code' => $codePass,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->twitchAppContext->getAuthCallback(),
        ];
        
        $accessToken = $this->exchangeCodeForToken($post);
        if ($accessToken === null) {
            return null;
        }
        
        return $accessToken;
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

    // PRIVATE FUNCTIONS

    private function checkCode(): bool | string {
        $code = $_GET['code'] ?? '';
        if (!$code) { (new Logger(Logger::CHANNEL_WEB))->warning('Twitch callback is missing its code'); return false; }
        return $code;
    }

    private function checkState(): bool {
        $expected = $_SESSION['twitchAuthState'] ?? '';
        $state = $_GET['state'] ?? '';
        if (!$expected) { (new Logger(Logger::CHANNEL_WEB))->warning('Twitch auth session state is missing'); return false; }
        if (!$state) { (new Logger(Logger::CHANNEL_WEB))->warning('Twitch callback state is missing'); return false; }
        if (!hash_equals($expected, $state)) { (new Logger(Logger::CHANNEL_WEB))->warning('Twitch callback state is invalid'); return false; }
        return true;
    }

    private function exchangeCodeForToken(array $post): ?string {
        $tokenUrl = $this->twitchAppContext->getTokenUrl();
        $panelApiKey = $this->twitchAppContext->getPanelApiKey();
        
        $response = CurlController::postForm($tokenUrl, $panelApiKey, $post);
        $response->setUserAgent('XogoriaAuth/1.0');
        $response->setTimeout(6);
        $responseBody = $response->send();
        
        $tokenData = json_decode($responseBody, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return null;
        }

        return (string) $tokenData['access_token'];
    }
}
