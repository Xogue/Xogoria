<?php

final class PrivateStore {
    public const PRIVATE_CONFIG_PATH = XOG_ROOT . "/../private/privateConfig.json";

    private static ?array $config = null;
    private array $data;
    private JsonHandler $jsonHandler;

    // MAGIC FUNCTIONS
    public function __construct( JsonHandler $jsonHandler ) {
        $this->jsonHandler = $jsonHandler;

        $this->data = $this->jsonHandler->safeLoad( self::PRIVATE_CONFIG_PATH );
        $this->checkError( $this->data, self::PRIVATE_CONFIG_PATH );
    }

    // PUBLIC FUNCTIONS
    // Database
    public function databaseHost( ): string { return $this->data[ "database" ][ "host" ] ?? "localhost"; }
    public function databaseUser( ): string { return $this->data[ "database" ][ "user" ] ?? "root"; }
    public function databasePass( ): string { return $this->data[ "database" ][ "pass" ] ?? ""; }
    public function databaseName( ): string { return $this->data[ "database" ][ "name" ] ?? "xogoria"; }
    public function databasePort( ): int    { return $this->data[ "database" ][ "port" ] ?? 3306; }
    // API
    public function getWebApiKey( )   : string { return $this->data[ "apiData" ][ "webApiKey" ] ?? ""; }
    public function getPanelBaseUrl( ): string { return $this->data[ "apiData" ][ "panelBaseUrl" ] ?? ""; }
    public function getServerPath( )  : string { return $this->data[ "apiData" ][ "serverPath" ] ?? ""; }
    public function getPanelApiKey( ) : string { return $this->data[ "apiData" ][ "panelApiKey" ] ?? ""; }
    // Twitch
    public function getSenderId( )      : string { return $this->data[ "twitch" ][ "broadcasterId" ] ?? ""; }
    public function getClientId( )      : string { return $this->data[ "twitch" ][ "clientId" ] ?? ""; }
    public function getClientSecret( )  : string { return $this->data[ "twitch" ][ "clientSecret" ] ?? ""; }
    public function getTwitchToken( )   : string { return $this->data[ "twitch" ][ "token" ] ?? ""; }
    public function getTwitchEditorId( ): string { return (string) ( $this->data[ "twitch" ][ "editorId" ] ?? $this->getSenderId( ) ); }
    // Backblaze
    public function backblazeKeyId( ): string { return $this->data[ "backblaze" ][ "key" ] ?? ""; }
    public function backblazeApplicationKey( ): string { return $this->data[ "backblaze" ][ "applicationKey" ] ?? ""; }
    public function backblazeBucket( ): string { return $this->data[ "backblaze" ][ "bucket" ] ?? ""; }
    public function backblazePublicBaseUrl( ): string { return $this->data[ "backblaze" ][ "publicBaseUrl" ] ?? ""; }
    public function backblazeAuthorizeUrl( ): string { return $this->data[ "backblaze" ][ "authorizeUrl" ] ?? ""; }
    public function backblazeListBucketUrl( ): string { return $this->data[ "backblaze" ][ "listBucketUrl" ] ?? ""; }
    public function backblazeUploadUrl( ): string { return $this->data[ "backblaze" ][ "uploadUrl" ] ?? ""; }
    // Redis
    public function redisHost( ): string { return $this->data[ "redis" ][ "host" ] ?? "localhost"; }
    public function redisPort( ): int    { return $this->data[ "redis" ][ "port" ] ?? 6379; }
    // Core
    public function isDevMode( ): bool { return $this->data[ "core" ][ "devMode" ] ?? false; }

    // PRIVATE FUNCTIONS
    private function checkError( array $data, string $path ) {
        // check errors eventually
 }
}
