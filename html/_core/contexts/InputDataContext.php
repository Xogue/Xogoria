<?php

class InputDataContext {
    private RequestData $requestData;
    private SessionData $sessionData;

    // MAGIC FUNCTIONS
    public function __construct( RequestData $requestData, SessionData $sessionData ) {
        $this->requestData = $requestData;
        $this->sessionData = $sessionData;
    }

    // PUBLIC FUNCTIONS
    // ALL WORKERS

    public function getRequest( )  : string { return $this->requestData->getRequest( ); }
    public function getType( )     : string { return $this->requestData->getType( ); }
    public function getAction( )   : string { return $this->requestData->getAction( ); }
    public function getUrlApiKey( ): string { return $this->requestData->getApiKey( ); }
    public function getDuration( ) : int    { return $this->requestData->getDuration( ); }
    public function getCost( )     : int    { return $this->requestData->getCost( ); }
    public function getCooldown( ) : int    { return $this->requestData->getCooldown( ); }
    // CURRENCY WORKER
    public function getAmount( ): int { return $this->requestData->getAmount( ); }
    // COLLECTION WORKER
    public function getMonster( )  : string { return $this->requestData->getMonster( ); }
    public function getObjective( ): string { return $this->requestData->getObjective( ); }
    public function getQuote( )    : string { return $this->requestData->getQuote( ); }

    public function getUserId( ): string {
        $userId = $this->requestData->getUserId( );
        if ( $userId === "" ) {
            $userId = $this->sessionData->getTwitchId( );
        }
        return $userId;
    }

    public function getUsername( ): string {
        $username = $this->requestData->getUsername( );
        if ( $username === "" ) {
            $username = $this->sessionData->getTwitchLoginName( );
        }
        return $username;
    }

    public function getDisplayName( ): string {
        $displayName = $this->requestData->getDisplayName( );
        if ( $displayName === "" ) {
            $displayName = $this->sessionData->getTwitchDisplayName( );
        }
        return $displayName;
    }

    public function usesExternalIdentity( ): bool {
        $requestUserId = $this->requestData->getUserId( );
        $sessionUserId = $this->sessionData->getTwitchId( );
        return $requestUserId !== "" && $requestUserId !== (string) $sessionUserId;
    }
}
