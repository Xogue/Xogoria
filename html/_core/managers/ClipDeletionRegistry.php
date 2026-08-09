<?php

final class ClipDeletionRegistry {
    private const PATH = XOG_ROOT . "/_core/_init/config/clipDeletionQueue.json";

    // MAGIC FUNCTIONS
    public function __construct( private Logger $logger ) { }

    // PUBLIC FUNCTIONS
    public function all( ): array {
        if ( !is_file( self::PATH ) ) {
            return [ ];
        }
        $data = json_decode( (string) file_get_contents( self::PATH ), true );
        return is_array( $data ) ? $data : [ ];
    }

    public function record(
        string $clipId,
        string $twitchUrl,
        ?string $backblazeUrl,
        string $requestedBy,
    ): array {
        $records = $this->all( );
        $record = [
            "clipId" => $clipId,
            "requestedAt" => date( DATE_ATOM ),
            "requestedBy" => $requestedBy,
            "twitchUrl" => $twitchUrl,
            "backblazeUrl" => $backblazeUrl,
            "twitchDelete" => "manual_required",
            "backblazeDelete" => $backblazeUrl ? "manual_required" : "not_stored",
        ];
        $records[ $clipId ] = $record;
        if ( !safeWriteJson( self::PATH, $records ) ) {
            throw new RuntimeException( "Unable to save clip deletion record" );
        }

        $this->logger->warning( "Clip deletion requested", [
            "clipId" => $clipId,
            "requestedBy" => $requestedBy,
        ] );

        return $record;
    }

    public function remove( string $clipId ): bool {
        $records = $this->all( );
        if ( !array_key_exists( $clipId, $records ) ) {
            return true;
        }
        unset( $records[ $clipId ] );
        return safeWriteJson( self::PATH, $records );
    }
}
