<?php

class LoreStream {
    private array $replaceMap = [
        "{HREF}" => "getUrl",
        "{DATE}" => "getDate",
    ];

    private int $chapterId;
    private string $url;
    private string $date;

    // MAGIC FUNCTIONS
    public function __construct( array $streamData ) {
        $this->chapterId = $streamData[ "chapterId" ] ?? 0;
        $this->url = $streamData[ "url" ] ?? "";
        $this->date = $streamData[ "date" ] ?? "";
    }

    // PUBLIC FUNCTIONS
    // GETTERS
    public function getChapterId( ): int    { return $this->chapterId; }
    public function getUrl( )      : string { return $this->url; }
    public function getDate( )     : string { return $this->date; }

    // OTHER
    public function assembleStream( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
