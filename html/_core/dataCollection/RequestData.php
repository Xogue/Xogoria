<?php

class RequestData {
    private array $data = [ ];
    private bool $loaded = false;

    // PUBLIC FUNCTIONS
    public function getUserId( )     : string { return (string) ( $this->get( "userId" ) ?? "" ); }
    public function getUsername( )   : string { return (string) ( $this->get( "username" ) ?? "" ); }
    public function getDisplayName( ): string { return (string) ( $this->get( "displayName" ) ?? "" ); }
    public function getType( )       : string { return (string) ( $this->get( "type" ) ?? "" ); }
    public function getApiKey( )     : string { return (string) ( $this->get( "apiKey" ) ?? "" ); }
    public function getAction( )     : string { return (string) ( $this->get( "action" ) ?? "" ); }
    public function getAmount( )     : int    { return (int) ( $this->get( "amount" ) ?? 0 ); }
    public function getRequest( )    : string { return (string) ( $this->get( "request" ) ?? "" ); }
    public function getMonster( )    : string { return (string) ( $this->get( "monster" ) ?? "" ); }
    public function getObjective( )  : string { return (string) ( $this->get( "objective" ) ?? "" ); }
    public function getQuote( )      : string { return (string) ( $this->get( "quote" ) ?? "" ); }
    public function getDuration( )   : int    { return (int) ( $this->get( "duration" ) ?? 0 ); }
    public function getCost( )       : int    { return (int) ( $this->get( "cost" ) ?? 0 ); }
    public function getCooldown( )   : int    { return (int) ( $this->get( "cooldown" ) ?? -1 ); }

    // PRIVATE FUNCTIONS
    private function getMethod( ): string { return strtoupper( (string) ( $_SERVER[ "REQUEST_METHOD" ] ?? "GET" ) ); }

    private function isPost( ): bool {
        $method = $this->getMethod( ) === "POST";
        return $method;
    }

    private function getData( ) {
        $this->loaded = true;
        if ( !$this->isPost( ) ) {
            $this->data = $_GET;
            return;
        }

        if ( !empty( $_POST ) ) {
            $this->data = $_POST;
            return;
        }

        $rawData = file_get_contents( "php://input" );
        $jsonData = json_decode( (string) $rawData, true );
        $this->data = is_array( $jsonData ) ? $jsonData : [ ];
    }

    private function get( string $key ): mixed {
        if ( !$this->loaded ) {
            $this->getData( );
        }
        return $this->data[ $key ] ?? null;
    }
}
