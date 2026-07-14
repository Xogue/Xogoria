<?php

class PowerSpawn implements SpecialType {
    private int $cooldownMin;
    private int $cooldownMax;
    private array $mobs = [];

    public function __construct(string $name, array $data) {
        $this->cooldownMin = $data['cooldownMin'] ?? 0;
        $this->cooldownMax = $data['cooldownMax'] ?? 0;
        $this->buildMobs($data['mobs'] ?? []);
    }

    public function getCooldownMin(): int { return $this->cooldownMin; }
    public function getCooldownMax(): int { return $this->cooldownMax; }
    public function getMobs(): array { return $this->mobs; }

    // PRIVATE FUNCTIONS
    private function buildMobs(array $mobsData): void {
        foreach ($mobsData as $key => $data) {
            $this->mobs[$key] = new Mob($key, $data);
        }
    }
}