<?php

class ObjectiveCollection {
    /** @var Objective[] */
    private array $objectives = [ ];

    // PUBLIC FUNCTIONS
    public function getAll( ): array { return $this->objectives; }
    public function add( Objective $objective ): void { $this->objectives[ ] = $objective; }
}
