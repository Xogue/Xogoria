<?php

final class ApiResponseNormalizer {
    private const RESPONSE_CONFIG_PATH = XOG_ROOT . "/_core/_init/config/responseCodes.json";

    private array $definitions;

    public function __construct( JsonHandler $jsonHandler ) {
        $this->definitions = $jsonHandler->safeLoad( self::RESPONSE_CONFIG_PATH );
        if ( empty( $this->definitions ) ) {
            throw new RuntimeException( "Failed to load API response definitions." );
        }
    }

    public function normalize(
        WorkerResult $result,
        string $requestName = "",
        string $requestType = "",
        string $action = "",
    ): array {
        $definition = $this->definition( $result->getCode( ) );
        $success = isset( $definition[ "success" ] )
            ? (bool) $definition[ "success" ]
            : $result->isSuccess( );
        $messageTemplate = trim( (string) ( $definition[ "messageTemplate" ] ?? "" ) );
        $message = $messageTemplate !== ""
            ? $this->renderMessage( $messageTemplate, $definition[ "replaceMap" ] ?? [ ], $result->getMeta( ) )
            : $result->getMessage( );

        return [
            "success" => $success,
            "code" => $result->getCode( ) !== "" ? $result->getCode( ) : "unclassified_response",
            "message" => $message,
            "value" => $result->getValue( ),
            "request" => [
                "name" => $requestName,
                "type" => $requestType,
                "action" => $action,
            ],
            "meta" => (object) $result->getMeta( ),
        ];
    }

    public function getHttpStatus( WorkerResult $result ): int {
        $status = (int) ( $this->definition( $result->getCode( ) )[ "httpStatus" ] ?? 0 );
        return $status >= 100 && $status <= 599 ? $status : $result->getHttpStatus( );
    }

    private function definition( string $code ): array {
        $definition = $this->definitions[ $code ] ?? [ ];
        return is_array( $definition ) ? $definition : [ ];
    }

    private function renderMessage( string $template, mixed $replaceMap, array $meta ): string {
        if ( !is_array( $replaceMap ) ) {
            return $template;
        }

        $replacements = [ ];
        foreach ( $replaceMap as $placeholder => $metaKey ) {
            $value = $meta[ (string) $metaKey ] ?? "";
            $replacements[ (string) $placeholder ] = $this->stringify( $value );
        }
        return strtr( $template, $replacements );
    }

    private function stringify( mixed $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? "true" : "false";
        }
        if ( is_scalar( $value ) || $value === null ) {
            return (string) $value;
        }
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?: "";
    }
}
