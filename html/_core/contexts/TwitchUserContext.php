<?php

class TwitchUserContext {
    private string $validateUrl;
    private string $clientId;
    private string $clientSecret;
    private string $senderId;
    private string $messageUrl;
    private string $usersUrl;

    public function __construct(ConfigManager $configManager) {
        $this->clientId = $configManager->getClientId();
        $this->clientSecret = $configManager->getClientSecret();
        $this->validateUrl = $configManager->getValidateUrl();
        $this->senderId = $configManager->getSenderId();
        $this->messageUrl = $configManager->getMessageUrl();
        $this->usersUrl = $configManager->getUsersUrl();
    }

    public function getClientId(): string { return $this->clientId; }
    public function getClientSecret(): string { return $this->clientSecret; }
    public function getValidateUrl(): string { return $this->validateUrl; }
    public function getSenderId(): string { return $this->senderId; }
    public function getMessageUrl(): string { return $this->messageUrl; }
    public function getUsersUrl(): string { return $this->usersUrl; }
}
