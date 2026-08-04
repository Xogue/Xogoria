<?php

class WorkerResult {
    private bool $success = false;
    private mixed $value = null;
    private string $resultMessage = "";
    private string $code = "";
    private int $httpStatus = 200;
    private array $meta = [ ];

    // MAGIC FUNCTIONS
    public function __construct( mixed $value = null, string $message = "", string $code = "" ) {
        $this->value = $value;
        $this->resultMessage = $message;
        $this->code = $code;
        $this->interpretData( $value );
    }

    // PUBLIC FUNCTIONS
    public function isSuccess( )      : bool   { return $this->success; }
    public function getIntResult( )   : int    { return is_int( $this->value ) ? $this->value : -1; }
    public function getValue( )       : mixed  { return $this->value; }
    public function getMessage( )     : string { return $this->resultMessage; }
    public function getCode( )        : string { return $this->code; }
    public function getMeta( )        : array  { return $this->meta; }
    public function getHttpStatus( )  : int    { return $this->httpStatus; }
    public function getWorkerAsJson( ): string { return $this->toJson( ); }

    public static function failure(
        string $message,
        string $code = "request_failed",
        int $httpStatus = 400,
    ): self {
        $result = new self( false, $message, $code );
        $result->httpStatus = $httpStatus;
        return $result;
    }

    public static function success(
        string $code,
        mixed $value = true,
        array $meta = [ ],
    ): self {
        $result = new self( $value, "", $code );
        $result->success = true;
        foreach ( $meta as $key => $metaValue ) {
            $result->addMeta( (string) $key, $metaValue );
        }
        return $result;
    }

    public static function failureCode(
        string $code,
        array $meta = [ ],
        int $httpStatus = 400,
    ): self {
        $result = self::failure( "", $code, $httpStatus );
        foreach ( $meta as $key => $metaValue ) {
            $result->addMeta( (string) $key, $metaValue );
        }
        return $result;
    }

    public function addMeta( string $key, mixed $value ): void {
        $this->meta[ $key ] = $value;
    }

    public function toArray( ): array {
        return [
            "success" => $this->success,
            "value" => $this->value,
            "message" => $this->resultMessage,
            "code" => $this->code,
            "meta" => $this->meta,
        ];
    }

    public function toJson( ): string {
        return json_encode(
            $this->toArray( ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?:
            '{"success":false,"code":"json_error"}';
    }

    public function interpretData( mixed $data ): void {
        match ( true ) {
            is_int( $data ) => $this->processInt( $data ),
            is_bool( $data ) => $this->processBool( $data ),
            is_float( $data ) => $this->processFloat( $data ),
            is_string( $data ) => $this->processString( $data ),
            is_array( $data ) => $this->processArray( $data ),
            is_object( $data ) => $this->processObject( $data ),
            is_null( $data ) => $this->processNull( ),
            default => "unknown",
        };
    }

    // PRIVATE FUNCTIONS
    private function processNull( ): void { $this->success = false; }

    private function processBool( bool $data )    : void { $this->success = $data; }
    private function processFloat( float $data )  : void { $this->success = $data !== 0.0; }
    private function processString( string $data ): void { $this->success = $data !== ""; }
    private function processArray( array $data )  : void { $this->success = !empty( $data ); }
    private function processObject( object $data ): void { $this->success = !empty( (array) $data ); }

    private function processInt( int $data ): void {
        // balance ressult from CurrencyWorker
        $this->success = $data >= 0;
        if ( $this->success ) {
            $this->resultMessage = $this->resultMessage ?: "Your new total is {$data}.";
        }
    }
}
