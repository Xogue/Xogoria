<?php

class BackblazeBridge {
    private Logger $logger;

    private string $keyId;
    private string $applicationKey;
    private string $bucketName;
    private string $publicBaseUrl;

    private ?array $authorization = null;
    private ?string $clipBucketId = null;
    private PrivateStore $privateStore;

    // MAGIC FUNCTIONS
    public function __construct( PrivateStore $privateStore ) {
        $this->privateStore = $privateStore;
        $this->keyId = $privateStore->backblazeKeyId( );
        $this->applicationKey = $privateStore->backblazeApplicationKey( );
        $this->bucketName = $privateStore->backblazeBucket( );
        $this->publicBaseUrl = $privateStore->backblazePublicBaseUrl( );
        $this->logger = new Logger( );
    }

    // PUBLIC FUNCTIONS
    public function uploadClip( string $clipId, string $filePath ): ?string {
        return $this->uploadClipObject( $filePath, $clipId . ".mp4" );
    }

    public function uploadNormalizedClip( string $clipId, string $filePath ): ?string {
        $contentHash = hash_file( "sha256", $filePath );
        if ( $contentHash === false ) {
            $this->logger->error( "Unable to hash normalized clip", [ "clipId" => $clipId ] );
            return null;
        }

        return $this->uploadClipObject(
            $filePath,
            $clipId . "-normalized-" . substr( $contentHash, 0, 12 ) . ".mp4",
        );
    }

    // PRIVATE FUNCTIONS
    private function uploadClipObject( string $filePath, string $fileName ): ?string {
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

        if ( !$this->uploadFile( $urlResponse, $filePath, $fileName ) ) {
            return null;
        }

        return rtrim( $this->publicBaseUrl, "/" ) .
            "/xogue29_clips/" .
            rawurlencode( $fileName );
    }

    private function authorize( ): bool {
        if ( $this->authorization !== null ) {
            return true;
        }

        $response = CurlController::get( $this->privateStore->backblazeAuthorizeUrl( ) )
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

        $response = CurlController::postJson( $this->accountApiUrl( $this->privateStore->backblazeListBucketUrl( ) ), [
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
        $response = CurlController::postJson( $this->accountApiUrl( $this->privateStore->backblazeUploadUrl( ) ), [
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

    private function uploadFile( array $urlResponse, string $filePath, string $fileName ): bool {
        $data = @file_get_contents( $filePath );
        if ( $data === false ) {
            $this->logger->error( "Backblaze upload could not read file", [ "path" => $filePath ] );
            return false;
        }

        $objectKey = "xogue29_clips/" . rawurlencode( $fileName );

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

    private function accountApiUrl( string $configuredUrl ): string {
        $apiBase = rtrim( (string) ( $this->authorization[ "apiUrl" ] ?? "" ), "/" );
        $path = (string) parse_url( $configuredUrl, PHP_URL_PATH );
        if ( $apiBase === "" || $path === "" ) {
            throw new RuntimeException( "Backblaze account API configuration is incomplete" );
        }
        return $apiBase . "/" . ltrim( $path, "/" );
    }
}
