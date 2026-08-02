<?php

class Mob {
    private bool $enabled;
    private string $name;
    private string $label;
    private int $cost;
    private int $cooldown;
    private string $command;

    // MAGIC FUNCTIONS
    public function __construct( string $name, array $data ) {
        $this->name = $name;
        $this->enabled = $data[ "enabled" ] ?? false;
        $this->label = $data[ "label" ] ?? "";
        $this->cost = $data[ "cost" ] ?? 0;
        $this->cooldown = $data[ "cooldown" ] ?? 0;
        $this->command = (string) ( $data[ "command" ] ?? "" );
    }

    // PUBLIC FUNCTIONS
    public function isEnabled( )  : bool   { return $this->enabled; }
    public function getName( )    : string { return $this->name; }
    public function getLabel( )   : string { return $this->label; }
    public function getCost( )    : int    { return $this->cost; }
    public function getCooldown( ): int    { return $this->cooldown; }
    public function getCommand( )  : string { return $this->command; }
}
