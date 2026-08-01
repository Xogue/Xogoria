<?php

class Quote {
    private array $replaceMap = [
        "{ID}" => "getId",
        "{TEXT}" => "getText",
        "{SPEAKER}" => "getSpeaker",
        "{GAME}" => "getGame",
        "{DATE}" => "getDate",
        "{FAVORITE}" => "isFavorite",
    ];

    private int $id;
    private string $text;
    private string $speaker;
    private string $game;
    private bool $favorite;
    private string $date;

    // MAGIC FUNCTIONS
    public function __construct( array $quoteData ) {
        $this->id = $quoteData[ "id" ];
        $this->text = $quoteData[ "text" ];
        $this->speaker = $quoteData[ "speaker" ];
        $this->game = $quoteData[ "game" ];
        $this->favorite = $quoteData[ "favorite" ];
        $this->date = $quoteData[ "date" ];
    }

    // PUBLIC FUNCTIONS
    public function getId( )     : int    { return $this->id; }
    public function getText( )   : string { return $this->text; }
    public function getSpeaker( ): string { return $this->speaker; }
    public function getGame( )   : string { return $this->game; }
    public function isFavorite( ): bool   { return $this->favorite; }
    public function getDate( )   : string { return $this->date; }

    // OTHER
    public function assembleQuote( string $template ): string { return $this->replaceValues( $template ); }

    public function replaceValues( string $template ): string {
        $replaced = $template;
        foreach ( $this->replaceMap as $key => $method ) {
            $replaced = str_replace( $key, $this->$method( ), $replaced );
        }
        return $replaced;
    }
}
