<?php

final class AdminController {
    // MAGIC FUNCTIONS
    public function __construct( private ServiceFactory $services ) { }

    // PUBLIC FUNCTIONS
    public function resources( ): AdminResourceManager { return $this->services->adminResourceManager( ); }
    public function configs( )  : AdminConfigManager   { return $this->services->adminConfigManager( ); }

    public function requireAdmin( bool $api = false ): UserContext {
        $user = $this->services->contextManager( )->getUser( );
        if ( !$user->isAdmin( ) ) {
            $this->services
                ->logger( $api ? Logger::CHANNEL_API : Logger::CHANNEL_WEB )
                ->warning( "Admin access denied", [ "user_id" => $user->getUserId( ) ] );

            if ( $api ) {
                ApiController::authFailed( "Administrator access is required." );
            }
            http_response_code( 403 );
            header( "Location: /interact.php?admin=denied" );
            exit( );
        }
        return $user;
    }

    public function csrfToken( ): string {
        if ( empty( $_SESSION[ "adminCsrfToken" ] ) ) {
            $_SESSION[ "adminCsrfToken" ] = bin2hex( random_bytes( 32 ) );
        }
        return (string) $_SESSION[ "adminCsrfToken" ];
    }

    public function verifyCsrf( string $token ): void {
        $expected = (string) ( $_SESSION[ "adminCsrfToken" ] ?? "" );
        if ( $expected === "" || $token === "" || !hash_equals( $expected, $token ) ) {
            ApiController::error( "The admin session token is invalid. Refresh and try again.", 419 );
        }
    }
}
