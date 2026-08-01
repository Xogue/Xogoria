<?php

class MonsterName {
    private array $replaceMap = [
        "{GAMENAME}" => "getGameName",
        "{CUSTOMNAME}" => "getCustomName",
    ];

    private int $id;
    private string $gameName;
    private string $customName;

    // MAGIC FUNCTIONS
    public function __construct( array $monsterData ) {
        $this->id = $monsterData[ "id" ];
        $this->gameName = $monsterData[ "gameName" ];
        $this->customName = $monsterData[ "customName" ];
    }

    // PUBLIC FUNCTIONS
    public function getId( )        : int    { return $this->id; }
    public function getGameName( )  : string { return $this->gameName; }
    public function getCustomName( ): string { return $this->customName; }

    // OTHER
    public function assembleMonsterName( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
