<?php

class TwitchAppContext {
    private string $clientId;
    private string $clientSecret;
    private string $authStart;
    private string $authCallback;
    private string $senderId;
    private string $panelApiKey;
    private string $authUrl;
    private string $tokenUrl;
    private array $codeQuery;

    // MAGIC FUNCTIONS
    public function __construct( ConfigManager $configManager ) {
        $this->clientId = $configManager->getClientId( );
        $this->clientSecret = $configManager->getClientSecret( );
        $this->authStart = $configManager->getAuthStart( );
        $this->authCallback = $configManager->getAuthCallback( );
        $this->senderId = $configManager->getSenderId( );
        $this->authUrl = $configManager->getAuthUrl( );
        $this->tokenUrl = $configManager->getTokenUrl( );
        $this->codeQuery = $configManager->getCodeQuery( );
        $this->panelApiKey = $configManager->getPanelApiKey( );
    }

    // PUBLIC FUNCTIONS
    public function getClientId( )    : string { return $this->clientId; }
    public function getClientSecret( ): string { return $this->clientSecret; }
    public function getAuthStart( )   : string { return $this->authStart; }
    public function getAuthCallback( ): string { return $this->authCallback; }
    public function getSenderId( )    : string { return $this->senderId; }
    public function getAuthUrl( )     : string { return $this->authUrl; }
    public function getTokenUrl( )    : string { return $this->tokenUrl; }
    public function getCodeQuery( )   : array  { return $this->codeQuery; }
    public function getPanelApiKey( ) : string { return $this->panelApiKey; }
}
