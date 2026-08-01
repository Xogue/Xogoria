<?php

class WorkerContext {
    private Game $game;
    private Profile $profile;

    // MAGIC FUNCTIONS
    public function __construct( private ConfigManager $configManager ) {
        $this->game = $configManager->getActiveGame( );
        $this->profile = $configManager->getActiveProfile( );
    }

    // PUBLIC FUNCTIONS
    // GETTERS
    public function getGame( )    : Game    { return $this->game; }
    public function getProfile( ) : Profile { return $this->profile; }
    public function getServerId( ): string  { return $this->profile->getServerId( ); }

    // SETTERS

    // OTHER
    public function setActiveGameAndProfile( string $game, string $profile ): void {
        $selectedGame = $this->configManager->getGame( $game );
        if ( $selectedGame === null || $selectedGame->getProfile( $profile ) === null ) {
            throw new InvalidArgumentException( "Unknown game or profile" );
        }
        $this->configManager->setActiveGameAndProfile( $game, $profile );
        $this->game = $this->configManager->getActiveGame( );
        $this->profile = $this->configManager->getActiveProfile( );
    }
}
