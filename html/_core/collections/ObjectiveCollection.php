<?php

class ObjectiveCollection {
    /** @var Objective[] */
    private array $objectives = [];

    public function add(Objective $objective): void {
        $this->objectives[] = $objective;
    }

    public function getAll(): array {
        return $this->objectives;
    }
}