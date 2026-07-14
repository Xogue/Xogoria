<?php

class GameManager {
    /** @var Game[] */
    private array $games = [];

    public function __construct(private TemplateManager $templateManager) { }

    public function addGames(array $gameData) {
        foreach ($gameData as $gameName => $data) {
            $this->games[$gameName] = new Game($gameName, $data, $this->templateManager);
        }
    }

    public function getMinecraft(): ?Game { return $this->games['minecraft'] ?? null; }
    public function getHytale(): ?Game { return $this->games['hytale'] ?? null; }
    public function getAllGames(): array { return $this->games; }
}