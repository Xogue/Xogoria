<?php
require_once dirname( __DIR__ ) . "/../includes/session.php";
$clipManager = new ServiceFactory( )->clipManager( );

$method = $_SERVER[ "REQUEST_METHOD" ] ?? "GET";
$input = $method === "POST" ? $_POST : $_GET;
$clipId = $input[ "clipId" ] ?? $input[ "clip_id" ] ?? "";
$clipId = trim( (string) $clipId );

if ( $clipId === "" ) {
    ApiController::error( "Missing clipId" );
}

// Increment play count; intentionally unauthenticated
try {
    $clipManager->increasePlayCount( $clipId );
} catch ( Throwable $e ) {
    // swallow errors to avoid breaking overlay
 }
