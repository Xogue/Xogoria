<?php

function ensurePath( string $path ) {
    if ( !is_dir( $path ) && !is_file( $path ) ) {
        createPath( $path );
    }
}

function createPath( string $path ): void {
    if ( str_ends_with( $path, '/' ) ) {
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
            $bytes = file_put_contents( $path, '' );
            if ( $bytes === false ) {
                throw new \RuntimeException( sprintf( 'File "%s" could not be created', $path ) );
            }

            @chmod( $path, 0644 );
        }
    }
}

function safeLoadJson(string $filePath): ?array {
    $logger = new Logger();

    if ( !is_readable( $filePath ) ) {
        $logger->error('Config file not readable', ['path' => $filePath]);
        throw new \RuntimeException( 'Config file not readable: ' . $filePath );
    }

    $fileContents = file_get_contents( $filePath );
    if ( !$fileContents ) {
        $logger->error('Config file cannot be read', ['path' => $filePath]);
        throw new \RuntimeException( "Config file can't be read: " . $filePath );
    }

    $data = json_decode( $fileContents, true );
    if ( !$data ) {
        $logger->error('Config file cannot be decoded', ['path' => $filePath, 'json_error' => json_last_error_msg()]);
        throw new \RuntimeException( "Config file can't be decoded: " . $filePath );
    }

    return $data;
}

function safeWriteJson(string $filePath, array $data): bool {
    $logger = new Logger();
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonData === false) {
        $logger->error('Failed to encode data as JSON', ['json_error' => json_last_error_msg()]);
        return false;
    }

    $tempPath = $filePath . '.tmp';
    $bytes = file_put_contents($tempPath, $jsonData, LOCK_EX);
    if ($bytes === false) {
        $logger->error('Failed to write JSON data', ['path' => $tempPath]);
        return false;
    }

    if (!rename($tempPath, $filePath)) {
        $logger->error('Failed to replace JSON file', ['path' => $filePath]);
        return false;
    }
    return true;
}

function getCurrentPageName(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return basename( $scriptName, '.php' );
}

function forceRelativeUrl( string $url ): string {
    if ( preg_match( '#^https?://#i', $url ) ) {
        $parsed = parse_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        $query  = isset( $parsed['query'] ) ? ( '?' . $parsed['query'] ) : '';
        return $path . $query;
    }

    return $url;
}

function calculateScriptDir(): string {
    $scriptDir = '/';
    if ( !empty( $_SERVER['SCRIPT_NAME'] ) ) {
        $scriptDir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) );
        $scriptDir = rtrim( $scriptDir, '/' );

        if ( $scriptDir === '' ) {
            $scriptDir = '/';
        }
    }
    return $scriptDir;
}

function formatTime( string $seconds ): string {
    $cooldown = '';
    if ( $seconds >= 3600 ) {
        $hrs       = $seconds / 3600.0;
        $hrsStr    = ( $seconds % 3600 === 0 ) ? (string) ( $seconds / 3600 ) : number_format( $hrs, 1 );
        $cooldown .= ' (' . $hrsStr . 'h)';
    } elseif ( $seconds > 60 ) {
        $mins      = $seconds / 60.0;
        $minsStr   = ( $seconds % 60 === 0 ) ? (string) ( $seconds / 60 ) : number_format( $mins, 1 );
        $cooldown .= ' (' . $minsStr . 'm)';
    } else { $cooldown .= 's';}
    return $cooldown;
}
