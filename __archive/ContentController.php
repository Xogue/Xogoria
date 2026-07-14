<?php

class ContentController {
    private TemplateManager $templateManager;
    private string $assembledHtml = '';

    public function __construct() {
        $this->templateManager = new TemplateManager();
    }

    public function buildSimpleHtml(string $prefix, array $collection) {
        return $this->templateManager->buildHtmlFromArray($prefix, $collection);
    }

    public function buildLoreHtml(array $chapters, ?array $audio, ?array $streams) {
        $this->assembledHtml = $this->templateManager->getPart('loreStart');
        foreach ($chapters as $chapterId => $chapterData) {
            $this->assembledHtml .= $this->buildChapter($chapterData['chapterId'], $chapterData, $audio, $streams);
        }

        $this->assembledHtml .= $this->templateManager->getPart('loreEnd');
        return $this->assembledHtml;
    }

    public function filterData(array $collection, array $filters) {
        return array_filter($collection, function($item) use ($filters) {
            return $this->checkItem($item, $filters);
        });
    }

    public function getPart(string $partName) { return $this->templateManager->getPart($partName); }

    // PRIVATE FUNCTIONS
    private function checkItem(array $item, array $filters) {
        foreach ($filters as $key => $value) {
            if (strtolower($item[$key]) != strtolower($value)) {
                return false;
            }
        }
        return true;
    }

    private function buildChapter( string $chapterId, array $chapterData, ?array $audio, ?array $streams ) {
        $actions = '';
        if (!empty($streams)) {
            $actions .= $this->templateManager->getPart('streamStart');
            foreach ($streams as $streamKey => $streamData) {
                if ((int)$streamData['chapterId'] !== (int)$chapterId) {continue;}
                $actions .= $this->buildStream($streamData);
            }
            $actions .= $this->templateManager->getPart('streamEnd');
        }

        if (!empty($audio)) {
            foreach ($audio as $audioKey => $audioData) {
                if ((int)$audioData['chapterId'] !== (int)$chapterId) {continue;}
                $actions .= $this->buildAudio($audioData);
            }
        }

        $data = [];
        $chapterPieces = explode('.', $chapterData['chapterNumber']);
        $distractionNumber = $chapterPieces[1] ?? '';
        $data['{ID}'] = $chapterId;
        $data['{NUMBER}'] = $chapterData['chapterNumber'];
        $data['{TITLE}'] = $chapterData['chapterTitle'];
        $data['{TEXT}'] = $chapterData['chapterText'];
        $data['{ACTIONS}'] = $actions;
        $data['{DISTRACTION_NUMBER}'] = $distractionNumber;

        if ($distractionNumber) {
            return strtr($this->templateManager->getPart('subChapter'), $data);
        } else {
            return strtr($this->templateManager->getPart('chapter'), $data);
        }
    }

    private function buildStream( array $streamData ) {
        $data = [];
        $data['{HREF}'] = $streamData['url'];
        if ( $data['{HREF}'] === '' ) {return;}

        $streamDate = $streamData['date'] ?? '';
        $formattedStreamDate = 'No Date Found';
        if ( $streamDate !== '' ) {
            $stringStreamDate = strtotime( $streamDate );
            if ( $stringStreamDate !== false ) {
                $formattedStreamDate = date( 'M j, Y', $stringStreamDate );
            }
        }

        $data['{LABEL}'] = $formattedStreamDate;
        return strtr($this->templateManager->getPart('streamLink'), $data);
    }

    private function buildAudio( array $audioData ) {
        $data = [];
        $data['{AUDIO_URL}'] = $audioData['url'] ?? '';
        if ( $data['{AUDIO_URL}'] === '' ) {return;}

        $data['{LABEL}'] = $audioData['label'] ?? '';
        if ( $data['{LABEL}'] === '' ) {$data['{LABEL}'] = 'Play Clip';}

        return strtr($this->templateManager->getPart('audioLink'), $data);
    }
}