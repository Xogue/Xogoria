<?php

final class ClipManager {
    private const TABLE = "stream_clips";

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
                "SELECT * FROM " . self::TABLE . " WHERE clip_id IN ({$placeholders})",
                str_repeat( "s", count( $clipIds ) ),
                $clipIds,
            ),
        );
    }

    public function getAllClipInfo( ): array {
        return $this->indexRows(
            $this->database->selectFromJson(
                "SELECT * FROM " . self::TABLE . " ORDER BY admin_seen_at DESC, clip_id ASC",
                "",
                [ ],
            ),
        );
    }

    public function getAllReviewedClipInfo( ): array {
        return $this->indexRows(
            $this->database->selectFromJson(
                "SELECT * FROM " . self::TABLE . " WHERE review_status = ?",
                "i",
                [ 1 ],
            ),
        );
    }

    public function approve( string $clipId, array $twitch ): bool {
        $sql =
            "INSERT INTO " .
            self::TABLE .
            ' (clip_id, enabled, review_status, admin_seen, admin_seen_at, tw_title, tw_creator, tw_view_count, tw_created_at, tw_thumbnail_url, tw_duration)
            VALUES (?, 1, 1, 1, NOW(), ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = 1, review_status = 1, admin_seen = 1, admin_seen_at = COALESCE(admin_seen_at, NOW()),
            tw_title = VALUES(tw_title), tw_creator = VALUES(tw_creator), tw_view_count = VALUES(tw_view_count),
            tw_created_at = VALUES(tw_created_at), tw_thumbnail_url = VALUES(tw_thumbnail_url), tw_duration = VALUES(tw_duration)';

        return $this->database->execute( $sql, "sssissd", [
            $clipId,
            (string) ( $twitch[ "title" ] ?? "" ),
            (string) ( $twitch[ "creatorName" ] ?? "" ),
            (int) ( $twitch[ "viewCount" ] ?? 0 ),
            (string) ( $twitch[ "createdAt" ] ?? "" ),
            (string) ( $twitch[ "thumbnailUrl" ] ?? "" ),
            (float) ( $twitch[ "duration" ] ?? 0 ),
        ] );
    }

    public function setReviewStatus( string $clipId, int $status ): bool {
        $sql =
            "INSERT INTO " .
            self::TABLE .
            ' (clip_id, enabled, review_status, admin_seen, admin_seen_at)
            VALUES (?, 0, ?, 1, NOW()) ON DUPLICATE KEY UPDATE enabled = 0, review_status = VALUES(review_status), admin_seen = 1, admin_seen_at = COALESCE(admin_seen_at, NOW())';
        return $this->database->execute( $sql, "si", [ $clipId, $status ] );
    }

    public function saveMetadata( string $clipId, array $input ): bool {
        $fields = [
            "custom_title" => trim( (string) ( $input[ "customTitle" ] ?? "" ) ) ?: null,
            "is_favorite" => !empty( $input[ "favorite" ] ) ? 1 : 0,
            "enabled" => !empty( $input[ "enabled" ] ) ? 1 : 0,
            "max_duration" => max( 0, (float) ( $input[ "maxDuration" ] ?? 0 ) ),
            "start_offset" => max( 0, (float) ( $input[ "startOffset" ] ?? 0 ) ),
            "play_count" => max( 0, (int) ( $input[ "playCount" ] ?? 0 ) ),
        ];
        return $this->update( $clipId, $fields );
    }

    public function increasePlayCount( string $clipId ): bool {
        return $this->database->execute(
            "UPDATE " . self::TABLE . " SET play_count = play_count + 1 WHERE clip_id = ?",
            "s",
            [ $clipId ],
        );
    }

    public function setLocalFileUrl( string $clipId, string $url ): bool {
        return $this->update( $clipId, [
            "local_url" => $url,
            "has_local_file" => $url !== "" ? 1 : 0,
        ] );
    }

    public function setAudioNormalized( string $clipId, bool $normalized ): bool {
        return $this->update( $clipId, [ "audio_normalized" => $normalized ? 1 : 0 ] );
    }

    // PRIVATE FUNCTIONS
    private function update( string $clipId, array $fields ): bool {
        $success = $this->database->update( self::TABLE, $fields, "clip_id = ?", "s", [ $clipId ] );
        if ( !$success ) {
            $this->logger->error( "Clip metadata update failed", [
                "clip_id" => $clipId,
                "fields" => array_keys( $fields ),
            ] );
        }
        return $success;
    }

    private function indexRows( array $rows ): array {
        $indexed = [ ];
        foreach ( $rows as $row ) {
            $id = (string) ( $row[ "clip_id" ] ?? "" );
            if ( $id !== "" ) {
                $indexed[ $id ] = $this->normalize( $row );
            }
        }
        return $indexed;
    }

    private function normalize( array $row ): array {
        return [
            "id" => (string) ( $row[ "clip_id" ] ?? "" ),
            "customTitle" => $row[ "custom_title" ] ?? null,
            "favorite" => !empty( $row[ "is_favorite" ] ),
            "enabled" => !empty( $row[ "enabled" ] ),
            "playCount" => (int) ( $row[ "play_count" ] ?? 0 ),
            "maxDuration" => (float) ( $row[ "max_duration" ] ?? 0 ),
            "startOffset" => (float) ( $row[ "start_offset" ] ?? 0 ),
            "reviewStatus" => (int) ( $row[ "review_status" ] ?? 0 ),
            "localUrl" => $row[ "local_url" ] ?? null,
            "audioNormalized" => !empty( $row[ "audio_normalized" ] ),
            "title" => (string) ( $row[ "tw_title" ] ?? "" ),
            "creatorName" => (string) ( $row[ "tw_creator" ] ?? "" ),
            "viewCount" => (int) ( $row[ "tw_view_count" ] ?? 0 ),
            "createdAt" => (string) ( $row[ "tw_created_at" ] ?? "" ),
            "thumbnailUrl" => (string) ( $row[ "tw_thumbnail_url" ] ?? "" ),
            "duration" => (float) ( $row[ "tw_duration" ] ?? 0 ),
        ];
    }
}
