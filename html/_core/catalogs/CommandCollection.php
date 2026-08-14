<?php

class CommandCollection {
    /** @var Command[] */
    private array $commands = [ ];

    // PUBLIC FUNCTIONS
    public function getAll( ): array { return $this->commands; }
    public function add( Command $command ): void { $this->commands[ ] = $command; }
}
