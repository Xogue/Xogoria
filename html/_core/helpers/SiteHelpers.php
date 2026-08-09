<?php

function ensurePath( string $path ) {
    if ( !is_dir( $path ) && !is_file( $path ) ) {
        createPath( $path );
    }
}

function createPath( string $path ): void {
    if ( str_ends_with( $path, "/" ) ) {
        if ( !is_dir( $path ) ) {
            if ( !@mkdir( $path, 0775, true ) && !is_dir( $path ) ) {
                throw new \RuntimeException( sprintf( 'Directory "%s" was not created', $path ) );
            }
        }
    } else {
        $dirName = dirname( $path );
        if ( !is_dir( $dirName ) ) {
            if ( !@mkdir( $dirName, 0775, true ) && !is_dir( $dirName ) ) {
                throw new \RuntimeException( sprintf( 'Directory "%s" was not created', $dirName ) );
            }
        }

        if ( !is_file( $path ) ) {
            $bytes = file_put_contents( $path, "" );
            if ( $bytes === false ) {
                throw new \RuntimeException( sprintf( 'File "%s" could not be created', $path ) );
            }

            @chmod( $path, 0644 );
        }
    }
}

function safeLoadJson( string $filePath ): ?array {
    $logger = new Logger( );

    if ( !is_readable( $filePath ) ) {
        $logger->error( "Config file not readable", [ "path" => $filePath ] );
        throw new \RuntimeException( "Config file not readable: " . $filePath );
    }

    $fileContents = file_get_contents( $filePath );
    if ( !$fileContents ) {
        $logger->error( "Config file cannot be read", [ "path" => $filePath ] );
        throw new \RuntimeException( "Config file can't be read: " . $filePath );
    }

    $data = json_decode( $fileContents, true );
    if ( !$data ) {
        $logger->error( "Config file cannot be decoded", [
            "path" => $filePath,
            "json_error" => json_last_error_msg( ),
        ] );

        throw new \RuntimeException( "Config file can't be decoded: " . $filePath );
    }

    return $data;
}

function safeWriteJson( string $filePath, array $data ): bool {
    $logger = new Logger( );
    $jsonData = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    if ( $jsonData === false ) {
        $logger->error( "Failed to encode data as JSON", [ "json_error" => json_last_error_msg( ) ] );
        return false;
    }

    $lock = null;
    $lockPaths = [
        $filePath . ".lock",
        rtrim( sys_get_temp_dir( ), "/\\" ) .
            DIRECTORY_SEPARATOR .
            "xogoria-json-" . hash( "sha256", $filePath ) . ".lock",
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
        $logger->error( "Failed to lock JSON file", [ "path" => $filePath ] );
        return false;
    }

    $tempPath = @tempnam( dirname( $filePath ), basename( $filePath ) . ".tmp-" );
    try {
        if ( $tempPath === false ) {
            // Some deployments allow PHP to update an uploaded file but not
            // create siblings in its directory. The process lock still keeps
            // application writers serialized in this compatibility fallback.
            $bytes = @file_put_contents( $filePath, $jsonData, LOCK_EX );
            if ( $bytes === false ) {
                $logger->error( "Failed to write JSON data", [ "path" => $filePath ] );
                return false;
            }
            return true;
        }

        $bytes = @file_put_contents( $tempPath, $jsonData, LOCK_EX );
        if ( $bytes === false ) {
            $logger->error( "Failed to write JSON data", [ "path" => $tempPath ] );
            return false;
        }
        @chmod( $tempPath, 0644 );

        if ( !@rename( $tempPath, $filePath ) ) {
            $logger->error( "Failed to replace JSON file", [ "path" => $filePath ] );
            return false;
        }
        $tempPath = false;
        return true;
    } finally {
        if ( is_string( $tempPath ) && is_file( $tempPath ) ) {
            @unlink( $tempPath );
        }
        flock( $lock, LOCK_UN );
        fclose( $lock );
    }
}

function getCurrentPageName( ): string {
    $scriptName = $_SERVER[ "SCRIPT_NAME" ] ?? "";
    return basename( $scriptName, ".php" );
}

function forceRelativeUrl( string $url ): string {
    if ( preg_match( "#^https?://#i", $url ) ) {
        $parsed = parse_url( $url );
        $path = isset( $parsed[ "path" ] ) ? $parsed[ "path" ] : "/";
        $query = isset( $parsed[ "query" ] ) ? "?" . $parsed[ "query" ] : "";
        return $path . $query;
    }

    return $url;
}

function calculateScriptDir( ): string {
    $scriptDir = "/";
    if ( !empty( $_SERVER[ "SCRIPT_NAME" ] ) ) {
        $scriptDir = str_replace( "\\", "/", dirname( $_SERVER[ "SCRIPT_NAME" ] ) );
        $scriptDir = rtrim( $scriptDir, "/" );

        if ( $scriptDir === "" ) {
            $scriptDir = "/";
        }
    }
    return $scriptDir;
}

function formatTime( string $seconds ): string {
    $cooldown = "";
    if ( $seconds >= 3600 ) {
        $hrs = $seconds / 3600.0;
        $hrsStr = $seconds % 3600 === 0 ? (string) ( $seconds / 3600 ) : number_format( $hrs, 1 );
        $cooldown .= " (" . $hrsStr . "h)";
    } elseif ( $seconds > 60 ) {
        $mins = $seconds / 60.0;
        $minsStr = $seconds % 60 === 0 ? (string) ( $seconds / 60 ) : number_format( $mins, 1 );
        $cooldown .= " (" . $minsStr . "m)";
    } else {
        $cooldown .= "s";
    }
    return $cooldown;
}
