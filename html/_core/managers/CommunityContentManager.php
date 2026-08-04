<?php

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

    public function save( string $source ): bool {
        $source = str_replace( [ "\r\n", "\r" ], "\n", $source );
        if ( str_contains( $source, "\0" ) ) {
            throw new InvalidArgumentException( "Community content contains an invalid character." );
        }
        if ( strlen( $source ) > self::MAX_LENGTH ) {
            throw new InvalidArgumentException( "Community content must be smaller than 200 KB." );
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
    }

    private function backup( ): void {
        if (
            !is_dir( self::BACKUP_DIRECTORY ) &&
            !mkdir( self::BACKUP_DIRECTORY, 0775, true ) &&
            !is_dir( self::BACKUP_DIRECTORY )
        ) {
            throw new RuntimeException( "Unable to create the content backup directory." );
        }
        if ( is_file( self::CONTENT_PATH ) ) {
            $destination = self::BACKUP_DIRECTORY . "/community-" . date( "Ymd-His" ) . ".json";
            if ( !copy( self::CONTENT_PATH, $destination ) ) {
                throw new RuntimeException( "Unable to back up the community page." );
            }
        }
    }
}
