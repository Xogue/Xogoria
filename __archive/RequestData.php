<?php

class RequestData {
    private array $data = [];

    public function getUserId(): string {return (string) ( $this->get( 'userId' ) ?? '' );}
    public function getUsername(): string {return (string) ( $this->get( 'username' ) ?? '' );}
    public function getDisplayName(): string {return (string) ( $this->get( 'displayName' ) ?? '' );}
    public function getType(): string {return (string) ( $this->get( 'type' ) ?? '' );}
    public function getApiKey(): string {return (string) ( $this->get( 'apiKey' ) ?? '' );}
    public function getActionKey(): string {return (string) ( $this->get( 'actionKey' ) ?? '' );}
    public function getAction(): string {return (string) ( $this->get( 'action' ) ?? '' );}
    public function getAmount(): int {return (int) ( $this->get( 'amount' ) ?? 0 );}
    public function getRequestType(): string {return (string) ( $this->get( 'type' ) ?? '' );}
    public function getMonster(): string {return (string) ( $this->get( 'monster' ) ?? '' );}
    public function getObjective(): string {return (string) ( $this->get( 'objective' ) ?? '' );}
    public function getQuote(): string {return (string) ( $this->get( 'quote' ) ?? '' );}

    // PRIVATE FUNCTIONS
    private function getMethod(): string {
        return strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
    }

    private function isPost(): bool {
        $method = $this->getMethod() === 'POST';
        return $method;
    }

    private function getData() {
        $this->data = $this->isPost() ? $_POST : $_GET;
    }

    private function get( string $key ): mixed {
        if ( empty( $this->data ) ) {$this->getData();}
        return $this->data[$key] ?? null;
    }
}
