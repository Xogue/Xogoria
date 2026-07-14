<?php

class DataStoreContext {
    private string $host;
    private string $port;
    private string $password;
    private string $database;

    public function __construct( private ConfigManager $configManager ) { 
        $this->host = $this->configManager->getRedisHost();
        $this->port = $this->configManager->getRedisPort();
    }

    public function getHost(): string { return $this->host; }
    public function getPort(): string { return $this->port; }
}