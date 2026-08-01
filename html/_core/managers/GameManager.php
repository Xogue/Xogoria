<?php

class GameManager {
    /** @var Game[] */
    private array $games = [ ];

    // MAGIC FUNCTIONS
    public function __construct( private TemplateManager $templateManager ) { }

    // PUBLIC FUNCTIONS
    public function getMinecraft( ): ?Game { return $this->games[ "minecraft" ] ?? null; }
    public function getHytale( )   : ?Game { return $this->games[ "hytale" ] ?? null; }
    public function getAllGames( ) : array { return $this->games; }

    public function addGames( array $gameData ) {
        foreach ( $gameData as $gameName => $data ) {
            $this->games[ $gameName ] = new Game( $gameName, $data, $this->templateManager );
        }
    }
}
