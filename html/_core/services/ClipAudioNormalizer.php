<?php

final class ClipAudioNormalizer {
    private const TARGET_LOUDNESS = -16.0;
    private const TARGET_TRUE_PEAK = -1.5;
    private const TARGET_RANGE = 11.0;

    public function __construct(
        private ClipManager $clips,
        private BackblazeBridge $storage,
        private Logger $logger,
    ) { }

    public function normalize( string $clipId ): array {
        $clip = $this->clips->getAllClipInfo( )[ $clipId ] ?? null;
        if ( $clip === null ) {
            throw new InvalidArgumentException( "The selected clip no longer exists." );
        }
        if ( !empty( $clip[ "audioNormalized" ] ) ) {
            return $clip;
        }

        $sourceUrl = trim( (string) ( $clip[ "localUrl" ] ?? "" ) );
        if ( !$this->isSupportedSourceUrl( $sourceUrl ) ) {
            throw new InvalidArgumentException( "This clip has no stored MP4 to normalize." );
        }
        if ( !function_exists( "proc_open" ) ) {
            throw new RuntimeException( "Audio processing is not available on this server." );
        }

        $lock = $this->acquireLock( $clipId );
        $workDirectory = $this->createWorkDirectory( );
        $sourcePath = $workDirectory . DIRECTORY_SEPARATOR . "source.mp4";
        $outputPath = $workDirectory . DIRECTORY_SEPARATOR . "normalized.mp4";

        try {
            $this->download( $sourceUrl, $sourcePath );
            $measurement = $this->measure( $sourcePath );
            $this->transcode( $sourcePath, $outputPath, $measurement );
            $this->verifyOutput( $outputPath );

            $normalizedUrl = $this->storage->uploadNormalizedClip( $clipId, $outputPath );
            if ( $normalizedUrl === null ) {
                throw new RuntimeException( "The normalized clip could not be stored." );
            }
            if ( !$this->clips->setNormalizedLocalFile( $clipId, $normalizedUrl ) ) {
                throw new RuntimeException( "The normalized file was stored, but the clip record could not be updated." );
            }

            $this->logger->info( "Clip audio normalized", [
                "clipId" => $clipId,
                "targetLoudness" => self::TARGET_LOUDNESS,
                "targetTruePeak" => self::TARGET_TRUE_PEAK,
                "targetRange" => self::TARGET_RANGE,
            ] );

            $clip[ "localUrl" ] = $normalizedUrl;
            $clip[ "audioNormalized" ] = true;
            return $clip;
        } finally {
            $this->removeWorkDirectory( $workDirectory );
            flock( $lock, LOCK_UN );
            fclose( $lock );
        }
    }

    private function download( string $url, string $destination ): void {
        $response = CurlController::download( $url, $destination )
            ->timeout( 120 )
            ->send( );
        clearstatcache( true, $destination );
        if ( !$response->isOk( ) || !is_file( $destination ) || filesize( $destination ) < 1024 ) {
            throw new RuntimeException( "The stored clip could not be downloaded for processing." );
        }
    }

    private function measure( string $sourcePath ): array {
        $filter = $this->targetFilter( ) . ":print_format=json";
        $result = $this->run( [
            "ffmpeg", "-hide_banner", "-nostdin", "-i", $sourcePath,
            "-map", "0:a:0", "-vn", "-af", $filter, "-f", "null", "-",
        ] );
        if ( $result[ "exitCode" ] !== 0 ) {
            throw new RuntimeException( "FFmpeg could not measure this clip's audio." );
        }

        preg_match_all( '/\{\s*"input_i".*?\}/s', $result[ "stderr" ], $matches );
        $json = $matches[ 0 ] === [ ] ? "" : (string) end( $matches[ 0 ] );
        $measurement = json_decode( $json, true );
        $required = [ "input_i", "input_tp", "input_lra", "input_thresh", "target_offset" ];
        if ( !is_array( $measurement ) || array_diff( $required, array_keys( $measurement ) ) !== [ ] ) {
            throw new RuntimeException( "FFmpeg did not return a usable loudness measurement." );
        }
        foreach ( $required as $key ) {
            if ( !is_numeric( $measurement[ $key ] ) ) {
                throw new RuntimeException( "This clip does not contain measurable audio." );
            }
        }
        return $measurement;
    }

    private function transcode( string $sourcePath, string $outputPath, array $measurement ): void {
        $filter = $this->targetFilter( ) .
            ":measured_I=" . (float) $measurement[ "input_i" ] .
            ":measured_TP=" . (float) $measurement[ "input_tp" ] .
            ":measured_LRA=" . (float) $measurement[ "input_lra" ] .
            ":measured_thresh=" . (float) $measurement[ "input_thresh" ] .
            ":offset=" . (float) $measurement[ "target_offset" ] .
            ":linear=true:print_format=summary";

        $result = $this->run( [
            "ffmpeg", "-y", "-hide_banner", "-nostdin", "-i", $sourcePath,
            "-map", "0:v:0?", "-map", "0:a:0", "-map_metadata", "0",
            "-c:v", "copy", "-c:a", "aac", "-b:a", "160k", "-ar", "48000",
            "-af", $filter, "-movflags", "+faststart", $outputPath,
        ] );
        clearstatcache( true, $outputPath );
        if ( $result[ "exitCode" ] !== 0 || !is_file( $outputPath ) || filesize( $outputPath ) < 1024 ) {
            throw new RuntimeException( "FFmpeg could not create the normalized clip." );
        }
    }

    private function verifyOutput( string $outputPath ): void {
        $result = $this->run( [
            "ffprobe", "-v", "error", "-show_entries", "stream=codec_type",
            "-of", "json", $outputPath,
        ] );
        $probe = json_decode( $result[ "stdout" ], true );
        $streamTypes = array_column( (array) ( $probe[ "streams" ] ?? [ ] ), "codec_type" );
        if ( $result[ "exitCode" ] !== 0 || !in_array( "video", $streamTypes, true ) || !in_array( "audio", $streamTypes, true ) ) {
            throw new RuntimeException( "The normalized clip failed media validation." );
        }
    }

    private function targetFilter( ): string {
        return "loudnorm=I=" . self::TARGET_LOUDNESS .
            ":TP=" . self::TARGET_TRUE_PEAK .
            ":LRA=" . self::TARGET_RANGE;
    }

    private function run( array $command ): array {
        $outputBase = tempnam( sys_get_temp_dir( ), "xogoria-ffmpeg-" );
        if ( $outputBase === false ) {
            throw new RuntimeException( "FFmpeg output could not be captured." );
        }
        $stdoutPath = $outputBase . ".stdout";
        $stderrPath = $outputBase . ".stderr";
        @unlink( $outputBase );

        try {
            $process = @proc_open( $command, [
                0 => [ "file", PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null", "r" ],
                1 => [ "file", $stdoutPath, "w" ],
                2 => [ "file", $stderrPath, "w" ],
            ], $pipes );
            if ( !is_resource( $process ) ) {
                throw new RuntimeException( "FFmpeg could not be started on this server." );
            }

            $exitCode = proc_close( $process );
            return [
                "exitCode" => $exitCode,
                "stdout" => (string) @file_get_contents( $stdoutPath ),
                "stderr" => (string) @file_get_contents( $stderrPath ),
            ];
        } finally {
            if ( is_file( $stdoutPath ) ) @unlink( $stdoutPath );
            if ( is_file( $stderrPath ) ) @unlink( $stderrPath );
        }
    }

    private function acquireLock( string $clipId ) {
        $lockPath = sys_get_temp_dir( ) . DIRECTORY_SEPARATOR .
            "xogoria-clip-normalize-" . hash( "sha256", $clipId ) . ".lock";
        $lock = @fopen( $lockPath, "c+" );
        if ( $lock === false || !flock( $lock, LOCK_EX | LOCK_NB ) ) {
            if ( is_resource( $lock ) ) {
                fclose( $lock );
            }
            throw new RuntimeException( "This clip is already being normalized." );
        }
        return $lock;
    }

    private function createWorkDirectory( ): string {
        $path = sys_get_temp_dir( ) . DIRECTORY_SEPARATOR . "xogoria-normalize-" . bin2hex( random_bytes( 8 ) );
        if ( !mkdir( $path, 0700 ) && !is_dir( $path ) ) {
            throw new RuntimeException( "A temporary audio workspace could not be created." );
        }
        return $path;
    }

    private function removeWorkDirectory( string $path ): void {
        foreach ( [ "source.mp4", "normalized.mp4" ] as $fileName ) {
            $filePath = $path . DIRECTORY_SEPARATOR . $fileName;
            if ( is_file( $filePath ) ) {
                @unlink( $filePath );
            }
        }
        if ( is_dir( $path ) ) {
            @rmdir( $path );
        }
    }

    private function isSupportedSourceUrl( string $url ): bool {
        return filter_var( $url, FILTER_VALIDATE_URL ) !== false &&
            strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) === "https";
    }
}
