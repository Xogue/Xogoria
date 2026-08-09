<?php

require_once dirname( __DIR__, 2 ) . "/includes/bootstrap.php";

$services = new ServiceFactory( );
$admin = $services->adminController( );
$user = $admin->requireAdmin( true );
$manager = $services->clipReviewManager( );

try {
    if ( ( $_SERVER[ "REQUEST_METHOD" ] ?? "GET" ) === "GET" ) {
        ApiController::sendJson( [ "success" => true ] + $manager->list( ) );
    }

    $contentType = (string) ( $_SERVER[ "CONTENT_TYPE" ] ?? "" );
    $input = str_contains( $contentType, "application/json" )
        ? json_decode( (string) file_get_contents( "php://input" ), true )
        : $_POST;
    if ( !is_array( $input ) ) {
        ApiController::error( "Invalid request body." );
    }
    $admin->verifyCsrf( (string) ( $input[ "csrfToken" ] ?? "" ) );
    $action = (string) ( $input[ "action" ] ?? "" );
    $clipId = trim( (string) ( $input[ "clipId" ] ?? "" ) );
    if ( $clipId === "" ) {
        ApiController::error( "A clip ID is required.", 422 );
    }

    if ( $action === "normalizeAudio" || $action === "approve" ) {
        set_time_limit( 300 );
    }

    $result = match ( $action ) {
        "approve" => [
            "success" => true,
        ] + $manager->approve( $clipId, (array) ( $input[ "clip" ] ?? [ ] ) ),
        "ignore" => [ "success" => $manager->ignore( $clipId ) ],
        "restore" => [ "success" => $manager->restore( $clipId ) ],
        "save" => [ "success" => $manager->save( $clipId, (array) ( $input[ "data" ] ?? [ ] ) ) ],
        "normalizeAudio" => [ "success" => true, "clip" => $manager->normalizeAudio( $clipId ) ],
        "requestDeletion" => [
            "success" => true,
            "deletion" => $manager->requestDeletion(
                $clipId,
                $user->getDisplayName( ) ?: $user->getLoginName( ),
            ),
        ],
        default => throw new InvalidArgumentException( "Unknown clip action" ),
    };
    if ( !$result[ "success" ] ) {
        ApiController::error( "The clip operation failed.", 500 );
    }
    ApiController::sendJson( $result );
} catch ( InvalidArgumentException $error ) {
    ApiController::error( $error->getMessage( ), 422 );
} catch ( RuntimeException $error ) {
    $services->logger( Logger::CHANNEL_API )->exception( $error, [ "endpoint" => "clipsAdmin" ] );
    ApiController::error( $error->getMessage( ), 500 );
} catch ( Throwable $error ) {
    $services->logger( Logger::CHANNEL_API )->exception( $error, [ "endpoint" => "clipsAdmin" ] );
    ApiController::error( "The clip operation could not be completed.", 500 );
}
