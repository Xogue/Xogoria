<?php

class StreamStatus {
    private const TTL = 60;
    private Logger $logger;
    private PrivateLoader $privateLoader;
    private DataStore $dataStore;

    private string $clientId;
    private string $token;
    private string $streamChannel;

    private string $streamUrl;

    private bool $isLive = false;

    public function __construct() {
        $this->logger = new Logger();
        $this->privateLoader = new PrivateLoader('twitch');
        $this->dataStore = new DataStore();

        $this->clientId = $this->privateLoader->getDetail('clientId');
        $this->token = $this->privateLoader->getDetail('token');
        $this->streamChannel = $this->privateLoader->getDetail('botChannel');
        $this->streamUrl = $this->privateLoader->getDetail('streamUrl') . rawurlencode($this->streamChannel);
    }

    public function isLive(): bool {
        if ($this->shouldCheckStatus()) {
            $this->checkStatus();
        }
        
        return $this->isLive;
    }

    public function getStatus(): string {
        if (!$this->shouldCheckStatus()) {
            return $this->isLive ? 'live' : 'offline';
        }

        if ($this->dataStore->getKey('streamStatus:checking') !== '') {
            return 'checking';
        }

        $this->dataStore->setExpiringKey('streamStatus:checking', 15, 'checking');
        $this->checkStatus();

        return $this->isLive ? 'live' : 'offline';
    }

    private function wentLive(): void {
        $this->isLive = true;
        $this->dataStore->setExpiringKey('lastChecked', self::TTL, 'live');
    }
    
    private function wentOffline(): void {
        $this->isLive = false;
        $this->dataStore->setExpiringKey('lastChecked', self::TTL, 'offline');
    }

    private function shouldCheckStatus(): bool {
        $lastChecked = $this->dataStore->getKey('lastChecked');
        if ($lastChecked === '') {
            return true;
        }

        $this->isLive = $lastChecked === 'live';
        return false;
    }

    private function checkStatus(): bool {
        $tokenKey = 'twitch:streamStatus:appToken';
        $cachedToken = $this->dataStore->getKey($tokenKey);
        $accessToken = $cachedToken !== '' ? $cachedToken : $this->token;

        $fetchStatus = function (string $token): CurlResponse {
            return CurlController::get($this->streamUrl)
                ->headers([
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->timeout(6)
                ->send();
        };

        $response = $fetchStatus($accessToken);

        if ($response->statusCode() === 401) {
            $tokenData = (new TwitchBridge())->getAppAccessToken();
            if ($tokenData === null) {
                $this->logger->error('Failed to fetch live status', ['channel' => $this->streamChannel, 'response' => $response->summary()]);
                return false;
            }

            $this->token = (string) $tokenData['access_token'];
            $expiresIn = max(60, ((int) ($tokenData['expires_in'] ?? 3600)) - 300);
            $this->dataStore->setExpiringKey($tokenKey, $expiresIn, $this->token);
            $response = $fetchStatus($this->token);
        }

        if (!$response->isOk()) {
            $this->logger->error('Failed to fetch live status', ['channel' => $this->streamChannel, 'response' => $response->summary()]);
            return false;
        }

        $data = $response->json();
        if ($data === null) {
            $this->logger->error('Failed to decode live status response', ['channel' => $this->streamChannel]);
            return false;
        }

        if (isset($data['data']) && count($data['data']) > 0) {
            if ($data['data'][0]['type'] === 'live') {
                $this->wentLive();
                return true;
            } else {
                $this->wentOffline();
                return false;
            }
        }

        $this->wentOffline();
        return false;
    }
}
