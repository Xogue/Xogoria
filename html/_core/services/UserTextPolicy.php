<?php

final class UserTextPolicy {
    public function __construct( private array $restrictedWords ) { }

    public function isAllowed( string $text ): bool {
        return $this->findRestrictedWord( $text ) === null;
    }

    public function findRestrictedWord( string $text ): ?string {
        $normalizedText = $this->normalize( $text );

        foreach ( $this->restrictedWords as $word ) {
            $word = trim( (string) $word );
            if ( $word === "" ) {
                continue;
            }

            $normalizedWord = $this->normalize( $word );
            $normalizedWord = preg_replace( "/[^\\p{L}\\p{N}]+/u", "", $normalizedWord ) ?? "";
            $characters = preg_split( "//u", $normalizedWord, -1, PREG_SPLIT_NO_EMPTY );
            if ( $characters === false || $characters === [ ] ) {
                continue;
            }

            $pattern = implode( "[^\\p{L}\\p{N}]*", array_map( fn( string $character ): string => preg_quote( $character, "/" ), $characters ) );
            if ( preg_match( "/(?:(?<![\\p{L}\\p{N}]){$pattern}|{$pattern}(?![\\p{L}\\p{N}]))/u", $normalizedText ) === 1 ) {
                return $word;
            }
        }

        return null;
    }

    private function normalize( string $text ): string {
        if ( class_exists( "Normalizer" ) ) {
            $text = Normalizer::normalize( $text, Normalizer::FORM_KD ) ?: $text;
            $text = preg_replace( "/\\p{M}+/u", "", $text ) ?? $text;
        }
        $text = function_exists( "mb_strtolower" ) ? mb_strtolower( $text, "UTF-8" ) : strtolower( $text );
        return strtr( $text, [
            "0" => "o",
            "1" => "i",
            "3" => "e",
            "4" => "a",
            "5" => "s",
            "7" => "t",
            "@" => "a",
            "$" => "s",
        ] );
    }
}
