<?php

class CurlController {
    private const DEFAULT_USER_AGENT = "XogoriaCurl/1.0";

    private CurlHandle $curlHandle;
    private mixed $downloadHandle = null;
    private array $headers = [ ];
    private int $lastHttpStatus = 0;
    private int $lastErrorNumber = 0;
    private string $lastErrorMessage = "";

    // MAGIC FUNCTIONS
    public function __construct( string $url, string $panelKey, ?array $data = null ) {
        $this->curlHandle = curl_init( $url );

        if ( !$this->curlHandle ) {
            new Logger( )->error( "Unable to initialize cURL", [ "url" => $url ] );
        }

        $this->setReturnTransfer( );
        $this->setTimeout( 20 );
        $this->setUserAgent( self::DEFAULT_USER_AGENT );

        if ( $data !== null ) {
            $this->setToPost( );
            $this->setAccept( "application/json" );
            $this->setContentType( "application/json" );
            $this->setAuthorization( "Bearer " . $panelKey );
            $this->setPostData( json_encode( $data, JSON_UNESCAPED_UNICODE ) );
            $this->setUserAgent( "curl/8.0 ( +php cURL relay )" );
        }

        $this->setCurlHeaders( );
    }

    // PUBLIC FUNCTIONS
    public function setToPost( )   : void { $this->option( CURLOPT_POST, true ); }
    public function setBasicAuth( ): void { $this->option( CURLOPT_HTTPAUTH, CURLAUTH_BASIC ); }
    public function setCurlHeaders( ) { $this->option( CURLOPT_HTTPHEADER, $this->formatHeaders( $this->headers ) ); }

    // HEADERS
    public function setAccept( string $value )       : void { $this->headers[ "Accept" ] = $value; }
    public function setContentType( string $value )  : void { $this->headers[ "Content-Type" ] = $value; }
    public function setAuthorization( string $value ): void { $this->headers[ "Authorization" ] = $value; }
    public function setClientId( string $value )     : void { $this->headers[ "Client-ID" ] = $value; }
    // OPTIONS
    public function setTimeout( int $seconds )        : void { $this->option( CURLOPT_TIMEOUT, $seconds ); }
    public function setUserAgent( string $value )     : void { $this->option( CURLOPT_USERAGENT, $value ); }
    public function setOutputFile( mixed $fileHandle ): void { $this->option( CURLOPT_FILE, $fileHandle ); }
    public function setPostData( string $data )       : void { $this->option( CURLOPT_POSTFIELDS, $data ); }
    // SPECIFIC SETTERS
    public function setBearerToken( string $token ): void { $this->setAuthorization( "Bearer " . $token ); }

    public function setReturnTransfer( bool $value = true ): void {
        $this->option( CURLOPT_RETURNTRANSFER, $value );
    }

    public function setBasicCreds( string $username, string $password ): void {
        $this->option( CURLOPT_USERPWD, "{$username}:{$password}" );
    }

    public function send( ): mixed {
        $returnValue = curl_exec( $this->curlHandle );
        $this->lastHttpStatus = (int) curl_getinfo( $this->curlHandle, CURLINFO_RESPONSE_CODE );
        $this->lastErrorNumber = curl_errno( $this->curlHandle );
        $this->lastErrorMessage = curl_error( $this->curlHandle );

        if ( $this->lastErrorNumber !== 0 ) {
            new Logger( )->error( "cURL request failed", [
                "http_status" => $this->lastHttpStatus,
                "error_number" => $this->lastErrorNumber,
                "error" => $this->lastErrorMessage,
            ] );
        }

        $this->closeDownloadHandle( );
        return $returnValue;
    }

    public function getLastResponseInfo( ): array {
        return [
            "httpStatus" => $this->lastHttpStatus,
            "errorNumber" => $this->lastErrorNumber,
            "errorMessage" => $this->lastErrorMessage,
        ];
    }

    public static function postForm( string $url, string $panelApiKey, array $data ): self {
        $newRequest = new self( $url, $panelApiKey );
        $newRequest->setToPost( );
        $newRequest->setContentType( "application/x-www-form-urlencoded" );
        $newRequest->setPostData( http_build_query( $data ) );
        return $newRequest;
    }

    // PRIVATE FUNCTIONS
    private function option( int $option, mixed $value ) {
        curl_setopt( $this->curlHandle, $option, $value );
    }

    private function formatHeaders( array $headers ): array {
        $lines = [ ];

        foreach ( $headers as $name => $value ) {
            if ( is_int( $name ) ) {
                $lines[ ] = (string) $value;
                continue;
            }

            $lines[ ] = $name . ": " . $value;
        }

        return $lines;
    }

    private function closeDownloadHandle( ): void {
        if ( is_resource( $this->downloadHandle ) ) {
            fclose( $this->downloadHandle );
            $this->downloadHandle = null;
        }
    }
 }
