<?php

class Profile {
    private string $name;
    private string $label;
    private string $serverId;
    private string $tagLine;
    private array $allowedSimpleTypes = [ ];
    private array $allowedSpecialTypes = [ ];

    // MAGIC FUNCTIONS
    public function __construct( string $name, array $data ) {
        $columnNames = [
            "label",
            "serverId",
            "tagLine",
            "allowedSimpleTypes",
            "allowedSpecialTypes",
        ];
        $validated = $this->checkData( $data, $columnNames );
        if ( !$validated ) {
            throw new InvalidArgumentException( "Invalid profile data" );
        }

        $this->name = $name;
        $this->label = $data[ "label" ];
        $this->serverId = $data[ "serverId" ];
        $this->tagLine = $data[ "tagLine" ];
        $this->allowedSimpleTypes = $data[ "allowedSimpleTypes" ];
        $this->allowedSpecialTypes = $data[ "allowedSpecialTypes" ];
    }

    // PUBLIC FUNCTIONS
    public function getName( )               : string { return $this->name; }
    public function getLabel( )              : string { return $this->label; }
    public function getServerId( )           : string { return $this->serverId; }
    public function getTagLine( )            : string { return $this->tagLine; }
    public function getAllowedSimpleTypes( ) : array  { return $this->allowedSimpleTypes; }
    public function getAllowedSpecialTypes( ): array  { return $this->allowedSpecialTypes; }

    public function allowsSimpleInteraction( string $type, string $interaction ): bool {
        $allowed = $this->allowedSimpleTypes[ $type ] ?? null;
        return is_array( $allowed ) && in_array( $interaction, $allowed, true );
    }

    // PRIVATE FUNCTIONS
    private function checkData( array $data, array $columnNames ): bool {
        foreach ( $columnNames as $columnName ) {
            if ( !array_key_exists( $columnName, $data ) ) {
                return false;
            }
        }
        return true;
    }
}

?>
