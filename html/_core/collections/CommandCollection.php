<?php

class CommandCollection {
    /** @var Command[] */
    private array $commands = [];

    public function add(Command $command): void {
        $this->commands[] = $command;
    }

    public function getAll(): array {
        return $this->commands;
    }
}