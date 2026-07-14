<?php

class CurlController {
    private const DEFAULT_USER_AGENT = 'XogoriaCurl/1.0';

    private CurlHandle $curlHandle;
    private mixed $downloadHandle = null;
    private array $headers  = [];

    public function __construct( string $url, string $panelKey, ?array $data = null ) {
        $this->curlHandle = curl_init( $url );

        if ( !$this->curlHandle ) {
            (new Logger())->error('Unable to initialize cURL', ['url' => $url]);
        }

        $this->setReturnTransfer();
        $this->setTimeout( 20 );
        $this->setUserAgent( self::DEFAULT_USER_AGENT );

        if ( $data !== null ) {
            $this->setToPost();
            $this->setAccept( 'application/json' );
            $this->setContentType( 'application/json' );
            $this->setAuthorization( 'Bearer ' . $panelKey );
            $this->setPostData( json_encode( $data, JSON_UNESCAPED_UNICODE ) );
            $this->setUserAgent( 'curl/8.0 ( +php cURL relay )' );
        }

        $this->setCurlHeaders();
    }

    // HEADERS
    public function setAccept(string $value): void { $this->headers['Accept'] = $value; }
    public function setContentType(string $value): void { $this->headers['Content-Type'] = $value; }
    public function setAuthorization(string $value): void { $this->headers['Authorization'] = $value; }
    public function setClientId(string $value): void { $this->headers['Client-ID'] = $value; }

    // OPTIONS
    public function setTimeout(int $seconds): void { $this->option( CURLOPT_TIMEOUT, $seconds ); }
    public function setUserAgent(string $value): void { $this->option( CURLOPT_USERAGENT, $value ); }
    public function setOutputFile(mixed $fileHandle): void { $this->option( CURLOPT_FILE, $fileHandle ); }
    public function setReturnTransfer(bool $value = true): void { $this->option( CURLOPT_RETURNTRANSFER, $value ); }
    public function setToPost(): void { $this->option( CURLOPT_POST, true ); }
    public function setPostData(string $data): void { $this->option( CURLOPT_POSTFIELDS, $data ); }
    public function setBasicAuth(): void { $this->option( CURLOPT_HTTPAUTH, CURLAUTH_BASIC ); }
    public function setBasicCreds(string $username, string $password): void { $this->option( CURLOPT_USERPWD, "{$username}:{$password}" ); }
    public function setCurlHeaders() { $this->option( CURLOPT_HTTPHEADER, $this->formatHeaders( $this->headers ) ); }

    // SPECIFIC SETTERS
    public function setBearerToken(string $token): void { $this->setAuthorization( 'Bearer ' . $token ); }

    // PRIVATE FUNCTIONS
    private function option( int $option, mixed $value ) {
        curl_setopt( $this->curlHandle, $option, $value );
    }

    public function send(): mixed {
        $returnValue = curl_exec( $this->curlHandle );
        $returnCode = (int) curl_getinfo( $this->curlHandle, CURLINFO_RESPONSE_CODE );
        $errorNumber = curl_errno( $this->curlHandle );
        $errorMessage = curl_error( $this->curlHandle );

        if ( $errorNumber ) {
            (new Logger())->error('cURL request failed', [
                'http_status' => $returnCode,
                'error_number' => $errorNumber,
                'error' => $errorMessage,
            ]);
        }

        $this->closeDownloadHandle();
        return $returnValue;
    }

    public static function postForm( string $url, string $panelApiKey, array $data ): self {
        $newRequest = (new self( $url, $panelApiKey ));
        $newRequest->setToPost();
        $newRequest->setContentType('application/x-www-form-urlencoded');
        $newRequest->setPostData( http_build_query( $data ) );
        return $newRequest;
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

    // 

    // public static function postRaw( string $url, string $body, string $contentType = '' ): self {
    //     $request = self::request( $url )
    //         ->method( 'POST' )
    //         ->body( $body );

    //     if ( $contentType !== '' ) {
    //         $request->headers( ['Content-Type' => $contentType] );
    //     }

    //     return $request;
    // }

    // public static function panelJsonPost( string $url, array $data, array $privateData ): self {
    //     $request = new self( $url, $privateData, $data );

    //     return $request
    //         ->headers( ['Authorization' => 'Bearer ' . $privateData['panelApiKey']] )
    //         ->preferIpv4()
    //         ->userAgent( 'curl/8.0 ( +php cURL relay )' );
    // }

    // public static function download( string $url, string $destinationPath ): self {
    //     $request = self::get( $url )->returnTransfer( false )->timeout( 30 );
    //     $handle  = fopen( $destinationPath, 'wb' );

    //     if ( $handle === false ) {
    //         throw new \RuntimeException( 'Failed to open destination file for writing: ' . $destinationPath );
    //     }

    //     $request->downloadHandle = $handle;
    //     return $request->option( CURLOPT_FILE, $handle );
    // }

    // public function getRequestInfo(): array {
    //     if ( $this->response === null ) {
    //         return [
    //             'response'     => false,
    //             'responseCode' => null,
    //             'errorNumber'  => 0,
    //             'error'        => '',
    //         ];
    //     }

    //     return $this->response->toArray();
    // }

    

    

    // public function __destruct() {
    //     $this->closeDownloadHandle();
    // }
}
