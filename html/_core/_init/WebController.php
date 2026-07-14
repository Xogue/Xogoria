<?php

final class WebController {
    public function __construct(private ServiceFactory $services) {
        if (!defined('DEV_MODE')) {
            define('DEV_MODE', $this->services->configManager()->isDevMode());
        }
    }

    public function getActiveGame(): Game { return $this->services->configManager()->getActiveGame(); }
    public function getActiveProfile(): Profile { return $this->services->configManager()->getActiveProfile(); }
    public function getTwitchAppContext(): TwitchAppContext { return $this->services->contextManager()->getTwitchApp(); }
    public function getUserContext(): UserContext { return $this->services->contextManager()->getUser(); }
    public function getMySqlManager(): MySqlManager { return $this->services->mySqlManager(); }
    public function getCollectionManager(): CollectionManager { return $this->services->collectionManager(); }
    public function getLoreManager(): LoreManager { return $this->services->loreManager(); }
    public function getGameManager(): GameManager { return $this->services->gameManager(); }
    public function getAssetManager(): AssetManager { return $this->services->assetManager(); }
    public function getTemplatePart(string $name): string { return (string) $this->services->templateManager()->getPart($name); }
    public function loginToTwitch(): void { $this->services->twitchController()->startLogin(); }
    public function getPostLoginRedirect(): string { return $this->services->twitchController()->getPostLoginRedirect(); }
    public function getAccessCode(): mixed { return $this->services->twitchController()->getAccessCode(); }
    public function getUserData(string $accessToken): mixed { return $this->services->twitchController()->getUserData($accessToken); }
    public function syncTwitchSessionUser(): bool { return (bool) $this->services->userController()->syncTwitchSessionUser(); }
    public function logoutUser(): void { $this->services->userController()->logout(); }
}
