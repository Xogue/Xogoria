<?php

class Interaction {
    private array $replaceMap = [
        '{KEY}' => 'getName',
        '{TYPE}' => 'getType',
        '{LABEL}' => 'getLabel',
        '{DESCRIPTION}' => 'getDescription',
        '{DURATION}' => 'getDuration',
        '{COST}' => 'getCost',
        '{CURRENT_COST}' => 'getCurrentCost',
        '{COOLDOWN}' => 'getCooldown',
        '{COMMANDS}' => 'getCommandString',
        '{COMMAND}' => 'getCommand'
    ];

    private string $name;
    private bool $enabled;
    private string $label;
    private string $description;
    private int $duration;
    private int $cost;
    private string $type;
    private int $cooldown;
    private array $commands = [];

    public function __construct( string $type, string $name, array $data, private string $panelTemplate) {
        $this->type        = $type;
        $this->name        = $name;
        $this->enabled     = $data['enabled'] ?? false;
        $this->label       = $data['label'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->duration    = $data['duration'] ?? 0;
        $this->cost        = $data['cost'] ?? 0;
        $this->cooldown    = $data['cooldown'] ?? 0;
        $this->commands    = $data['commands'] ?? [];
    }

    // GETTERS
    public function isEnabled(): bool {return $this->enabled;}
    public function getName(): string {return $this->name;}
    public function getLabel(): string {return $this->label;}
    public function getCost(): int {return $this->cost;}
    public function getDescription(): string {return $this->description;}
    public function getDuration(): int {return $this->duration;}
    public function getCooldown(): int {return $this->cooldown;}
    public function getCommand(): string {return "!" . $this->name;}
    public function getType(): string {return $this->type;}

    public function getCurrentCost(): int {
        return $this->cost * $this->duration;
    }

    public function getCommandString(): string {
        $commandString = '';
        foreach($this->commands as $command => $value) {
            $commandString .= "$command:$value ";
        }
        return rtrim($commandString);
    }

    public function getCommandArray(array $details): array {
        foreach ($details as $key => $value) {
            $this->commands = array_combine(
                array_map(function($command) use ($key, $value) {
                    return str_replace($key, $value, $command);
                }, array_keys($this->commands)),
                array_values($this->commands)
            );
        }
        $commandArray = [];
        foreach($this->commands as $command => $value) {
            for ($i = 0; $i < $value; $i++) {
                $commandArray[] = $command;
            }
        }
        return $commandArray;
    }

    // OTHER
    public function replaceValues(string $template): string {
        $replaced = $template;
        foreach ($this->replaceMap as $key => $method) {
            $replaced = str_replace($key, $this->$method(), $replaced);
        }
        return $replaced;
    }

    public function getPanelHtml(): string {
        return $this->replaceValues($this->panelTemplate);
    }
}
