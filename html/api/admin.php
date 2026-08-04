<?php

require_once dirname( __DIR__ ) . "/includes/bootstrap.php";

$services = new ServiceFactory( );
$admin = $services->adminController( );
$admin->requireAdmin( true );

if ( ( $_SERVER[ "REQUEST_METHOD" ] ?? "GET" ) !== "POST" ) {
    ApiController::error( "POST is required.", 405 );
}

$contentType = (string) ( $_SERVER[ "CONTENT_TYPE" ] ?? "" );
$input = str_contains( $contentType, "application/json" )
    ? json_decode( (string) file_get_contents( "php://input" ), true )
    : $_POST;
if ( !is_array( $input ) ) {
    ApiController::error( "Invalid request body." );
}

$admin->verifyCsrf( (string) ( $input[ "csrfToken" ] ?? "" ) );
$domain = (string) ( $input[ "domain" ] ?? "" );
$action = (string) ( $input[ "action" ] ?? "" );

try {
    if ( $domain === "resource" ) {
        $resource = (string) ( $input[ "resource" ] ?? "" );
        $manager = $admin->resources( );
        $success = match ( $action ) {
            "save" => $manager->save( $resource, (array) ( $input[ "data" ] ?? [ ] ) ),
            "delete" => $manager->delete( $resource, $input[ "key" ] ?? null ),
            default => throw new InvalidArgumentException( "Unknown resource action" ),
        };
        if ( !$success ) {
            ApiController::error( "The database operation failed.", 500 );
        }
        ApiController::sendJson( [ "success" => true, "rows" => $manager->list( $resource ) ] );
    }

    if ( $domain === "config" && $action === "save" ) {
        if (
            !$admin
                ->configs( )
                ->save( (string) ( $input[ "config" ] ?? "" ), (string) ( $input[ "source" ] ?? "" ) )
        ) {
            ApiController::error( "The configuration could not be saved.", 500 );
        }
        ApiController::sendJson( [ "success" => true ] );
    }

    if ( $domain === "community" ) {
        $source = (string) ( $input[ "source" ] ?? "" );
        $manager = $admin->community( );
        if ( $action === "preview" ) {
            ApiController::sendJson( [ "success" => true, "html" => $manager->render( $source ) ] );
        }
        if ( $action === "save" ) {
            if ( !$manager->save( $source ) ) {
                ApiController::error( "The community page could not be saved.", 500 );
            }
            ApiController::sendJson( [ "success" => true, "html" => $manager->render( $source ) ] );
        }
        throw new InvalidArgumentException( "Unknown community content action" );
    }

    ApiController::error( "Unknown admin operation." );
} catch ( InvalidArgumentException $error ) {
    ApiController::error( $error->getMessage( ), 422 );
} catch ( Throwable $error ) {
    $services
        ->logger( Logger::CHANNEL_API )
        ->exception( $error, [ "domain" => $domain, "action" => $action ] );

    ApiController::error( "The admin operation could not be completed.", 500 );
}
