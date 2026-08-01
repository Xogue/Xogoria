<?php

class PrivateApiContext {
    private string $apiKey;
    private string $panelBaseUrl;
    private string $serverPath;
    private string $panelApiKey;

    // MAGIC FUNCTIONS
    public function __construct( private PrivateStore $privateStore ) {
        $this->apiKey = $privateStore->getWebApiKey( );
        $this->panelBaseUrl = $privateStore->getPanelBaseUrl( );
        $this->serverPath = $privateStore->getServerPath( );
        $this->panelApiKey = $privateStore->getPanelApiKey( );
    }

    // PUBLIC FUNCTIONS
    public function getWebApiKey( )   : string { return $this->apiKey; }
    public function getPanelBaseUrl( ): string { return $this->panelBaseUrl; }
    public function getServerPath( )  : string { return $this->serverPath; }
    public function getPanelApiKey( ) : string { return $this->panelApiKey; }
}
