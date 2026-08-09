<?php

final class ClipReviewManager {
    // MAGIC FUNCTIONS
    public function __construct(
        private ClipManager $clips,
        private TwitchClipService $twitch,
        private ClipDeletionRegistry $deletions,
        private ClipAudioNormalizer $audioNormalizer,
        private BackblazeBridge $storage,
    ) { }

    // PUBLIC FUNCTIONS
    public function ignore( string $clipId ): bool { return $this->clips->setReviewStatus( $clipId, 2 ); }

    public function restore( string $clipId ): bool {
        return $this->clips->setReviewStatus( $clipId, 0 ) && $this->deletions->remove( $clipId );
    }

    public function catalog( int $limit = 50 ): array {
        $stored = $this->clips->getAllClipInfo( );
        $warning = null;
        try {
            $recent = $this->twitch->recent( $limit );
        } catch ( Throwable $error ) {
            $recent = [ ];
            $warning = $error->getMessage( );
        }

        $merged = $stored;
        foreach ( $recent as $clip ) {
            $id = $clip[ "id" ];

            $merged[ $id ] = array_merge(
                [
                    "customTitle" => null,
                    "favorite" => false,
                    "enabled" => false,
                    "playCount" => 0,
                    "maxDuration" => 0,
                    "startOffset" => 0,
                    "reviewStatus" => 0,
                    "localUrl" => null,
                    "audioNormalized" => false,
                ],
                $stored[ $id ] ?? [ ],
                $clip,
            );
        }
        return [
            "clips" => array_values( $merged ),
            "warning" => $warning,
        ];
    }

    public function list( ): array {
        return $this->catalog( 100 ) + [
            "deletions" => array_values( $this->deletions->all( ) ),
        ];
    }

    public function approve( string $clipId, array $clip ): array {
        $stored = $this->clips->getAllClipInfo( )[ $clipId ] ?? [ ];
        $localUrl = trim( (string) ( $stored[ "localUrl" ] ?? "" ) );
        if ( $localUrl !== "" ) {
            if ( !$this->clips->approve( $clipId, $clip, $localUrl ) ) {
                throw new RuntimeException( "The stored clip could not be published." );
            }
            return $this->finishApproval( $clipId, $clip, $localUrl, !empty( $stored[ "audioNormalized" ] ) );
        }

        $temporaryPath = tempnam( sys_get_temp_dir( ), "xogoria-clip-" );
        if ( $temporaryPath === false ) {
            throw new RuntimeException( "A temporary file could not be created for the clip." );
        }

        try {
            $this->twitch->downloadClip(
                $clipId,
                (string) ( $clip[ "thumbnailUrl" ] ?? "" ),
                $temporaryPath,
            );
            $localUrl = $this->storage->uploadClip( $clipId, $temporaryPath ) ?? "";
            if ( $localUrl === "" ) {
                throw new RuntimeException( "The clip could not be uploaded to Backblaze." );
            }
            if ( !$this->clips->approve( $clipId, $clip, $localUrl ) ) {
                throw new RuntimeException( "The clip was stored, but its published record could not be saved." );
            }
            return $this->finishApproval( $clipId, $clip, $localUrl, false );
        } finally {
            @unlink( $temporaryPath );
        }
    }

    public function save( string $clipId, array $data ): bool {
        return $this->clips->saveMetadata( $clipId, $data );
    }

    public function normalizeAudio( string $clipId ): array {
        return $this->audioNormalizer->normalize( $clipId );
    }

    public function requestDeletion( string $clipId, string $requestedBy ): array {
        $stored = $this->clips->getAllClipInfo( )[ $clipId ] ?? [ ];
        if ( !$this->clips->setReviewStatus( $clipId, 3 ) ) {
            throw new RuntimeException( "Unable to mark clip for deletion" );
        }

        return $this->deletions->record(
            $clipId,
            $this->twitch->twitchUrl( $clipId ),
            $stored[ "localUrl" ] ?? null,
            $requestedBy,
        );
    }

    private function approvedClip( array $clip, string $localUrl ): array {
        return array_merge( $clip, [
            "reviewStatus" => 1,
            "enabled" => true,
            "localUrl" => $localUrl,
        ] );
    }

    private function finishApproval(
        string $clipId,
        array $clip,
        string $localUrl,
        bool $alreadyNormalized,
    ): array {
        $approved = $this->approvedClip( $clip, $localUrl );
        if ( $alreadyNormalized ) {
            $approved[ "audioNormalized" ] = true;
            return [ "clip" => $approved ];
        }

        try {
            return [
                "clip" => array_merge( $approved, $this->audioNormalizer->normalize( $clipId ) ),
            ];
        } catch ( InvalidArgumentException | RuntimeException $error ) {
            return [
                "clip" => $approved,
                "normalizationWarning" => $error->getMessage( ),
            ];
        }
    }
}
