<?php

class UserController {
    // MAGIC FUNCTIONS
    public function __construct(
        private MySqlManager $mySqlManager,
        private UserContext $userContext,
        private InputDataContext $inputDataContext,
    ) { }

    // PUBLIC FUNCTIONS
    public function syncTwitchSessionUser( ): bool {
        return $this->ensureTwitchUser( );
    }

    public function ensureTwitchUser( ): bool {
        $twitchUserId = $this->inputDataContext->getUserId( );
        $twitchLoginName = $this->inputDataContext->getUsername( );
        $twitchDisplayName = $this->inputDataContext->getDisplayName( );
        if ( $twitchLoginName === "" ) {
            // Some Streamer.bot AG actions only send the stable Twitch user ID.
            $twitchLoginName = $twitchUserId;
        }
        if ( $twitchDisplayName === "" ) {
            $twitchDisplayName = $twitchLoginName;
        }

        if ( $twitchUserId === "" ) {
            new Logger( Logger::CHANNEL_WEB )->warning( "Cannot sync an incomplete Twitch session" );
            return false;
        }

        $rows = $this->mySqlManager->selectUserWithId( $twitchUserId );
        if ( !empty( $rows ) ) {
            $this->userContext->refreshIdentity( $this->inputDataContext );
            return true;
        }

        $inserted = $this->mySqlManager->insertUser(
            $twitchUserId,
            $twitchLoginName,
            $twitchDisplayName,
        );

        // A concurrent first request may have inserted the same Twitch user.
        if ( !$inserted && empty( $this->mySqlManager->selectUserWithId( $twitchUserId ) ) ) {
            new Logger( Logger::CHANNEL_WEB )->error( "Failed to insert Twitch user", [
                "user_id" => $twitchUserId,
            ] );

            return false;
        }

        $this->userContext->refreshIdentity( $this->inputDataContext );
        return $this->userContext->userLoggedIn( );
    }

    public function logout( ): void {
        $this->userContext->logout( );
        session_unset( );
        session_destroy( );
        setcookie( session_name( ), "", time( ) - 3600, "/" );
        header( "Location: " . "/about.php" );
    }
}
