<?php

class CooldownManager {
    private const KEY_PREFIX = "xogoria:cooldown:";

    // MAGIC FUNCTIONS
    public function __construct( private UserContext $userContext, private DataStore $dataStore ) { }

    // PUBLIC FUNCTIONS
    public function startCooldown( string $commandName, int $cooldown ): void {
        $cooldown = max( 0, $cooldown );
        if ( $commandName === "" || $cooldown == 0 ) {
            return;
        }

        $key = $this->buildKey( $commandName );
        $this->dataStore->setExpiringKey( $key, $cooldown, $commandName );
        return;
    }

    public function getCooldown( string $commandName ): int|bool {
        if ( $commandName === "" ) {
            return false;
        }

        $key = $this->buildKey( $commandName );
        $ttl = (int) $this->dataStore->checkExpiringKey( $key );
        return $ttl > 0 ? $ttl : false;
    }

    // PRIVATE FUNCTIONS
    private function buildKey( string $commandName ): string { return self::KEY_PREFIX . $this->identityKey( ) . ":" . $commandName; }

    private function identityKey( ): string {
        return match ( true ) {
            $this->userContext->getUserId( ) !== "" => $this->userContext->getUserId( ),
            $this->userContext->getLoginName( ) !== "" => $this->userContext->getLoginName( ),
            default => "anonymous",
        };
    }
}
