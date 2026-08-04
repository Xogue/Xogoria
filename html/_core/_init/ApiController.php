<?php

final class ApiController {
    private ContextManager $contexts;
    private Logger $logger;
    private ApiResponseNormalizer $responseNormalizer;

    // MAGIC FUNCTIONS
    public function __construct( private ServiceFactory $services ) {
        $this->contexts = $services->contextManager( );
        $this->logger = $services->logger( Logger::CHANNEL_API );
        $this->responseNormalizer = $services->apiResponseNormalizer( );
    }

    // PUBLIC FUNCTIONS
    public function process( ): WorkerResult {
        try {
            $input = $this->contexts->getInputData( true );
            $request = $input->getRequest( );
            if ( $request === "" ) {
                return WorkerResult::failureCode( ResponseLibrary::R_API__MISSING_REQUEST );
            }

            if ( $input->usesExternalIdentity( ) ) {
                $expectedKey = $this->contexts->getPrivateApi( )->getWebApiKey( );
                $providedKey = $input->getUrlApiKey( );
                if (
                    $expectedKey === "" ||
                    $providedKey === "" ||
                    !hash_equals( $expectedKey, $providedKey )
                ) {
                    $this->logger->warning( "External API identity failed authentication", [
                        "request" => $request,
                    ] );

                    return WorkerResult::failureCode( ResponseLibrary::R_API__UNAUTHORIZED, [ ], 401 );
                }
            }

            $this->contexts->refreshIdentity( $input );
            if (
                in_array( $request, [ "currency", "interaction" ], true ) &&
                !$this->contexts->userLoggedIn( )
            ) {
                $this->services->userController( )->ensureTwitchUser( );
            }
            $worker = $this->services->createWorker( $request );
            if ( $worker === null ) {
                $this->logger->warning( "Unknown API request", [ "request" => $request ] );
                return WorkerResult::failureCode( ResponseLibrary::R_API__INVALID_REQUEST, [
                    "requestName" => $request,
                ] );
            }

            $worker->prime( $this->contexts->getWorker( ), $input );
            $result = $worker->process( );

            if ( $request === "interaction" && $this->adminDebugRequested( ) ) {
                $profile = $this->contexts->getWorker( )->getProfile( );
                $result->addMeta( "relay", [
                    "profile" => $profile->getName( ),
                    "profileLabel" => $profile->getLabel( ),
                    "serverId" => $profile->getServerId( ),
                    "commands" => $this->services->interactionManager( )->getLastCommandResults( ),
                ] );
            }

            $this->logger->info( "API request processed", [
                "request" => $request,
                "action" => $input->getAction( ),
                "success" => $result->isSuccess( ),
            ] );

            return $result;
        } catch ( Throwable $error ) {
            $this->logger->exception( $error );
            $result = WorkerResult::failureCode( ResponseLibrary::R_API__INTERNAL_ERROR, [ ], 500 );
            if ( $this->adminDebugRequested( ) ) {
                $result->addMeta( "debug", [
                    "exception" => $error::class,
                    "message" => $error->getMessage( ),
                    "file" => $error->getFile( ),
                    "line" => $error->getLine( ),
                    "trace" => explode( PHP_EOL, $error->getTraceAsString( ) ),
                ] );
            }
            return $result;
        }
    }

    public function respond( ?WorkerResult $result = null ): never {
        $result ??= $this->process( );
        $input = $this->contexts->getInputData( true );
        $status = $this->responseNormalizer->getHttpStatus( $result );
        $response = $this->responseNormalizer->normalize(
            $result,
            $input->getRequest( ),
            $input->getType( ),
            $input->getAction( ),
        );
        if ( !headers_sent( ) ) {
            http_response_code( $status );
            header( "Content-Type: application/json; charset=utf-8" );
        }
        echo json_encode(
            $response,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?: '{"success":false,"code":"json_error","message":"The response could not be encoded.","value":false,"request":{"name":"","type":"","action":""},"meta":{}}';
        exit( );
    }

    public static function error( string $message, int $status = 400 ): never {
        self::sendJson( [ "success" => false, "message" => $message ], $status );
    }

    public static function authFailed( string $message = "Not authorized" ): never {
        self::error( $message, 403 );
    }

    public static function sendJson( array $data, int $status = 200 ): never {
        if ( !headers_sent( ) ) {
            http_response_code( $status );
            header( "Content-Type: application/json; charset=utf-8" );
        }

        echo json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        exit( );
    }

    private function adminDebugRequested( ): bool {
        if ( ( $_SERVER[ "HTTP_X_INTERACT_DEBUG" ] ?? "" ) !== "1" ) {
            return false;
        }

        try {
            return $this->contexts->getUser( )->isAdmin( );
        } catch ( Throwable ) {
            return false;
        }
    }
}
