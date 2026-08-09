<?php

class ConfigManager {
    private JsonHandler $jsonHandler;
    private ConfigStore $configStore;
    private PrivateStore $privateStore;

    // MAGIC FUNCTIONS
    public function __construct( private GameManager $gameManager ) {
        $this->jsonHandler = new JsonHandler( );
        $this->configStore = new ConfigStore( $this->jsonHandler );
        $this->privateStore = new PrivateStore( $this->jsonHandler );
        $this->gameManager->addGames( $this->configStore->getGameData( ) );
    }

    // PUBLIC FUNCTIONS
    public function getConfigStore( ) { return $this->configStore; }
    public function getPrivateStore( ) { return $this->privateStore; }
    // SPECIFIC GETTERS
    // PrivateStore
    public function getClientId( ) { return $this->privateStore->getClientId( ); }
    public function getClientSecret( ) { return $this->privateStore->getClientSecret( ); }
    public function getSenderId( ) { return $this->privateStore->getSenderId( ); }
    public function getDatabaseHost( ) { return $this->privateStore->databaseHost( ); }
    public function getDatabasePort( ) { return $this->privateStore->databasePort( ); }
    public function getDatabasePassword( ) { return $this->privateStore->databasePass( ); }
    public function getDatabaseName( ) { return $this->privateStore->databaseName( ); }
    public function getRedisHost( ) { return $this->privateStore->redisHost( ); }
    public function getRedisPort( ) { return $this->privateStore->redisPort( ); }
    public function getPanelBaseUrl( ) { return $this->privateStore->getPanelBaseUrl( ); }
    public function getServerPath( ) { return $this->privateStore->getServerPath( ); }
    public function getPanelApiKey( ) { return $this->privateStore->getPanelApiKey( ); }
    public function isDevMode( ) { return $this->privateStore->isDevMode( ); }
    // ConfigStore
    public function getAuthStart( ) { return $this->configStore->getAuthStart( ); }
    public function getAuthCallback( ) { return $this->configStore->getAuthCallback( ); }
    public function getAuthUrl( ) { return $this->configStore->getAuthUrl( ); }
    public function getTokenUrl( ) { return $this->configStore->getTokenUrl( ); }
    public function getValidateUrl( ) { return $this->configStore->getValidateUrl( ); }
    public function getMessageUrl( ) { return $this->configStore->getMessageUrl( ); }
    public function getUsersUrl( ) { return $this->configStore->getUsersUrl( ); }
    public function getClipsUrl( ) { return $this->configStore->getClipsUrl( ); }
    public function getClipDownloadUrl( ) { return $this->configStore->getClipDownloadUrl( ); }
    public function getCodeQuery( ) { return $this->configStore->getCodeQuery( ); }
    public function getGameData( ) { return $this->configStore->getGameData( ); }

    public function getGame( string $name ): ?Game { return $this->gameManager->getAllGames( )[ $name ] ?? null; }
    // VARIABLE GETTERS
    public function getSqlQuery( string $key ) { return $this->configStore->getSqlQuery( $key ); }

    public function getActiveGame( ) {
        $activeGame = $this->configStore->getActiveGame( );
        return match ( $activeGame ) {
            "minecraft" => $this->gameManager->getMinecraft( ),
            "hytale" => $this->gameManager->getHytale( ),
            default => throw new Exception( "Unknown game" ),
        };
    }

    public function getActiveProfile( ) {
        $game = $this->getActiveGame( );
        $profileName = $this->configStore->getActiveProfile( );
        return $game->getProfile( $profileName );
    }

    // SETTERS
    public function setActiveGameAndProfile( string $game, string $profile ) {
        $this->configStore->setActiveGameAndProfile( $game, $profile );
    }
}
