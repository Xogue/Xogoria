<?php

class JsonHandler {
    private string $filename;

    public function safeLoad( string $filename ): array {
        $this->filename = $filename;
        if ( $this->checkReadable( $filename ) ) {
            $fileContents = file_get_contents( $filename );
            if ( $this->wasRead( $fileContents ) ) {
                $data = json_decode( $fileContents, true );
                if ( $this->wasDecoded( $data ) ) {
                    return $data;
                }
            }
        }

        return [];
    }

    public function safeWrite( string $filename, array $data ) {
        $this->filename = $filename;
        if ( $this->checkWritable( $filename ) ) {
            $jsonData = json_encode( $data, JSON_PRETTY_PRINT );
            if ( $this->wasEncoded( $jsonData ) ) {
                file_put_contents( $filename, $jsonData );
            }
        }
    }

    // PRIVATE FUNCTIONS
    private function checkReadable( string $filename ): bool {
        if ( !is_readable( $filename ) ) {
            throw new RuntimeException('File not readable: ' . $filename);
            return false;
        }

        return true;
    }
    private function checkWritable( string $filename ): bool {
        if ( !is_writable( $filename ) ) {
            throw new RuntimeException('File not writable: ' . $filename);
            return false;
        }

        return true;
    }
    private function wasRead( string $fileContents ): bool {
        if ( !$fileContents ) {
            throw new RuntimeException('Failed to read file: ' . $this->filename);
            return false;
        }

        return true;
    }

    private function wasDecoded( mixed $data ): bool {
        if ( !$data ) {
            throw new RuntimeException('Failed to decode JSON from file: ' . $this->filename . ' Error: ' . json_last_error_msg());
            return false;
        }

        return true;
    }

    private function wasEncoded( string $jsonData ): bool {
        if ( !$jsonData ) {
            throw new RuntimeException('Failed to encode JSON for file: ' . $this->filename . ' Error: ' . json_last_error_msg());
            return false;
        }

        return true;
    }

}
