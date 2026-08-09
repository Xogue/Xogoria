<?php

final class ClipManager {
    private const TABLE = "streamClips";

    // MAGIC FUNCTIONS
    public function __construct( private MySqlManager $database, private Logger $logger ) { }

    // PUBLIC FUNCTIONS
    public function getClipInfoFromIds( array $clipIds ): array {
        $clipIds = array_values( array_filter( array_map( "strval", $clipIds ), "strlen" ) );
        if ( $clipIds === [ ] ) {
            return [ ];
        }
        $placeholders = implode( ", ", array_fill( 0, count( $clipIds ), "?" ) );

        return $this->indexRows(
            $this->database->selectFromJson(
                "SELECT * FROM " . self::TABLE . " WHERE twitchString IN ({$placeholders})",
                str_repeat( "s", count( $clipIds ) ),
                $clipIds,
            ),
        );
    }

    public function getAllClipInfo( ): array {
        return $this->indexRows(
            $this->database->selectFromJson(
                "SELECT * FROM " . self::TABLE . " ORDER BY seen DESC, id ASC",
                "",
                [ ],
            ),
        );
    }

    public function getAllReviewedClipInfo( ): array {
        return $this->indexRows(
            $this->database->selectFromJson(
                "SELECT * FROM " . self::TABLE . " WHERE status = ?",
                "i",
                [ 1 ],
            ),
        );
    }

    public function approve( string $clipId, array $twitch, string $localUrl ): bool {
        $sql =
            "INSERT INTO " .
            self::TABLE .
            ' (twitchString, enabled, status, seen, twitchTitle, twitchCreator, twitchViewCount, twitchCreated, twitchThumbnailUrl, twitchDuration, locaUrl)
            VALUES (?, 1, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = 1, status = 1, seen = COALESCE(seen, NOW()),
            twitchTitle = VALUES(twitchTitle), twitchCreator = VALUES(twitchCreator), twitchViewCount = VALUES(twitchViewCount),
            twitchCreated = VALUES(twitchCreated), twitchThumbnailUrl = VALUES(twitchThumbnailUrl), twitchDuration = VALUES(twitchDuration),
            locaUrl = VALUES(locaUrl)';

        return $this->database->execute( $sql, "sssissds", [
            $clipId,
            (string) ( $twitch[ "title" ] ?? "" ),
            (string) ( $twitch[ "creatorName" ] ?? "" ),
            (int) ( $twitch[ "viewCount" ] ?? 0 ),
            $this->mysqlDateTime( (string) ( $twitch[ "createdAt" ] ?? "" ) ),
            (string) ( $twitch[ "thumbnailUrl" ] ?? "" ),
            (float) ( $twitch[ "duration" ] ?? 0 ),
            $localUrl,
        ] );
    }

    public function setReviewStatus( string $clipId, int $status ): bool {
        $sql =
            "INSERT INTO " .
            self::TABLE .
            ' (twitchString, enabled, status, seen)
            VALUES (?, 0, ?, NOW()) ON DUPLICATE KEY UPDATE enabled = 0, status = VALUES(status), seen = COALESCE(seen, NOW())';
        return $this->database->execute( $sql, "si", [ $clipId, $status ] );
    }

    public function saveMetadata( string $clipId, array $input ): bool {
        return $this->update( $clipId, [
            "customTitle" => trim( (string) ( $input[ "customTitle" ] ?? "" ) ) ?: null,
            "favorite" => !empty( $input[ "favorite" ] ) ? 1 : 0,
            "enabled" => !empty( $input[ "enabled" ] ) ? 1 : 0,
            "maxDuration" => max( 0, (float) ( $input[ "maxDuration" ] ?? 0 ) ),
            "startOffset" => max( 0, (float) ( $input[ "startOffset" ] ?? 0 ) ),
            "playCount" => max( 0, (int) ( $input[ "playCount" ] ?? 0 ) ),
        ] );
    }

    public function increasePlayCount( string $clipId ): bool {
        return $this->database->execute(
            "UPDATE " . self::TABLE . " SET playCount = playCount + 1 WHERE twitchString = ?",
            "s",
            [ $clipId ],
        );
    }

    public function setLocalFileUrl( string $clipId, string $url ): bool {
        // The deployed column is intentionally mapped here despite its historical typo.
        return $this->update( $clipId, [ "locaUrl" => $url !== "" ? $url : null ] );
    }

    public function setAudioNormalized( string $clipId, bool $normalized ): bool {
        return $this->update( $clipId, [ "normalized" => $normalized ? 1 : 0 ] );
    }

    public function setNormalizedLocalFile( string $clipId, string $url ): bool {
        return $this->update( $clipId, [
            "locaUrl" => $url,
            "normalized" => 1,
        ] );
    }

    // PRIVATE FUNCTIONS
    private function update( string $clipId, array $fields ): bool {
        $success = $this->database->update( self::TABLE, $fields, "twitchString = ?", "s", [ $clipId ] );
        if ( !$success ) {
            $this->logger->error( "Clip metadata update failed", [
                "clipId" => $clipId,
                "fields" => array_keys( $fields ),
            ] );
        }
        return $success;
    }

    private function indexRows( array $rows ): array {
        $indexed = [ ];
        foreach ( $rows as $row ) {
            $clipId = (string) ( $row[ "twitchString" ] ?? "" );
            if ( $clipId !== "" ) {
                $indexed[ $clipId ] = $this->normalize( $row );
            }
        }
        return $indexed;
    }

    private function normalize( array $row ): array {
        $localUrl = trim( (string) ( $row[ "locaUrl" ] ?? "" ) );
        return [
            "id" => (string) ( $row[ "twitchString" ] ?? "" ),
            "customTitle" => $row[ "customTitle" ] ?? null,
            "favorite" => !empty( $row[ "favorite" ] ),
            "enabled" => !empty( $row[ "enabled" ] ),
            "playCount" => (int) ( $row[ "playCount" ] ?? 0 ),
            "maxDuration" => (float) ( $row[ "maxDuration" ] ?? 0 ),
            "startOffset" => (float) ( $row[ "startOffset" ] ?? 0 ),
            "reviewStatus" => (int) ( $row[ "status" ] ?? 0 ),
            "localUrl" => $localUrl !== "" ? $localUrl : null,
            "audioNormalized" => !empty( $row[ "normalized" ] ),
            "title" => (string) ( $row[ "twitchTitle" ] ?? "" ),
            "creatorName" => (string) ( $row[ "twitchCreator" ] ?? "" ),
            "viewCount" => (int) ( $row[ "twitchViewCount" ] ?? 0 ),
            "createdAt" => (string) ( $row[ "twitchCreated" ] ?? "" ),
            "thumbnailUrl" => (string) ( $row[ "twitchThumbnailUrl" ] ?? "" ),
            "duration" => (float) ( $row[ "twitchDuration" ] ?? 0 ),
        ];
    }

    private function mysqlDateTime( string $value ): ?string {
        $timestamp = strtotime( $value );
        return $timestamp === false ? null : gmdate( "Y-m-d H:i:s", $timestamp );
    }
}
