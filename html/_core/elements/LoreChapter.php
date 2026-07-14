<?php

class LoreChapter {
    private array $replaceMap = [
        '{ID}' => 'getId',
        '{NUMBER}' => 'getChapterNumber',
        '{TITLE}' => 'getTitle',
        '{BODY}' => 'getBody',
        '{ACTIONS}' => 'getActions',
        '{DISTRACTION}' => 'getDistraction'
    ];

    private array $streams = [];
    private array $audio = [];

    private string $id;
    private string $chapterNumber;
    private string $distraction;
    private string $actions;
    private string $title;
    private string $body;

    public function __construct(array $chapterData) {
        $this->id = $chapterData['chapterId'] ?? '';
        $this->title = $chapterData['chapterTitle'] ?? '';
        $this->body = $chapterData['chapterText'] ?? '';

        $this->chapterNumber = $chapterData['chapterNumber'] ?? '';
        $this->distraction = explode('.', $chapterData['chapterNumber'])[1] ?? '';

    }
    
    public function addStream(LoreStream $stream) {
        $this->streams[$stream->getChapterId() . ": " . $stream->getDate()] = $stream;
    }

    public function addAudio(LoreAudio $audio) {
        $this->audio[$audio->getChapterId()] = $audio;
    }

    public function assembleChapter(array $chapterTemplatePack): string {
        $this->actions = $chapterTemplatePack['streamStart'];
        foreach ($this->streams as $stream) {
            $this->actions .= $stream->assembleStream($chapterTemplatePack['streamTemplate']);
        }
        $this->actions .= $chapterTemplatePack['streamEnd'];

        foreach ($this->audio as $audio) {
            $this->actions .= $audio->assembleAudio($chapterTemplatePack['audioTemplate']);
        }

        $output = $this->replaceValues($chapterTemplatePack['chapter'], $chapterTemplatePack['subChapter']);
        return $output;
    }

    // GETTERS
    public function getId(): string { return $this->id; }
    public function getChapterNumber(): string { return $this->chapterNumber; }
    public function getDistraction(): string { return $this->distraction; }
    public function getActions(): string { return $this->actions; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getStreams(): array { return $this->streams; }
    public function getAudio(): array { return $this->audio; }

    // OTHER
    public function replaceValues(string $template, string $subChapterTemplate): string {
        $replaced = $this->distraction ? $subChapterTemplate : $template;
        foreach ($this->replaceMap as $key => $method) {
            $replaced = str_replace($key, $this->$method(), $replaced);
        }
        return $replaced;
    }
}