<?php

class TwitchController {
    private TwitchAppBridge $twitchAppBridge;
    private TwitchUserBridge $twitchUserBridge;
    private TwitchClipBridge $twitchClipBridge;

    // MAGIC FUNCTIONS
    public function __construct( DataController $dataController ) {
        $this->twitchAppBridge = new TwitchAppBridge( $dataController->getTwitchAppContext( ) );
        $this->twitchUserBridge = new TwitchUserBridge( $dataController->getTwitchUserContext( ) );
        $this->twitchClipBridge = new TwitchClipBridge( );
    }

    // PUBLIC FUNCTIONS
    public function getUserBridge( ) { return $this->twitchUserBridge; }
    public function startLogin( ) { $this->twitchAppBridge->sendForCode( ); }
    public function getPostLoginRedirect( ) { return $this->twitchAppBridge->getPostLoginRedirect( ); }
    public function getAccessCode( ) { return $this->twitchAppBridge->getAccessToken( ); }

    public function getUserData( string $accessToken ) { return $this->twitchUserBridge->getUserData( $accessToken ); }
}
