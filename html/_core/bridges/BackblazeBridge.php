<?php

class BackblazeBridge {
    private Logger $logger;

    private string $keyId;
    private string $applicationKey;
    private string $bucketName;
    private string $publicBaseUrl;

    private ?array $authorization = null;
    private ?string $clipBucketId = null;
    private PrivateLoader $privateLoader;

    // MAGIC FUNCTIONS
    public function __construct( ) {
        $this->privateLoader = new PrivateLoader( "backblaze" );
        $this->keyId = $this->privateLoader->getDetail( "key" );
        $this->applicationKey = $this->privateLoader->getDetail( "applicationKey" );
        $this->bucketName = $this->privateLoader->getDetail( "bucket" );
        $this->publicBaseUrl = $this->privateLoader->getDetail( "publicBaseUrl" );
        $this->logger = new Logger( );
    }

    // PUBLIC FUNCTIONS
    public function uploadClip( string $clipId, string $filePath ): ?string {
        if ( !is_file( $filePath ) ) {
            $this->logger->error( "Invalid upload file path", [ "path" => $filePath ] );
            return null;
        }

        if ( !$this->authorize( ) ) {
            $this->logger->error( "Backblaze upload authorization failed" );
            return null;
        }

        if ( !$this->getClipBucketId( ) ) {
            $this->logger->error( "Backblaze bucket ID is missing" );
            return null;
        }

        $urlResponse = $this->getUploadUrl( );
        if ( $urlResponse === null ) {
            return null;
        }

        if ( !$this->uploadFile( $urlResponse, $filePath, $clipId ) ) {
            return null;
        }

        return rtrim( $this->publicBaseUrl, "/" ) .
            "/xogue29_clips/" .
            rawurlencode( $clipId ) .
            ".mp4";
    }

    // PRIVATE FUNCTIONS
    private function authorize( ): bool {
        if ( $this->authorization !== null ) {
            return true;
        }

        $response = CurlController::get( $this->privateLoader->getDetail( "authorizeUrl" ) )
            ->basicAuth( $this->keyId, $this->applicationKey )
            ->send( );

        if ( !$response->isOk( ) ) {
            $this->logger->error( "Backblaze authorization failed", [
                "response" => $response->summary( ),
            ] );

            return false;
        }

        $json = $response->json( );
        if ( !is_array( $json ) || empty( $json[ "apiUrl" ] ) || empty( $json[ "authorizationToken" ] ) ) {
            $this->logger->error( "Backblaze authorization returned invalid JSON" );
            return false;
        }

        $this->authorization = [
            "apiUrl" => (string) $json[ "apiUrl" ],
            "authorizationToken" => (string) $json[ "authorizationToken" ],
            "downloadUrl" => isset( $json[ "downloadUrl" ] ) ? (string) $json[ "downloadUrl" ] : "",
            "accountId" => isset( $json[ "accountId" ] ) ? (string) $json[ "accountId" ] : "",
        ];

        return true;
    }

    private function getClipBucketId( ): bool {
        if ( $this->clipBucketId !== null ) {
            return true;
        }

        if ( $this->authorization === null && !$this->authorize( ) ) {
            $this->logger->error( "Backblaze bucket lookup is unauthorized" );
            return false;
        }

        $response = CurlController::postJson( $this->privateLoader->getDetail( "listBucketUrl" ), [
            "accountId" => $this->authorization[ "accountId" ],
            "bucketName" => $this->bucketName,
        ] )
            ->authorization( $this->authorization[ "authorizationToken" ] )
            ->send( );

        if ( !$response->isOk( ) ) {
            $this->logger->error( "Backblaze bucket lookup failed", [
                "response" => $response->summary( ),
            ] );

            return false;
        }

        $json = $response->json( );
        if ( !is_array( $json ) || empty( $json[ "buckets" ] ) || !is_array( $json[ "buckets" ] ) ) {
            $this->logger->error( "Backblaze bucket lookup returned invalid JSON" );
            return false;
        }

        foreach ( $json[ "buckets" ] as $bucket ) {
            if ( !isset( $bucket[ "bucketName" ], $bucket[ "bucketId" ] ) ) {
                continue;
            }

            if ( (string) $bucket[ "bucketName" ] === $this->bucketName ) {
                $this->clipBucketId = (string) $bucket[ "bucketId" ];
                return true;
            }
        }

        return false;
    }

    private function getUploadUrl( ): ?array {
        $response = CurlController::postJson( $this->privateLoader->getDetail( "uploadUrl" ), [
            "bucketId" => $this->clipBucketId,
        ] )
            ->authorization( $this->authorization[ "authorizationToken" ] )
            ->send( );

        if ( !$response->isOk( ) ) {
            $this->logger->error( "Backblaze upload URL request failed", [
                "response" => $response->summary( ),
            ] );

            return null;
        }

        $json = $response->json( );
        if ( !is_array( $json ) || empty( $json[ "uploadUrl" ] ) || empty( $json[ "authorizationToken" ] ) ) {
            $this->logger->error( "Backblaze upload URL returned invalid JSON" );
            return null;
        }

        return [
            "uploadUrl" => (string) $json[ "uploadUrl" ],
            "authorizationToken" => (string) $json[ "authorizationToken" ],
        ];
    }

    private function uploadFile( array $urlResponse, string $filePath, string $clipId ): bool {
        $data = @file_get_contents( $filePath );
        if ( $data === false ) {
            $this->logger->error( "Backblaze upload could not read file", [ "path" => $filePath ] );
            return false;
        }

        $objectKey = "xogue29_clips/" . rawurlencode( $clipId ) . ".mp4";

        $response = CurlController::postRaw( $urlResponse[ "uploadUrl" ], $data, "video/mp4" )
            ->headers( [
                "Authorization" => $urlResponse[ "authorizationToken" ],
                "X-Bz-File-Name" => $objectKey,
                "X-Bz-Content-Sha1" => sha1( $data ),
            ] )
            ->send( );

        if ( !$response->isOk( ) ) {
            $this->logger->error( "Backblaze file upload failed", [
                "response" => $response->summary( ),
            ] );

            return false;
        }

        return true;
    }
}
