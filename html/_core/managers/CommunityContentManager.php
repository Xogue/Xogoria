<?php

final class CommunityContentConflictException extends RuntimeException { }
final class CommunityContentStorageException extends RuntimeException { }

final class CommunityContentManager {
    private const CONTENT_PATH = XOG_ROOT . "/_core/_init/config/community.json";
    private const BACKUP_DIRECTORY = XOG_ROOT . "/_core/_init/config/_backups";
    private const MAX_LENGTH = 200000;

    public function __construct( private JsonHandler $json, private Logger $logger ) { }

    public function source( ): string {
        $data = $this->json->safeLoad( self::CONTENT_PATH );
        return is_string( $data[ "markdown" ] ?? null ) ? $data[ "markdown" ] : "";
    }

    public function render( ?string $source = null ): string {
        return ( new CommunityMarkdownRenderer( ) )->render( $source ?? $this->source( ) );
    }

    public function revision( ?string $source = null ): string {
        return hash( "sha256", $source ?? $this->source( ) );
    }

    public function save( string $source, ?string $expectedRevision = null ): bool {
        $source = str_replace( [ "\r\n", "\r" ], "\n", $source );
        if ( str_contains( $source, "\0" ) ) {
            throw new InvalidArgumentException( "Community content contains an invalid character." );
        }
        if ( strlen( $source ) > self::MAX_LENGTH ) {
            throw new InvalidArgumentException( "Community content must be smaller than 200 KB." );
        }

        $lock = null;
        $lockPaths = [
            XOG_ROOT . "/_core/_init/config/community.edit.lock",
            rtrim( sys_get_temp_dir( ), "/\\" ) .
                DIRECTORY_SEPARATOR .
                "xogoria-community-" . hash( "sha256", self::CONTENT_PATH ) . ".lock",
        ];
        foreach ( $lockPaths as $lockPath ) {
            $candidate = @fopen( $lockPath, "c" );
            if ( $candidate !== false && flock( $candidate, LOCK_EX ) ) {
                $lock = $candidate;
                break;
            }
            if ( is_resource( $candidate ) ) {
                fclose( $candidate );
            }
        }
        if ( !is_resource( $lock ) ) {
            throw new CommunityContentStorageException(
                "The server could not lock the community page. Check PHP temporary-directory permissions.",
            );
        }

        try {
            $currentSource = $this->source( );
            if (
                $expectedRevision !== null &&
                $expectedRevision !== "" &&
                !hash_equals( $this->revision( $currentSource ), $expectedRevision )
            ) {
                throw new CommunityContentConflictException(
                    "The community page changed after you opened it. Copy your changes, refresh, and merge them with the newer version.",
                );
            }

            $this->backup( );
            $saved = safeWriteJson( self::CONTENT_PATH, [
                "markdown" => $source,
                "updatedAt" => gmdate( "c" ),
            ] );
            if ( $saved ) {
                $this->logger->info( "Community page content updated" );
            }
            return $saved;
        } finally {
            flock( $lock, LOCK_UN );
            fclose( $lock );
        }
    }

    private function backup( ): void {
        if (
            !is_dir( self::BACKUP_DIRECTORY ) &&
            !mkdir( self::BACKUP_DIRECTORY, 0775, true ) &&
            !is_dir( self::BACKUP_DIRECTORY )
        ) {
            throw new CommunityContentStorageException(
                "The server could not create the community backup directory. Check its write permissions.",
            );
        }
        if ( is_file( self::CONTENT_PATH ) ) {
            $destination = self::BACKUP_DIRECTORY . "/community-" . ( new DateTimeImmutable( ) )->format( "Ymd-His-u" ) . ".json";
            if ( !copy( self::CONTENT_PATH, $destination ) ) {
                throw new CommunityContentStorageException(
                    "The server could not create a community-page backup. Check the backup directory's write permissions.",
                );
            }
        }
    }
}
