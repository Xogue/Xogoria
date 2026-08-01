<?php

class LoreManager {
    private LoreStory $logsToLegends;
    private string $storyOutput = "";

    // MAGIC FUNCTIONS
    public function __construct( TemplateManager $templateManager ) { $this->logsToLegends = new LoreStory( $templateManager ); }

    // PUBLIC FUNCTIONS
    public function buildStory( array $loreChapters, array $loreAudio, array $loreStreams ) {
        $this->buildChapters( $loreChapters );
        $this->buildStreams( $loreStreams );
        $this->buildAudio( $loreAudio );
    }

    public function getStoryOutput( ) {
        if ( empty( $this->storyOutput ) ) {
            $this->storyOutput = $this->logsToLegends->assembleStory( );
        }
        return $this->storyOutput;
    }

    // PRIVATE FUNCTIONS
    // PRIVATE
    private function buildChapters( array $loreChapters ) {
        foreach ( $loreChapters as $chapterData ) {
            $chapter = new LoreChapter( $chapterData );
            $this->logsToLegends->addChapter( $chapter );
        }
    }

    private function buildStreams( array $loreStreams ) {
        foreach ( $loreStreams as $streamData ) {
            $stream = new LoreStream( $streamData );
            $chapter = $this->logsToLegends->getChapter( $streamData[ "chapterId" ] ?? "" );
            if ( $chapter ) {
                $chapter->addStream( $stream );
            }
        }
    }

    private function buildAudio( array $loreAudio ) {
        foreach ( $loreAudio as $audioData ) {
            $audio = new LoreAudio( $audioData );
            $chapter = $this->logsToLegends->getChapter( $audioData[ "chapterId" ] ?? "" );
            if ( $chapter ) {
                $chapter->addAudio( $audio );
            }
        }
    }
}
