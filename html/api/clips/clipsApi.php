<?php

require_once dirname( __DIR__, 2 ) . "/includes/bootstrap.php";

$services = new ServiceFactory( );
$mode = strtolower( (string) ( $_GET[ "mode" ] ?? "recent" ) );
$range = strtolower( (string) ( $_GET[ "range" ] ?? "month" ) );
$limit = max( 1, min( 100, (int) ( $_GET[ "limit" ] ?? 50 ) ) );

try {
    $catalog = $services->clipReviewManager( )->catalog( $limit );
    $merged = $catalog[ "clips" ];
    if ( !empty( $catalog[ "warning" ] ) ) {
        $services->logger( Logger::CHANNEL_API )->warning( "Using stored clips because Twitch refresh failed", [
            "error" => $catalog[ "warning" ],
        ] );
    }

    $clips = array_values(
        array_filter(
            $merged,
            static fn( array $clip ): bool => (int) ( $clip[ "reviewStatus" ] ?? 0 ) === 1 && !empty( $clip[ "enabled" ] ),
        ),
    );

    if ( $mode === "top" ) {
        usort(
            $clips,
            static fn( array $a, array $b ): int => ( $b[ "viewCount" ] ?? 0 ) <=> ( $a[ "viewCount" ] ?? 0 ),
        );
    } else {
        usort(
            $clips,
            static fn( array $a, array $b ): int => strcmp(
                (string) ( $b[ "createdAt" ] ?? "" ),
                (string) ( $a[ "createdAt" ] ?? "" ),
            ),
        );
    }

    $clips = array_slice( $clips, 0, $limit );

    $payload = array_map(
        static fn( array $clip ): array => [
            "id" => $clip[ "id" ],
            "url" => $clip[ "url" ] ?? "https://clips.twitch.tv/" . $clip[ "id" ],
            "title" => $clip[ "title" ] ?? "",
            "displayTitle" => $clip[ "customTitle" ] ?: $clip[ "title" ] ?? "Stored clip",
            "creatorName" => $clip[ "creatorName" ] ?? "",
            "viewCount" => (int) ( $clip[ "viewCount" ] ?? 0 ),
            "createdAt" => $clip[ "createdAt" ] ?? "",
            "thumbnailUrl" => $clip[ "thumbnailUrl" ] ?? "",
            "duration" => (float) ( $clip[ "duration" ] ?? 0 ),
            "isFavorite" => !empty( $clip[ "favorite" ] ),
            "playCount" => (int) ( $clip[ "playCount" ] ?? 0 ),
            "maxDuration" => (float) ( $clip[ "maxDuration" ] ?? 0 ),
            "startOffset" => (float) ( $clip[ "startOffset" ] ?? 0 ),
            "enabled" => !isset( $clip[ "enabled" ] ) || !empty( $clip[ "enabled" ] ),
            "hasLocalFile" => !empty( $clip[ "localUrl" ] ),
            "localUrl" => $clip[ "localUrl" ] ?? null,
        ],
        $clips,
    );

    ApiController::sendJson( [
        "success" => true,
        "ok" => true,
        "mode" => $mode,
        "range" => $range,
        "clips" => $payload,
    ] );
} catch ( Throwable $error ) {
    $services->logger( Logger::CHANNEL_API )->exception( $error, [ "endpoint" => "clipsApi" ] );
    ApiController::error( "Clips could not be loaded.", 500 );
}
