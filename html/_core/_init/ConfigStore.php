<?php

class ConfigStore {
    private const CORE_CONFIG_PATH = XOG_ROOT . "/_core/_init/config/core.json";
    private const SQL_CONFIG_PATH = XOG_ROOT . "/_core/_init/config/sql.json";
    private const TWITCH_CONFIG_PATH = XOG_ROOT . "/_core/_init/config/twitch.json";
    private const GAME_CONFIG_PATH = XOG_ROOT . "/_core/_init/config/gameConfigs";

    private array $coreData;
    private array $sqlData;
    private array $twitchData;
    private array $gameData;

    private JsonHandler $jsonHandler;

    // MAGIC FUNCTIONS
    public function __construct( JsonHandler $jsonHandler ) {
        $this->jsonHandler = $jsonHandler;

        $this->coreData = $this->jsonHandler->safeLoad( self::CORE_CONFIG_PATH );
        if ( empty( $this->coreData ) ) {
            throw new RuntimeException( "Failed to load core config from " . self::CORE_CONFIG_PATH );
        }

        $this->sqlData = $this->jsonHandler->safeLoad( self::SQL_CONFIG_PATH );
        if ( empty( $this->sqlData ) ) {
            throw new RuntimeException( "Failed to load SQL config from " . self::SQL_CONFIG_PATH );
        }

        $this->twitchData = $this->jsonHandler->safeLoad( self::TWITCH_CONFIG_PATH );
        if ( empty( $this->twitchData ) ) {
            throw new RuntimeException(
                "Failed to load Twitch config from " . self::TWITCH_CONFIG_PATH,
            );
        }

        foreach ( new FileMap( self::GAME_CONFIG_PATH ) as $game => $gamePath ) {
            $gameName = explode( ".", $game )[ 0 ];
            $data = $this->jsonHandler->safeLoad( $gamePath );
            if ( !empty( $data ) ) {
                $this->gameData[ $gameName ] = $data;
            }
        }
    }

    // PUBLIC FUNCTIONS
    // SPECIFIC GETTERS
    public function getActiveGame( )   : string { return $this->coreData[ "activeGame" ] ?? ""; }
    public function getActiveProfile( ): string { return $this->coreData[ "activeProfile" ] ?? ""; }
    public function getAuthStart( )    : string { return $this->twitchData[ "authStartPath" ] ?? ""; }
    public function getAuthUrl( )      : string { return $this->twitchData[ "authUrl" ] ?? ""; }
    public function getTokenUrl( )     : string { return $this->twitchData[ "tokenUrl" ] ?? ""; }
    public function getValidateUrl( )  : string { return $this->twitchData[ "validateUrl" ] ?? ""; }
    public function getMessageUrl( )   : string { return $this->twitchData[ "messageUrl" ] ?? ""; }
    public function getUsersUrl( )     : string { return $this->twitchData[ "usersUrl" ] ?? ""; }
    public function getClipsUrl( )     : string { return $this->twitchData[ "clipsUrl" ] ?? ""; }
    public function getClipDownloadUrl( ): string { return $this->twitchData[ "clipDownloadUrl" ] ?? ""; }
    public function getCodeQuery( )    : array  { return $this->twitchData[ "codeQuery" ] ?? [ ]; }
    public function getGameData( )     : array  { return $this->gameData ?? [ ]; }
    public function getRestrictedWords( ): array {
        $words = $this->coreData[ "restrictedWords" ] ?? [ ];
        if ( !is_array( $words ) ) {
            return [ ];
        }

        return array_values( array_filter(
            $words,
            fn( mixed $word ): bool => is_string( $word ) && trim( $word ) !== "",
        ) );
    }

    public function getSqlQuery( string $key ): string { return $this->sqlData[ $key ] ?? ""; }

    public function getAuthCallback( ): string {
        return DEV_MODE
            ? $this->twitchData[ "authCallbackDevPath" ] ?? ""
            : $this->twitchData[ "authCallbackProdPath" ] ?? "";
    }

    // SETTERS
    public function setActiveGameAndProfile( string $game, string $profile ): void {
        $this->coreData[ "activeGame" ] = $game;
        $this->coreData[ "activeProfile" ] = $profile;
        $this->writeCore( );
    }

    // PRIVATE FUNCTIONS
    private function writeCore( ): void { $this->jsonHandler->safeWrite( self::CORE_CONFIG_PATH, $this->coreData ); }
}
