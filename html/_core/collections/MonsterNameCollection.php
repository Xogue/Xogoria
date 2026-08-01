<?php

class MonsterNameCollection {
    /** @var MonsterName[] */
    private array $monsterNames = [ ];

    // PUBLIC FUNCTIONS
    public function getAll( ): array { return $this->monsterNames; }
    public function add( MonsterName $monsterName ): void { $this->monsterNames[ ] = $monsterName; }
}
