<?php

class MonsterNameCollection {
    /** @var MonsterName[] */
    private array $monsterNames = [];

    public function add(MonsterName $monsterName): void {
        $this->monsterNames[] = $monsterName;
    }

    public function getAll(): array {
        return $this->monsterNames;
    }
}