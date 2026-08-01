<?php

class SessionData {
    private array $data = [ ];

    // PUBLIC FUNCTIONS
    public function hasIdentity( ): bool { return $this->getTwitchId( ) !== false && $this->getTwitchId( ) !== ""; }

    public function getTwitchId( ): string|false {
        if ( empty( $this->data ) ) {
            $this->data = $_SESSION;
        }
        return $this->data[ "twitchUserId" ] ?? false;
    }

    public function getTwitchLoginName( ): string|false {
        if ( empty( $this->data ) ) {
            $this->data = $_SESSION;
        }
        return $this->data[ "twitchLoginName" ] ?? false;
    }

    public function getTwitchDisplayName( ): string|false {
        if ( empty( $this->data ) ) {
            $this->data = $_SESSION;
        }
        return $this->data[ "twitchDisplayName" ] ?? false;
    }
}
