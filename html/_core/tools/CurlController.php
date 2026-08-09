<?php

if ( !class_exists( CurlResponse::class, false ) ) {
    require_once __DIR__ . "/CurlResponse.php";
}

class CurlController {
    private const DEFAULT_USER_AGENT = "XogoriaCurl/1.0";

    private CurlHandle $curlHandle;
    private mixed $downloadHandle = null;
    private array $headers = [ ];
    private bool $returnsResponse = false;

    // MAGIC FUNCTIONS
    public function __construct( string $url, string $panelKey = "", ?array $data = null ) {
        $curlHandle = curl_init( $url );
        if ( !$curlHandle instanceof CurlHandle ) {
            throw new RuntimeException( "Unable to initialize cURL" );
        }
        $this->curlHandle = $curlHandle;

        $this->setReturnTransfer( );
        $this->setTimeout( 20 );
        $this->setUserAgent( self::DEFAULT_USER_AGENT );
        $this->option( CURLOPT_FOLLOWLOCATION, true );
        $this->option( CURLOPT_MAXREDIRS, 3 );
        $this->option( CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
        $this->option( CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );

        if ( $data !== null ) {
            $this->setToPost( );
            $this->setAccept( "application/json" );
            $this->setContentType( "application/json" );
            $this->setAuthorization( "Bearer " . $panelKey );
            $this->setPostData( json_encode( $data, JSON_UNESCAPED_UNICODE ) ?: "{}" );
            $this->setUserAgent( "curl/8.0 (+php cURL relay)" );
        }
    }

    public function __destruct( ) { $this->closeDownloadHandle( ); }

    // PUBLIC FUNCTIONS
    public static function get( string $url ): self {
        return self::responseRequest( new self( $url ) );
    }

    public static function postJson( string $url, array $data ): self {
        $request = self::responseRequest( new self( $url ) );
        $request->setToPost( );
        $request->setAccept( "application/json" );
        $request->setContentType( "application/json" );
        $request->setPostData( json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ?: "{}" );
        return $request;
    }

    public static function postRaw( string $url, string $data, string $contentType ): self {
        $request = self::responseRequest( new self( $url ) );
        $request->setToPost( );
        $request->setContentType( $contentType );
        $request->setPostData( $data );
        return $request;
    }

    public static function download( string $url, string $destination ): self {
        $handle = @fopen( $destination, "wb" );
        if ( $handle === false ) {
            throw new RuntimeException( "Unable to create the download file" );
        }

        $request = self::responseRequest( new self( $url ) );
        $request->downloadHandle = $handle;
        $request->setReturnTransfer( false );
        $request->setOutputFile( $handle );
        return $request;
    }

    public static function postForm( string $url, string $panelApiKey, array $data ): self {
        $newRequest = new self( $url, $panelApiKey );
        $newRequest->setToPost( );
        $newRequest->setContentType( "application/x-www-form-urlencoded" );
        $newRequest->setPostData( http_build_query( $data ) );
        return $newRequest;
    }

    public function headers( array $headers ): self {
        foreach ( $headers as $name => $value ) {
            $this->headers[ $name ] = $value;
        }
        return $this;
    }

    public function timeout( int $seconds ): self { $this->setTimeout( $seconds ); return $this; }
    public function authorization( string $token ): self { $this->setAuthorization( $token ); return $this; }

    public function basicAuth( string $username, string $password ): self {
        $this->setBasicAuth( );
        $this->setBasicCreds( $username, $password );
        return $this;
    }

    public function setToPost( ): void { $this->option( CURLOPT_POST, true ); }
    public function setBasicAuth( ): void { $this->option( CURLOPT_HTTPAUTH, CURLAUTH_BASIC ); }
    public function setCurlHeaders( ): void { $this->option( CURLOPT_HTTPHEADER, $this->formatHeaders( $this->headers ) ); }

    public function setAccept( string $value ): void { $this->headers[ "Accept" ] = $value; }
    public function setContentType( string $value ): void { $this->headers[ "Content-Type" ] = $value; }
    public function setAuthorization( string $value ): void { $this->headers[ "Authorization" ] = $value; }
    public function setClientId( string $value ): void { $this->headers[ "Client-ID" ] = $value; }
    public function setTimeout( int $seconds ): void { $this->option( CURLOPT_TIMEOUT, $seconds ); }
    public function setUserAgent( string $value ): void { $this->option( CURLOPT_USERAGENT, $value ); }
    public function setOutputFile( mixed $fileHandle ): void { $this->option( CURLOPT_FILE, $fileHandle ); }
    public function setPostData( string $data ): void { $this->option( CURLOPT_POSTFIELDS, $data ); }
    public function setBearerToken( string $token ): void { $this->setAuthorization( "Bearer " . $token ); }
    public function setReturnTransfer( bool $value = true ): void { $this->option( CURLOPT_RETURNTRANSFER, $value ); }
    public function setBasicCreds( string $username, string $password ): void {
        $this->option( CURLOPT_USERPWD, "{$username}:{$password}" );
    }

    public function send( ): mixed {
        $this->setCurlHeaders( );
        $body = curl_exec( $this->curlHandle );
        $statusCode = (int) curl_getinfo( $this->curlHandle, CURLINFO_RESPONSE_CODE );
        $errorNumber = curl_errno( $this->curlHandle );
        $errorMessage = curl_error( $this->curlHandle );
        $this->closeDownloadHandle( );

        if ( $errorNumber !== 0 ) {
            new Logger( )->error( "cURL request failed", [
                "httpStatus" => $statusCode,
                "errorNumber" => $errorNumber,
                "error" => $errorMessage,
            ] );
        }

        if ( !$this->returnsResponse ) {
            return $body;
        }

        return new CurlResponse(
            is_string( $body ) ? $body : "",
            $statusCode,
            $errorNumber,
            $errorMessage,
        );
    }

    // PRIVATE FUNCTIONS
    private static function responseRequest( self $request ): self {
        $request->returnsResponse = true;
        return $request;
    }

    private function option( int $option, mixed $value ): void { curl_setopt( $this->curlHandle, $option, $value ); }

    private function formatHeaders( array $headers ): array {
        $lines = [ ];
        foreach ( $headers as $name => $value ) {
            $lines[ ] = is_int( $name ) ? (string) $value : $name . ": " . $value;
        }
        return $lines;
    }

    private function closeDownloadHandle( ): void {
        if ( is_resource( $this->downloadHandle ) ) {
            fclose( $this->downloadHandle );
        }
        $this->downloadHandle = null;
    }
}
