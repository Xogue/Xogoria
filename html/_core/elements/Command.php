<?php

class Command {
    private array $replaceMap = [
        "{NAME}" => "getName",
        "{DESCRIPTION}" => "getDescription",
        "{CATEGORY}" => "getCategory",
        "{PERMS}" => "getPerms",
    ];

    private int $id;
    private string $name;
    private string $description;
    private string $category;
    private string $perms;
    private bool $enabled;

    // MAGIC FUNCTIONS
    public function __construct( array $commandData ) {
        $this->id = $commandData[ "id" ];
        $this->name = $commandData[ "name" ];
        $this->description = $commandData[ "description" ];
        $this->category = $commandData[ "category" ];
        $this->perms = $commandData[ "perms" ];
        $this->enabled = $commandData[ "enabled" ];
    }

    // PUBLIC FUNCTIONS
    public function getId( )         : int    { return $this->id; }
    public function getName( )       : string { return $this->name; }
    public function getDescription( ): string { return $this->description; }
    public function getCategory( )   : string { return $this->category; }
    public function getPerms( )      : string { return $this->perms; }
    public function isEnabled( )     : bool   { return $this->enabled; }

    // OTHER
    public function assembleCommand( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
