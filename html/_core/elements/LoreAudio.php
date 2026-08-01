<?php

class LoreAudio {
    private array $replaceMap = [
        "{HREF}" => "getUrl",
        "{LABEL}" => "getLabel",
    ];

    private int $chapterId;
    private string $url;
    private string $label;

    // MAGIC FUNCTIONS
    public function __construct( array $audioData ) {
        $this->chapterId = $audioData[ "chapterId" ] ?? 0;
        $this->url = $audioData[ "url" ] ?? "";
        $this->label = $audioData[ "label" ] ?? "";
    }

    // PUBLIC FUNCTIONS
    // GETTERS
    public function getChapterId( ): int    { return $this->chapterId; }
    public function getUrl( )      : string { return $this->url; }
    public function getLabel( )    : string { return $this->label; }

    // OTHER
    public function assembleAudio( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
