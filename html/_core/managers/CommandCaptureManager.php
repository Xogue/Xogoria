<?php

final class CommandCaptureManager {
    private const MAX_CAPTURE_BYTES = 20 * 1024 * 1024;
    private const FILE_PATTERN = '/^(?:commands|streamerbot)-\d{8}-\d{6}-[a-f0-9]{8}\.json$/';
    private const API_LOG_PATH = XOG_ROOT . "/_core/_init/_logs/api.log";
    private const VERIFICATION_FILE = "command-action-verifications.json";
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

    public function preview( array $payload, array $websiteRows ): array {
        $commands = is_array( $payload[ "commands" ] ?? null ) ? $payload[ "commands" ] : [ ];
        $actions = is_array( $payload[ "actions" ] ?? null ) ? $payload[ "actions" ] : [ ];

        $commandsById = [ ];
        $aliasIndex = [ ];
        foreach ( $commands as $command ) {
            if ( !is_array( $command ) || trim( (string) ( $command[ "id" ] ?? "" ) ) === "" ) {
                continue;
            }
            $id = (string) $command[ "id" ];
            $commandsById[ $id ] = $command;
            foreach ( is_array( $command[ "commands" ] ?? null ) ? $command[ "commands" ] : [ ] as $alias ) {
                $key = $this->normalizeCommandName( (string) $alias );
                if ( $key !== "" ) {
                    $aliasIndex[ $key ][ $id ] = true;
                }
            }
        }

        $candidateMap = $this->buildNameCandidates( array_values( $commandsById ), $actions );
        $pendingVerifications = [ ];
        foreach ( $candidateMap as $candidate ) {
            if ( ( $candidate[ "mapping" ][ "status" ] ?? "" ) !== "needs_verification" ) {
                continue;
            }
            foreach ( $this->candidateActions( $candidate, $actions ) as $action ) {
                $pendingVerifications[ ] = [
                    "command" => $commandsById[ (string) $candidate[ "command_id" ] ] ?? [ ],
                    "action" => $action,
                    "method" => (string) ( $candidate[ "mapping" ][ "method" ] ?? "fuzzy_name_guess" ),
                    "score" => (float) ( $candidate[ "mapping" ][ "score" ] ?? 0 ),
                ];
            }
        }

        $matchedIds = [ ];
        $matched = [ ];
        $websiteOnly = [ ];
        $ambiguous = [ ];
        foreach ( $websiteRows as $row ) {
            $key = $this->normalizeCommandName( (string) ( $row[ "name" ] ?? "" ) );
            $ids = array_keys( $aliasIndex[ $key ] ?? [ ] );
            if ( count( $ids ) === 1 ) {
                $command = $commandsById[ $ids[ 0 ] ];
                $matchedIds[ $ids[ 0 ] ] = true;
                $websiteEnabled = (bool) ( $row[ "enabled" ] ?? false );
                $streamerbotEnabled = (bool) ( $command[ "enabled" ] ?? false );
                $matched[ ] = [
                    "website" => $row,
                    "streamerbot" => $command,
                    "actions" => $this->candidateActions(
                        $candidateMap[ $ids[ 0 ] ] ?? [ ],
                        $actions,
                    ),
                    "mappingStatus" => (string) ( $candidateMap[ $ids[ 0 ] ][ "mapping" ][ "status" ] ?? "unresolved" ),
                    "enabledChanged" => $websiteEnabled !== $streamerbotEnabled,
                ];
                continue;
            }
            if ( count( $ids ) > 1 ) {
                $ambiguous[ ] = [
                    "website" => $row,
                    "streamerbot" => array_values( array_intersect_key( $commandsById, array_flip( $ids ) ) ),
                ];
                continue;
            }
            $websiteOnly[ ] = $row;
        }

        $streamerbotOnly = [ ];
        foreach ( $commandsById as $id => $command ) {
            if ( isset( $matchedIds[ $id ] ) ) {
                continue;
            }
            $streamerbotOnly[ ] = [
                "streamerbot" => $command,
                "actions" => $this->candidateActions( $candidateMap[ $id ] ?? [ ], $actions ),
                "mappingStatus" => (string) ( $candidateMap[ $id ][ "mapping" ][ "status" ] ?? "unresolved" ),
            ];
        }

        $candidateCounts = array_map(
            static fn( array $candidate ): int => count( $candidate[ "action_ids" ] ?? [ ] ),
            $candidateMap,
        );
        $mappedActionIds = [ ];
        foreach ( $candidateMap as $candidate ) {
            foreach ( $candidate[ "action_ids" ] ?? [ ] as $actionId ) $mappedActionIds[ (string) $actionId ] = true;
        }
        return [
            "schemaVersion" => (int) ( $payload[ "schema_version" ] ?? 0 ),
            "generatedAt" => (string) ( $payload[ "generated_at" ] ?? "" ),
            "commandCount" => count( $commandsById ),
            "actionCount" => count( $actions ),
            "singleActionCount" => count( array_filter( $candidateCounts, static fn( int $count ): bool => $count === 1 ) ),
            "multipleActionCount" => count( array_filter( $candidateCounts, static fn( int $count ): bool => $count > 1 ) ),
            "unresolvedCount" => count( array_filter( $candidateCounts, static fn( int $count ): bool => $count === 0 ) ),
            "unmappedActionCount" => max( 0, count( $actions ) - count( $mappedActionIds ) ),
            "matched" => $matched,
            "websiteOnly" => $websiteOnly,
            "streamerbotOnly" => $streamerbotOnly,
            "ambiguous" => $ambiguous,
            "pendingVerifications" => $pendingVerifications,
        ];
    }

    public function recordVerification( string $commandId, string $actionId, bool $verified ): void {
        if ( !preg_match( '/^[a-f0-9-]{16,64}$/i', $commandId ) || !preg_match( '/^[a-f0-9-]{16,64}$/i', $actionId ) ) {
            throw new InvalidArgumentException( "Invalid command or action identifier." );
        }
        $this->ensureDirectory( );
        $state = $this->verificationState( );
        $state[ "decisions" ][ $commandId ][ $actionId ] = [
            "status" => $verified ? "verified" : "rejected",
            "updated_at_utc" => gmdate( "c" ),
        ];
        $this->writeVerificationState( $state );
    }

    public function pendingVerificationCount( ): int {
        $files = $this->files( 1 );
        if ( $files === [ ] ) return 0;
        $capture = $this->read( (string) $files[ 0 ][ "id" ] );
        if ( !$capture[ "validJson" ] ) return 0;
        $payload = json_decode( $capture[ "raw" ], true, 512, JSON_THROW_ON_ERROR );
        $commands = is_array( $payload[ "commands" ] ?? null ) ? $payload[ "commands" ] : [ ];
        $actions = is_array( $payload[ "actions" ] ?? null ) ? $payload[ "actions" ] : [ ];
        $count = 0;
        foreach ( $this->buildNameCandidates( $commands, $actions ) as $candidate ) {
            if ( ( $candidate[ "mapping" ][ "status" ] ?? "" ) === "needs_verification" ) {
                $count += count( $candidate[ "action_ids" ] ?? [ ] );
            }
        }
        return $count;
    }

    public function syncCommands( array $payload, AdminResourceManager $resources ): array {
        $commands = is_array( $payload[ "commands" ] ?? null ) ? $payload[ "commands" ] : [ ];
        $rows = $resources->list( "commands" );
        $state = $this->verificationState( );
        $rowLinks = is_array( $state[ "command_rows" ] ?? null ) ? $state[ "command_rows" ] : [ ];
        $rowsById = [ ];
        $rowsByName = [ ];
        foreach ( $rows as $row ) {
            $rowId = (string) ( $row[ "id" ] ?? "" );
            if ( $rowId !== "" ) $rowsById[ $rowId ] = $row;
            $nameKey = $this->normalizeCommandName( (string) ( $row[ "name" ] ?? "" ) );
            if ( $nameKey !== "" ) $rowsByName[ $nameKey ][ ] = $row;
        }

        $result = [ "added" => 0, "updated" => 0, "unchanged" => 0, "skipped" => 0 ];
        $newCommandIdsByName = [ ];
        foreach ( $commands as $command ) {
            if ( !is_array( $command ) ) {
                $result[ "skipped" ]++;
                continue;
            }
            $commandId = (string) ( $command[ "id" ] ?? "" );
            $aliases = is_array( $command[ "commands" ] ?? null ) ? $command[ "commands" ] : [ ];
            $primaryAlias = trim( (string) ( $aliases[ 0 ] ?? "" ) );
            if ( $commandId === "" || $primaryAlias === "" ) {
                $result[ "skipped" ]++;
                continue;
            }

            $existing = null;
            $linkedId = (string) ( $rowLinks[ $commandId ] ?? "" );
            if ( $linkedId !== "" && isset( $rowsById[ $linkedId ] ) ) {
                $existing = $rowsById[ $linkedId ];
            }
            if ( $existing === null ) {
                foreach ( $aliases as $alias ) {
                    $matches = $rowsByName[ $this->normalizeCommandName( (string) $alias ) ] ?? [ ];
                    if ( count( $matches ) === 1 ) {
                        $existing = $matches[ 0 ];
                        break;
                    }
                }
            }

            $enabled = !empty( $command[ "enabled" ] );
            if ( $existing !== null ) {
                $rowLinks[ $commandId ] = (string) $existing[ "id" ];
                if ( (bool) ( $existing[ "enabled" ] ?? false ) === $enabled ) {
                    $result[ "unchanged" ]++;
                    continue;
                }
                $existing[ "enabled" ] = $enabled;
                $existing[ "_originalKey" ] = $existing[ "id" ];
                if ( !$resources->save( "commands", $existing ) ) {
                    throw new RuntimeException( "A matched command could not be updated." );
                }
                $result[ "updated" ]++;
                continue;
            }

            $group = (string) ( $command[ "group" ] ?? "" );
            if ( !$resources->save( "commands", [
                "name" => $primaryAlias,
                "description" => "Streamer.bot command: " . (string) ( $command[ "name" ] ?? $primaryAlias ),
                "category" => $this->inferCategory( $group ),
                "perms" => $this->inferPermissions( $group ),
                "enabled" => $enabled,
            ] ) ) {
                throw new RuntimeException( "A new Streamer.bot command could not be added." );
            }
            $result[ "added" ]++;
            $newCommandIdsByName[ $this->normalizeCommandName( $primaryAlias ) ] = $commandId;
        }

        if ( $newCommandIdsByName !== [ ] ) {
            foreach ( $resources->list( "commands" ) as $row ) {
                $key = $this->normalizeCommandName( (string) ( $row[ "name" ] ?? "" ) );
                if ( isset( $newCommandIdsByName[ $key ] ) ) {
                    $rowLinks[ $newCommandIdsByName[ $key ] ] = (string) $row[ "id" ];
                }
            }
        }

        $state[ "command_rows" ] = $rowLinks;
        $state[ "last_sync_at_utc" ] = gmdate( "c" );
        $this->writeVerificationState( $state );
        return $result;
    }

    public function syncLatestCommands( AdminResourceManager $resources ): ?array {
        $this->ensureDirectory( );
        $lock = fopen( $this->directory . "/.command-sync.lock", "c" );
        if ( $lock === false ) throw new RuntimeException( "The command synchronization lock could not be opened." );
        try {
            if ( !flock( $lock, LOCK_EX ) ) throw new RuntimeException( "The command synchronization lock could not be acquired." );
            $files = $this->files( 1 );
            if ( $files === [ ] ) return null;
            $captureId = (string) $files[ 0 ][ "id" ];
            $state = $this->verificationState( );
            if ( ( $state[ "last_synced_capture_id" ] ?? "" ) === $captureId ) return null;

            $capture = $this->read( $captureId );
            if ( !$capture[ "validJson" ] ) throw new RuntimeException( "The latest command capture is invalid JSON." );
            $payload = json_decode( $capture[ "raw" ], true, 512, JSON_THROW_ON_ERROR );
            $result = $this->syncCommands( $payload, $resources );
            $state = $this->verificationState( );
            $state[ "last_synced_capture_id" ] = $captureId;
            $this->writeVerificationState( $state );
            return $result;
        } finally {
            flock( $lock, LOCK_UN );
            fclose( $lock );
        }
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

    private function normalizeCommandName( string $name ): string {
        $normalized = strtolower( trim( $name ) );
        return preg_replace( '/^!+/', '', $normalized ) ?? $normalized;
    }

    private function buildNameCandidates( array $commands, array $actions ): array {
        $state = $this->verificationState( );
        $decisions = is_array( $state[ "decisions" ] ?? null ) ? $state[ "decisions" ] : [ ];
        $result = [ ];

        foreach ( $commands as $command ) {
            $commandId = (string) ( $command[ "id" ] ?? "" );
            if ( $commandId === "" ) continue;

            $verifiedIds = [ ];
            $rejectedIds = [ ];
            foreach ( is_array( $decisions[ $commandId ] ?? null ) ? $decisions[ $commandId ] : [ ] as $actionId => $decision ) {
                $status = (string) ( $decision[ "status" ] ?? "" );
                if ( $status === "verified" ) $verifiedIds[ ] = (string) $actionId;
                if ( $status === "rejected" ) $rejectedIds[ (string) $actionId ] = true;
            }
            if ( $verifiedIds !== [ ] ) {
                $result[ $commandId ] = $this->candidateRecord(
                    $commandId,
                    $verifiedIds,
                    "verified",
                    "admin_verified",
                    1.0,
                );
                continue;
            }

            $commandParts = $this->nameParts( (string) ( $command[ "name" ] ?? "" ) );
            $sameWords = [ ];
            $fuzzy = [ ];
            foreach ( $actions as $action ) {
                if ( !is_array( $action ) ) continue;
                $actionId = (string) ( $action[ "id" ] ?? "" );
                if ( $actionId === "" || isset( $rejectedIds[ $actionId ] ) ) continue;
                $actionParts = $this->nameParts( (string) ( $action[ "name" ] ?? "" ) );
                $score = $this->nameSimilarity( $commandParts, $actionParts );
                $row = [ "id" => $actionId, "parts" => $actionParts, "score" => $score ];
                if ( $commandParts[ "words" ] !== [ ] && $commandParts[ "words" ] === $actionParts[ "words" ] ) {
                    $sameWords[ ] = $row;
                } else {
                    $fuzzy[ ] = $row;
                }
            }

            if ( $sameWords !== [ ] ) {
                usort( $sameWords, static fn( array $a, array $b ): int => $b[ "score" ] <=> $a[ "score" ] );
                $best = $sameWords[ 0 ];
                $extraMatches = $commandParts[ "extra" ] === $best[ "parts" ][ "extra" ];
                $result[ $commandId ] = $this->candidateRecord(
                    $commandId,
                    [ $best[ "id" ] ],
                    $extraMatches ? "inferred_words" : "needs_verification",
                    $extraMatches ? "same_words" : "same_words_different_characters",
                    $best[ "score" ],
                );
                continue;
            }

            usort( $fuzzy, static fn( array $a, array $b ): int => $b[ "score" ] <=> $a[ "score" ] );
            $best = $fuzzy[ 0 ] ?? null;
            $result[ $commandId ] = $best === null
                ? $this->candidateRecord( $commandId, [ ], "unresolved", "no_action_available", 0.0 )
                : $this->candidateRecord(
                    $commandId,
                    [ $best[ "id" ] ],
                    "needs_verification",
                    "fuzzy_name_guess",
                    $best[ "score" ],
                );
        }
        return $result;
    }

    private function candidateRecord(
        string $commandId,
        array $actionIds,
        string $status,
        string $method,
        float $score,
    ): array {
        return [
            "command_id" => $commandId,
            "action_ids" => array_values( $actionIds ),
            "mapping" => [
                "status" => $status,
                "method" => $method,
                "verified" => $status === "verified",
                "review_required" => $status === "needs_verification",
                "candidate_count" => count( $actionIds ),
                "score" => round( $score, 4 ),
            ],
        ];
    }

    private function nameParts( string $name ): array {
        $withoutGroups = preg_replace( '/\[[^\]]*\]|\([^)]*\)|\{[^}]*\}|<[^>]*>/u', ' ', $name ) ?? $name;
        preg_match_all( '/\p{L}+/u', strtolower( $withoutGroups ), $matches );
        $words = array_values( array_filter( $matches[ 0 ] ?? [ ], static fn( string $word ): bool => $word !== "" ) );
        sort( $words, SORT_STRING );
        $extra = preg_replace( '/[\p{L}\s]+/u', '', strtolower( $withoutGroups ) ) ?? "";
        return [
            "words" => $words,
            "extra" => $extra,
            "plain" => implode( " ", $words ) . "|" . $extra,
        ];
    }

    private function nameSimilarity( array $left, array $right ): float {
        $leftWords = array_values( array_unique( $left[ "words" ] ) );
        $rightWords = array_values( array_unique( $right[ "words" ] ) );
        $union = array_values( array_unique( array_merge( $leftWords, $rightWords ) ) );
        $wordScore = $union === [ ] ? 0.0 : count( array_intersect( $leftWords, $rightWords ) ) / count( $union );
        $leftPlain = (string) $left[ "plain" ];
        $rightPlain = (string) $right[ "plain" ];
        $length = max( strlen( $leftPlain ), strlen( $rightPlain ) );
        $textScore = $length === 0 ? 0.0 : 1.0 - min( 1.0, levenshtein( $leftPlain, $rightPlain ) / $length );
        return ( $wordScore * 0.75 ) + ( $textScore * 0.25 );
    }

    private function inferCategory( string $group ): string {
        $group = strtolower( $group );
        if ( preg_match( '/personal|game|custom|shortcut|prank|fun/', $group ) ) return "Playful";
        if ( preg_match( '/support|reward|community/', $group ) ) return "Supportive";
        if ( preg_match( '/utility|inform|currency|mod|xogue|point/', $group ) ) return "Utility";
        return "Other";
    }

    private function inferPermissions( string $group ): string {
        $group = strtolower( $group );
        if ( preg_match( '/mod|xogue|admin|owner/', $group ) ) return "Mods";
        if ( str_contains( $group, "vip" ) ) return "VIPs";
        return "Everyone";
    }

    private function verificationState( ): array {
        $path = $this->directory . "/" . self::VERIFICATION_FILE;
        if ( !is_file( $path ) ) return [ "version" => 1, "decisions" => [ ] ];
        $decoded = json_decode( (string) file_get_contents( $path ), true );
        return is_array( $decoded ) ? $decoded : [ "version" => 1, "decisions" => [ ] ];
    }

    private function writeVerificationState( array $state ): void {
        $path = $this->directory . "/" . self::VERIFICATION_FILE;
        $temporary = $path . "." . bin2hex( random_bytes( 4 ) ) . ".tmp";
        $json = json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . PHP_EOL;
        $written = file_put_contents( $temporary, $json, LOCK_EX ) === strlen( $json );
        $replaced = $written && rename( $temporary, $path );
        if ( !$replaced && $written && PHP_OS_FAMILY === "Windows" && is_file( $path ) ) {
            $replaced = unlink( $path ) && rename( $temporary, $path );
        }
        if ( !$written || !$replaced ) {
            @unlink( $temporary );
            throw new RuntimeException( "The command verification decision could not be stored." );
        }
        @chmod( $path, 0660 );
    }

    private function candidateActions( array $candidate, array $allActions ): array {
        $actions = $candidate[ "actions" ] ?? [ ];
        if ( is_array( $actions ) && $actions !== [ ] ) {
            return array_values( array_filter( $actions, "is_array" ) );
        }

        $ids = array_fill_keys(
            array_map( "strval", is_array( $candidate[ "action_ids" ] ?? null ) ? $candidate[ "action_ids" ] : [ ] ),
            true,
        );
        return array_values( array_filter(
            $allActions,
            static fn( mixed $action ): bool =>
                is_array( $action ) && isset( $ids[ (string) ( $action[ "id" ] ?? "" ) ] ),
        ) );
    }
}
