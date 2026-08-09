<?php

final class TwitchClipService {
    private const TOKEN_CACHE_PREFIX = "xogoria-twitch-app-token-";

    // MAGIC FUNCTIONS
    public function __construct( private ConfigManager $config, private Logger $logger ) { }

    // PUBLIC FUNCTIONS
    public function twitchUrl( string $clipId ): string { return "https://clips.twitch.tv/" . rawurlencode( $clipId ); }

    public function downloadClip( string $clipId, string $thumbnailUrl, string $destination ): void {
        $downloadUrl = null;
        try {
            $downloadUrl = $this->officialDownloadUrl( $clipId );
        } catch ( RuntimeException $error ) {
            $this->logger->warning( "Twitch clip download endpoint failed; trying the thumbnail URL", [
                "clipId" => $clipId,
                "error" => $error->getMessage( ),
            ] );
        }

        $downloadUrl ??= $this->downloadUrlFromThumbnail( $thumbnailUrl );
        if ( $downloadUrl === null ) {
            throw new RuntimeException( "Twitch did not provide a downloadable MP4 for this clip." );
        }

        $response = CurlController::download( $downloadUrl, $destination )
            ->timeout( 120 )
            ->send( );
        if ( !$response->isOk( ) || !is_file( $destination ) || filesize( $destination ) <= 0 ) {
            @unlink( $destination );
            throw new RuntimeException( "The clip MP4 could not be downloaded from Twitch." );
        }
    }

    public function recent( int $limit = 50 ): array {
        $private = $this->config->getPrivateStore( );
        $clientId = trim( $private->getClientId( ) );
        $broadcasterId = trim( $private->getSenderId( ) );
        if ( $clientId === "" || $broadcasterId === "" ) {
            throw new RuntimeException( "Twitch client ID or broadcaster ID is missing" );
        }

        $url =
            $this->config->getClipsUrl( ) .
            "?" .
            http_build_query( [
                "broadcaster_id" => $broadcasterId,
                "first" => max( 1, min( 100, $limit ) ),
            ] );

        $configuredToken = trim( $private->getTwitchToken( ) );
        $hasClientSecret = trim( $private->getClientSecret( ) ) !== "";
        $token = $hasClientSecret ? $this->appAccessToken( false ) : $configuredToken;
        if ( $token === "" ) {
            throw new RuntimeException( "Twitch access token or client secret is missing" );
        }
        try {
            $data = $this->getJson( $url, $clientId, $token );
        } catch ( RuntimeException $error ) {
            if ( $error->getCode( ) !== 401 || !$hasClientSecret ) {
                throw $error;
            }

            // A cached app token can be revoked before its advertised expiry. Refresh and retry once.
            $data = $this->getJson( $url, $clientId, $this->appAccessToken( true ) );
        }

        return array_map(
            fn( array $clip ): array => $this->normalize( $clip ),
            (array) ( $data[ "data" ] ?? [ ] ),
        );
    }

    // PRIVATE FUNCTIONS
    private function officialDownloadUrl( string $clipId ): ?string {
        $private = $this->config->getPrivateStore( );
        $clientId = trim( $private->getClientId( ) );
        $broadcasterId = trim( $private->getSenderId( ) );
        $editorId = trim( $private->getTwitchEditorId( ) );
        $endpoint = trim( $this->config->getClipDownloadUrl( ) );
        if ( $clientId === "" || $broadcasterId === "" || $editorId === "" || $endpoint === "" ) {
            throw new RuntimeException( "Twitch clip-download configuration is incomplete." );
        }

        $url = rtrim( $endpoint, "?&" ) . "?" . http_build_query( [
            "broadcaster_id" => $broadcasterId,
            "editor_id" => $editorId,
            "clip_id" => $clipId,
        ] );
        $configuredToken = trim( $private->getTwitchToken( ) );
        $hasClientSecret = trim( $private->getClientSecret( ) ) !== "";
        $token = $hasClientSecret ? $this->appAccessToken( false ) : $configuredToken;
        if ( $token === "" ) {
            throw new RuntimeException( "Twitch access token or client secret is missing." );
        }

        try {
            $data = $this->getJson( $url, $clientId, $token );
        } catch ( RuntimeException $error ) {
            if ( $error->getCode( ) !== 401 || !$hasClientSecret ) {
                throw $error;
            }
            $data = $this->getJson( $url, $clientId, $this->appAccessToken( true ) );
        }

        $row = (array) ( $data[ "data" ][ 0 ] ?? [ ] );
        $landscapeUrl = trim( (string) ( $row[ "landscape_download_url" ] ?? "" ) );
        $portraitUrl = trim( (string) ( $row[ "portrait_download_url" ] ?? "" ) );
        return $landscapeUrl !== "" ? $landscapeUrl : ( $portraitUrl !== "" ? $portraitUrl : null );
    }

    private function downloadUrlFromThumbnail( string $thumbnailUrl ): ?string {
        $marker = strpos( $thumbnailUrl, "-preview-" );
        return $marker === false ? null : substr( $thumbnailUrl, 0, $marker ) . ".mp4";
    }

    private function appAccessToken( bool $forceRefresh ): string {
        $private = $this->config->getPrivateStore( );
        $clientId = trim( $private->getClientId( ) );
        $clientSecret = trim( $private->getClientSecret( ) );
        if ( $clientId === "" || $clientSecret === "" ) {
            throw new RuntimeException( "Twitch client credentials are incomplete" );
        }

        $cachePath = $this->tokenCachePath( $clientId );
        if ( !$forceRefresh ) {
            $cached = $this->readTokenCache( $cachePath );
            if ( $cached !== "" ) {
                return $cached;
            }
        }

        $postBody = http_build_query( [
            "client_id" => $clientId,
            "client_secret" => $clientSecret,
            "grant_type" => "client_credentials",
        ] );
        $context = stream_context_create( [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                "content" => $postBody,
                "timeout" => 10,
                "ignore_errors" => true,
            ],
        ] );

        $body = @file_get_contents( $this->config->getTokenUrl( ), false, $context );
        $status = $this->statusCode( $http_response_header ?? [ ] );
        if ( $body === false || $status < 200 || $status >= 300 ) {
            $this->logger->error( "Twitch app-token request failed", [ "status" => $status ] );
            throw new RuntimeException( "Twitch authentication failed with status {$status}", $status );
        }

        try {
            $data = json_decode( $body, true, flags: JSON_THROW_ON_ERROR );
        } catch ( JsonException $error ) {
            throw new RuntimeException( "Twitch returned invalid authentication data", previous: $error );
        }

        $token = trim( (string) ( $data[ "access_token" ] ?? "" ) );
        if ( $token === "" ) {
            throw new RuntimeException( "Twitch authentication returned no access token" );
        }

        $expiresIn = max( 120, (int) ( $data[ "expires_in" ] ?? 3600 ) );
        $this->writeTokenCache( $cachePath, $token, time( ) + $expiresIn - 60 );
        return $token;
    }

    private function getJson( string $url, string $clientId, string $token ): array {
        $context = stream_context_create( [
            "http" => [
                "method" => "GET",
                "header" => "Client-ID: {$clientId}\r\nAuthorization: Bearer {$token}\r\nAccept: application/json\r\n",
                "timeout" => 10,
                "ignore_errors" => true,
            ],
        ] );

        $body = @file_get_contents( $url, false, $context );
        $status = $this->statusCode( $http_response_header ?? [ ] );
        if ( $body === false || $status < 200 || $status >= 300 ) {
            $this->logger->error( "Twitch clips request failed", [ "status" => $status ] );
            throw new RuntimeException( "Twitch clips request failed with status {$status}", $status );
        }
        try {
            return json_decode( $body, true, flags: JSON_THROW_ON_ERROR );
        } catch ( JsonException $error ) {
            throw new RuntimeException( "Twitch returned invalid clip data", previous: $error );
        }
    }

    private function readTokenCache( string $path ): string {
        if ( !is_file( $path ) ) {
            return "";
        }
        $data = json_decode( (string) @file_get_contents( $path ), true );
        if ( !is_array( $data ) || (int) ( $data[ "expiresAt" ] ?? 0 ) <= time( ) ) {
            return "";
        }
        return trim( (string) ( $data[ "accessToken" ] ?? "" ) );
    }

    private function writeTokenCache( string $path, string $token, int $expiresAt ): void {
        $payload = json_encode( [
            "accessToken" => $token,
            "expiresAt" => $expiresAt,
        ], JSON_UNESCAPED_SLASHES );
        if ( $payload === false || @file_put_contents( $path, $payload, LOCK_EX ) === false ) {
            $this->logger->warning( "Twitch app token could not be cached" );
            return;
        }
        @chmod( $path, 0600 );
    }

    private function tokenCachePath( string $clientId ): string {
        return rtrim( sys_get_temp_dir( ), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR .
            self::TOKEN_CACHE_PREFIX . hash( "sha256", $clientId ) . ".json";
    }

    private function statusCode( array $headers ): int {
        return isset( $headers[ 0 ] ) && preg_match( "/\s(\d{3})\s/", (string) $headers[ 0 ], $matches )
            ? (int) $matches[ 1 ]
            : 0;
    }

    private function normalize( array $clip ): array {
        return [
            "id" => (string) ( $clip[ "id" ] ?? "" ),
            "url" => (string) ( $clip[ "url" ] ?? "" ),
            "embedUrl" => (string) ( $clip[ "embed_url" ] ?? "" ),
            "title" => (string) ( $clip[ "title" ] ?? "" ),
            "creatorName" => (string) ( $clip[ "creator_name" ] ?? "" ),
            "viewCount" => (int) ( $clip[ "view_count" ] ?? 0 ),
            "createdAt" => (string) ( $clip[ "created_at" ] ?? "" ),
            "thumbnailUrl" => (string) ( $clip[ "thumbnail_url" ] ?? "" ),
            "duration" => (float) ( $clip[ "duration" ] ?? 0 ),
        ];
    }
}
