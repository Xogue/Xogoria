<?php
require_once dirname( __DIR__ ) . "/includes/bootstrap.php";

$services = new ServiceFactory( );
$captureMode = strtolower( trim( (string) ( $_GET[ "capture" ] ?? "" ) ) );
$providedRequestId = trim( (string) ( $_SERVER[ "HTTP_X_CAPTURE_REQUEST_ID" ] ?? "" ) );
$requestId = preg_replace( '/[^a-zA-Z0-9_-]/', '', $providedRequestId ) ?: bin2hex( random_bytes( 8 ) );
$requestId = substr( $requestId, 0, 64 );
$apiLogger = $services->logger( Logger::CHANNEL_API );

if ( $providedRequestId !== "" && $captureMode === "" ) {
    $apiLogger->warning( "Traced API request did not select Streamer.bot capture mode", [
        "requestId" => $requestId,
        "method" => $_SERVER[ "REQUEST_METHOD" ] ?? "GET",
    ] );
}

if ( in_array( $captureMode, [ "commands", "command", "streamerbot" ], true ) ) {
    if ( !headers_sent( ) ) {
        header( "X-Xogoria-Request-ID: {$requestId}" );
    }
    $apiLogger->info( "Streamer.bot capture request received", [
        "requestId" => $requestId,
        "method" => $_SERVER[ "REQUEST_METHOD" ] ?? "GET",
        "contentType" => $_SERVER[ "CONTENT_TYPE" ] ?? "",
        "contentLength" => (int) ( $_SERVER[ "CONTENT_LENGTH" ] ?? 0 ),
        "apiKeyPresent" => trim( (string) ( $_SERVER[ "HTTP_X_API_KEY" ] ?? "" ) ) !== "",
    ] );

    if ( strtoupper( (string) ( $_SERVER[ "REQUEST_METHOD" ] ?? "GET" ) ) !== "POST" ) {
        $apiLogger->warning( "Streamer.bot capture rejected because method was not POST", [ "requestId" => $requestId ] );
        ApiController::error( "Streamer.bot captures require POST.", 405 );
    }

    $expectedKey = $services->contextManager( )->getPrivateApi( )->getWebApiKey( );
    $providedKey = trim( (string) ( $_SERVER[ "HTTP_X_API_KEY" ] ?? "" ) );
    if ( $expectedKey === "" || $providedKey === "" || !hash_equals( $expectedKey, $providedKey ) ) {
        $apiLogger->warning( "Streamer.bot capture authentication failed", [
            "requestId" => $requestId,
            "providedKeyPresent" => $providedKey !== "",
            "expectedKeyConfigured" => $expectedKey !== "",
        ] );
        ApiController::authFailed( "A valid X-API-Key header is required." );
    }

    try {
        $raw = file_get_contents( "php://input" );
        $raw = $raw === false ? "" : $raw;
        $apiLogger->info( "Streamer.bot capture body read", [
            "requestId" => $requestId,
            "bytes" => strlen( $raw ),
        ] );
        $capture = $services->commandCaptureManager( )->capture( $raw, $requestId );
        ApiController::sendJson( [
            "success" => true,
            "requestId" => $requestId,
            "capture" => $capture,
        ], 201 );
    } catch ( InvalidArgumentException $error ) {
        $apiLogger->warning( "Streamer.bot capture payload rejected", [
            "requestId" => $requestId,
            "message" => $error->getMessage( ),
        ] );
        ApiController::error( $error->getMessage( ), 422 );
    } catch ( Throwable $error ) {
        $apiLogger->exception( $error, [
            "endpoint" => "streamerbotCapture",
            "requestId" => $requestId,
        ] );
        ApiController::error( "The Streamer.bot capture could not be stored.", 500 );
    }
}

new ApiController( $services )->respond( );
