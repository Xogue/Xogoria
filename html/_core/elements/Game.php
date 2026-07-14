<?php

class Game {
    private array $profiles = [];
    private array $simpleTypes = [];
    private array $specialTypes = [];
    private string $name;

    public function __construct(string $name, array $data, private TemplateManager $templateManager) {
        $this->name = $name;
        $this->buildSimpleTypes($data['simpleTypes'] ?? []);
        $this->buildSpecialTypes($data['specialTypes'] ?? []);
        $this->buildProfiles($data['profiles'] ?? []);
    }

    // GETTERS
    public function getSimpleTypes(): array { return $this->simpleTypes ?? []; }
    public function getSpecialTypes(): array { return $this->specialTypes ?? []; }
    public function getProfiles(): array { return $this->profiles ?? []; }
    public function getName(): string { return $this->name; }

    public function getSimpleType(string $name): ?InteractType { return $this->simpleTypes[$name] ?? null; }
    public function getSpecialType(string $name): ?SpecialType { return $this->specialTypes[$name] ?? null; }
    public function getProfile(string $name): ?Profile { return $this->profiles[$name] ?? null; }

    // PRIVATE FUNCTIONS
    private function buildSimpleTypes(array $simpleTypes): void {
        foreach ($simpleTypes as $type => $data) {
            $this->simpleTypes[$type] = new InteractType($type, $data, $this->templateManager);
        }
    }

    private function buildSpecialTypes(array $specialTypes): void {
        foreach ($specialTypes as $name => $data) {
            $this->specialTypes[$name] = match ($name) {
                'batClaim' => new BatClaim($name, $data),
                'powerSpawn' => new PowerSpawn($name, $data),
                default => throw new Exception("Unknown special interaction type: $name"),
            };
        }
    }

    private function buildProfiles(array $profiles): void {
        foreach ($profiles as $name => $data) {
            $this->profiles[$name] = new Profile($name, $data);
        }
    }
}