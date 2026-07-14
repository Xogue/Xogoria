<?php

class CurlController2 {
    private const DEFAULT_USER_AGENT = 'XogoriaCurl/1.0';

    private CurlHandle $curlHandle;
    private $downloadHandle = null;
    private array $headers  = [];

    public function __construct( string $url, array $privateData, ?array $data = null ) {
        $this->curlHandle = curl_init( $url );

        if ( !$this->curlHandle ) {
            throw new \Exception( "Failed to initialize cURL for URL: {$url}" );
        }

        $this->returnTransfer()
            ->timeout( 20 )
            ->userAgent( self::DEFAULT_USER_AGENT );

        if ( $data !== null ) {
            $this->method( 'POST' )
                ->jsonHeaders()
                ->headers( ['Authorization' => 'Bearer ' . $privateData['panelApiKey']] )
                ->preferIpv4()
                ->body( json_encode( $data, JSON_UNESCAPED_UNICODE ) )
                ->userAgent( 'curl/8.0 ( +php cURL relay )' );
        }
    }

    // HEADERS
    public function setAccept(string $value): void { $this->headers['Accept'] = $value; }
    public function setContentType(string $value): void { $this->headers['Content-Type'] = $value; }
    public function setAuthorization(string $value): void { $this->headers['Authorization'] = $value; }

    // OPTIONS
    public function setTimeout(int $seconds): void { $this->option( CURLOPT_TIMEOUT, $seconds ); }
    public function setUserAgent(string $value): void { $this->option( CURLOPT_USERAGENT, $value ); }
    public function setOutputFile(mixed $fileHandle): void { $this->option( CURLOPT_FILE, $fileHandle ); }
    public function setReturnTransfer(bool $value = true): void { $this->option( CURLOPT_RETURNTRANSFER, $value ); }
    public function setToPost(): void { $this->option( CURLOPT_POST, true ); }
    public function setPostData(string $data): void { $this->option( CURLOPT_POSTFIELDS, $data ); }
    public function setBasicAuth(): void { $this->option( CURLOPT_HTTPAUTH, CURLAUTH_BASIC ); }
    public function setBasicCreds(string $username, string $password): void { $this->option( CURLOPT_USERPWD, "{$username}:{$password}" ); }



    public function headers( array $headers ): self {
        foreach ( $headers as $name => $value ) { 
            $this->headers[$name] = $value; 
        }
        return $this;
    }

    public function setCurlHeaders(): self {
        return $this->option( CURLOPT_HTTPHEADER, $this->formatHeaders( $this->headers ) );
    }

    public function jsonHeaders(): self {
        return $this->headers( self::JSON_HEADERS );
    }

    public function acceptJson(): self {
        return $this->headers( ['Accept' => 'application/json'] );
    }

    public function bearerToken( string $token ): self {
        return $this->headers( ['Authorization' => 'Bearer ' . $token] );
    }

    public function authorization( string $value ): self {
        return $this->headers( ['Authorization' => $value] );
    }

    public static function request( string $url ): self {
        return new self( $url, [], null );
    }

    public static function get( string $url ): self {
        return self::request( $url );
    }

    public static function postJson( string $url, array $data ): self {
        return self::request( $url )
            ->method( 'POST' )
            ->jsonHeaders()
            ->body( json_encode( $data, JSON_UNESCAPED_UNICODE ) );
    }

    public static function postForm( string $url, array $data ): self {
        return self::request( $url )
            ->method( 'POST' )
            ->headers( ['Content-Type' => 'application/x-www-form-urlencoded'] )
            ->body( http_build_query( $data ) );
    }

    public static function postRaw( string $url, string $body, string $contentType = '' ): self {
        $request = self::request( $url )
            ->method( 'POST' )
            ->body( $body );

        if ( $contentType !== '' ) {
            $request->headers( ['Content-Type' => $contentType] );
        }

        return $request;
    }

    public static function panelJsonPost( string $url, array $data, array $privateData ): self {
        $request = new self( $url, $privateData, $data );

        return $request
            ->headers( ['Authorization' => 'Bearer ' . $privateData['panelApiKey']] )
            ->preferIpv4()
            ->userAgent( 'curl/8.0 ( +php cURL relay )' );
    }

    public static function download( string $url, string $destinationPath ): self {
        $request = self::get( $url )->returnTransfer( false )->timeout( 30 );
        $handle  = fopen( $destinationPath, 'wb' );

        if ( $handle === false ) {
            throw new \RuntimeException( 'Failed to open destination file for writing: ' . $destinationPath );
        }

        $request->downloadHandle = $handle;
        return $request->option( CURLOPT_FILE, $handle );
    }

    public function method( string $method ): self {
        $method = strtoupper( $method );
        $this->option( CURLOPT_CUSTOMREQUEST, $method );

        if ( $method === 'POST' ) {
            $this->option( CURLOPT_POST, true );
        }

        return $this;
    }

    public function body( string $body ): self {
        return $this->option( CURLOPT_POSTFIELDS, $body );
    }

    

    public function basicAuth( string $username, string $password ): self {
        return $this
            ->option( CURLOPT_HTTPAUTH, CURLAUTH_BASIC )
            ->option( CURLOPT_USERPWD, $username . ':' . $password );
    }

    public function timeout( int $seconds ): self {
        return $this->option( CURLOPT_TIMEOUT, $seconds );
    }

    public function userAgent( string $userAgent ): self {
        return $this->option( CURLOPT_USERAGENT, $userAgent );
    }

    public function preferIpv4(): self {
        if ( defined( 'CURL_IPRESOLVE_V4' ) ) {
            $this->option( CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        }

        return $this;
    }

    public function returnTransfer( bool $enabled = true ): self {
        return $this->option( CURLOPT_RETURNTRANSFER, $enabled );
    }

    public function option( int $option, mixed $value ): self {
        curl_setopt( $this->curlHandle, $option, $value );
        return $this;
    }

    public function send(): CurlResponse {
        $body           = curl_exec( $this->curlHandle );
        $this->response = new CurlResponse(
            $body,
            (int) curl_getinfo( $this->curlHandle, CURLINFO_RESPONSE_CODE ),
            curl_errno( $this->curlHandle ),
            curl_error( $this->curlHandle )
        );

        $this->closeDownloadHandle();
        return $this->response;
    }

    public function execute(): void {
        $this->send();
    }

    public function getRequestInfo(): array {
        if ( $this->response === null ) {
            return [
                'response'     => false,
                'responseCode' => null,
                'errorNumber'  => 0,
                'error'        => '',
            ];
        }

        return $this->response->toArray();
    }

    private function formatHeaders( array $headers ): array {
        $lines = [];

        foreach ( $headers as $name => $value ) {
            if ( is_int( $name ) ) {
                $lines[] = (string) $value;
                continue;
            }

            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    private function closeDownloadHandle(): void {
        if ( is_resource( $this->downloadHandle ) ) {
            fclose( $this->downloadHandle );
            $this->downloadHandle = null;
        }
    }

    public function __destruct() {
        $this->closeDownloadHandle();
    }
}
