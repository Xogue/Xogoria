<?php

class BatClaim implements SpecialType {
    private bool $enabled;
    private string $name;
    private string $label;
    private string $description;
    private int $cost;
    private int $cooldown;
    private array $commands;

    public function __construct(string $name, array $data) {
        $this->name = $name;
        $this->enabled = $data['enabled'] ?? false;
        $this->label = $data['label'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->cost = $data['cost'] ?? 0;
        $this->cooldown = $data['cooldown'] ?? 0;
        $this->commands = $data['commands'] ?? [];
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function getName(): string { return $this->name; }
    public function getLabel(): string { return $this->label; }
    public function getDescription(): string { return $this->description; }
    public function getCost(): int { return $this->cost; }
    public function getCooldown(): int { return $this->cooldown; }
    public function getCommands(): array { return $this->commands; }
}