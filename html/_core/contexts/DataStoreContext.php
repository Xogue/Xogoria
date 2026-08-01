<?php

class DataStoreContext {
    private string $host;
    private string $port;
    private string $password;
    private string $database;

    // MAGIC FUNCTIONS
    public function __construct( private ConfigManager $configManager ) {
        $this->host = $this->configManager->getRedisHost( );
        $this->port = $this->configManager->getRedisPort( );
    }

    // PUBLIC FUNCTIONS
    public function getHost( ): string { return $this->host; }
    public function getPort( ): string { return $this->port; }
}
