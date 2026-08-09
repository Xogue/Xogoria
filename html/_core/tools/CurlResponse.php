<?php

final class CurlResponse {
    public function __construct(
        private string $body,
        private int $statusCode,
        private int $errorNumber = 0,
        private string $errorMessage = "",
    ) { }

    public function statusCode( ): int { return $this->statusCode; }
    public function isOk( ): bool {
        return $this->errorNumber === 0 && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function json( ): ?array {
        try {
            $data = json_decode( $this->body, true, flags: JSON_THROW_ON_ERROR );
            return is_array( $data ) ? $data : null;
        } catch ( JsonException ) {
            return null;
        }
    }

    public function summary( ): string {
        $summary = "HTTP " . $this->statusCode;
        if ( $this->errorNumber !== 0 ) {
            $summary .= "; cURL " . $this->errorNumber . ": " . $this->errorMessage;
        }
        return $summary;
    }
}
