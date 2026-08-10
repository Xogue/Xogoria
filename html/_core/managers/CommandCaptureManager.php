<?php

final class CommandCaptureManager {
    private const MAX_CAPTURE_BYTES = 20 * 1024 * 1024;
    private const FILE_PATTERN = '/^(?:commands|streamerbot)-\d{8}-\d{6}-[a-f0-9]{8}\.json$/';
    private const API_LOG_PATH = XOG_ROOT . "/_core/_init/_logs/api.log";
    private string $directory;

    public function __construct( private Logger $logger ) {
        $this->directory = dirname( XOG_ROOT ) . "/storage/command-captures";
    }

    public function capture( string $raw, string $requestId = "" ): array {
        $bytes = strlen( $raw );
        if ( $bytes === 0 ) {
            throw new InvalidArgumentException( "The command capture body is empty." );
        }
        if ( $bytes > self::MAX_CAPTURE_BYTES ) {
            throw new InvalidArgumentException( "The command capture exceeds the 20 MB limit." );
        }

        $this->ensureDirectory( );
        $id = "streamerbot-" . date( "Ymd-His" ) . "-" . bin2hex( random_bytes( 4 ) ) . ".json";
        $path = $this->directory . "/" . $id;
        $temporary = $path . ".tmp";

        if ( file_put_contents( $temporary, $raw, LOCK_EX ) !== $bytes || !rename( $temporary, $path ) ) {
            @unlink( $temporary );
            throw new RuntimeException( "The command capture could not be stored." );
        }
        @chmod( $path, 0640 );

        $validJson = $this->isValidJson( $raw );
        $this->logger->info( "Streamer.bot inventory payload captured", [
            "requestId" => $requestId,
            "captureId" => $id,
            "bytes" => $bytes,
            "validJson" => $validJson,
        ] );

        return [
            "id" => $id,
            "bytes" => $bytes,
            "validJson" => $validJson,
        ];
    }

    public function files( int $limit = 100 ): array {
        if ( !is_dir( $this->directory ) ) {
            return [ ];
        }
        $paths = array_merge(
            glob( $this->directory . "/streamerbot-*.json" ) ?: [ ],
            glob( $this->directory . "/commands-*.json" ) ?: [ ],
        );
        rsort( $paths, SORT_STRING );
        $files = [ ];
        foreach ( array_slice( $paths, 0, max( 1, $limit ) ) as $path ) {
            $id = basename( $path );
            if ( !preg_match( self::FILE_PATTERN, $id ) || !is_file( $path ) ) {
                continue;
            }
            $files[ ] = [
                "id" => $id,
                "bytes" => (int) ( filesize( $path ) ?: 0 ),
                "capturedAt" => (int) ( filemtime( $path ) ?: 0 ),
            ];
        }
        return $files;
    }

    public function read( string $id ): array {
        if ( !preg_match( self::FILE_PATTERN, $id ) ) {
            throw new InvalidArgumentException( "Unknown command capture." );
        }
        $path = $this->directory . "/" . $id;
        if ( !is_file( $path ) ) {
            throw new InvalidArgumentException( "The command capture no longer exists." );
        }
        $raw = file_get_contents( $path );
        if ( $raw === false ) {
            throw new RuntimeException( "The command capture could not be read." );
        }
        return [
            "id" => $id,
            "raw" => $raw,
            "bytes" => strlen( $raw ),
            "capturedAt" => (int) ( filemtime( $path ) ?: 0 ),
            "validJson" => $this->isValidJson( $raw ),
        ];
    }

    public function recentAttempts( int $limit = 30 ): array {
        if ( !is_file( self::API_LOG_PATH ) ) {
            return [ ];
        }
        $handle = fopen( self::API_LOG_PATH, "rb" );
        if ( $handle === false ) {
            return [ ];
        }
        try {
            $size = (int) ( filesize( self::API_LOG_PATH ) ?: 0 );
            $offset = max( 0, $size - 1048576 );
            fseek( $handle, $offset );
            if ( $offset > 0 ) {
                fgets( $handle );
            }
            $source = stream_get_contents( $handle ) ?: "";
        } finally {
            fclose( $handle );
        }

        $attempts = [ ];
        $lines = array_reverse( preg_split( '/\R/', trim( $source ) ) ?: [ ] );
        foreach ( $lines as $line ) {
            $record = json_decode( $line, true );
            $message = (string) ( $record[ "message" ] ?? "" );
            if (
                !str_contains( strtolower( $message ), "command capture" ) &&
                !str_contains( strtolower( $message ), "traced api request" ) &&
                !str_contains( strtolower( $message ), "streamer.bot command payload" ) &&
                !str_contains( strtolower( $message ), "streamer.bot inventory payload" )
            ) {
                continue;
            }
            $attempts[ ] = $record;
            if ( count( $attempts ) >= max( 1, $limit ) ) {
                break;
            }
        }
        return $attempts;
    }

    private function ensureDirectory( ): void {
        if (
            !is_dir( $this->directory ) &&
            !mkdir( $this->directory, 0770, true ) &&
            !is_dir( $this->directory )
        ) {
            throw new RuntimeException( "The command capture directory could not be created." );
        }
    }

    private function isValidJson( string $raw ): bool {
        try {
            json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
            return true;
        } catch ( JsonException ) {
            return false;
        }
    }
}
