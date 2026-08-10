<?php
require_once dirname( __DIR__ ) . "/../includes/session.php";
$services = new ServiceFactory( );
$clipManager = $services->clipManager( );

$method = $_SERVER[ "REQUEST_METHOD" ] ?? "GET";
$input = $method === "POST" ? $_POST : $_GET;
$clipId = $input[ "clipId" ] ?? $input[ "clip_id" ] ?? "";
$clipId = trim( (string) $clipId );

if ( $clipId === "" ) {
    ApiController::error( "Missing clipId" );
}

// Increment play count; intentionally unauthenticated
try {
    if ( !$clipManager->increasePlayCount( $clipId ) ) {
        ApiController::error( "The clip play could not be recorded.", 500 );
    }
    $clipManager->alignNewApprovedPlayCounts( );
    ApiController::sendJson( [ "success" => true, "ok" => true ] );
} catch ( Throwable $e ) {
    $services->logger( Logger::CHANNEL_API )->exception( $e, [
        "endpoint" => "clipsPlay",
        "clipId" => $clipId,
    ] );
    ApiController::error( "The clip play could not be recorded.", 500 );
}
