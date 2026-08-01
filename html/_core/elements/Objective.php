<?php

class Objective {
    private array $replaceMap = [
        "{REQUIREMENT}" => "getRequirement",
    ];

    private int $id;
    private string $requirement;

    // MAGIC FUNCTIONS
    public function __construct( array $objectiveData ) {
        $this->id = $objectiveData[ "id" ];
        $this->requirement = $objectiveData[ "requirement" ];
    }

    // PUBLIC FUNCTIONS
    public function getId( )         : int    { return $this->id; }
    public function getRequirement( ): string { return $this->requirement; }

    // OTHER
    public function assembleObjective( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
