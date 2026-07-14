<?php

class LoreStory {
    private TemplateManager $templateManager;
    private array $chapterTemplatePack = [];
    private array $chapters = [];

    // TEMPLATES
    // Lore
    private string $loreStart;
    private string $loreEnd;

    // Chapter
    private string $chapterTemplate;
    private string $subChapterTemplate;

    // Stream
    private string $streamStart;
    private string $streamTemplate;
    private string $streamEnd;

    // Audio
    private string $audioTemplate;

    public function __construct(TemplateManager $templateManager) {
        $this->templateManager = $templateManager;
        $this->getStoryParts();
    }
    
    public function addChapter(LoreChapter $chapter) { $this->chapters[$chapter->getId()] = $chapter; }
    public function getChapter(int $chapterId): ?LoreChapter { return $this->chapters[$chapterId] ?? null; }

    public function assembleStory() {
        $story = $this->loreStart;
        foreach ($this->chapters as $chapter) {
            $story .= $chapter->assembleChapter($this->chapterTemplatePack);
        }
        $story .= $this->loreEnd;
        return $story;
    }

    // PRIVATE FUNCTIONS
    private function getStoryParts() {
        // Lore
        $this->loreStart = $this->templateManager->getPart('loreStart');
        $this->loreEnd = $this->templateManager->getPart('loreEnd');

        // Chapter
        $this->chapterTemplate = $this->templateManager->getPart('chapter');
        $this->subChapterTemplate = $this->templateManager->getPart('subChapter');

        // Stream
        $this->streamStart = $this->templateManager->getPart('streamStart');
        $this->streamTemplate = $this->templateManager->getPart('streamLink');
        $this->streamEnd = $this->templateManager->getPart('streamEnd');

        // Audio
        $this->audioTemplate = $this->templateManager->getPart('audioLink');

        $this->chapterTemplatePack = [
            'chapter' => $this->chapterTemplate,
            'subChapter' => $this->subChapterTemplate,
            'streamStart' => $this->streamStart,
            'streamTemplate' => $this->streamTemplate,
            'streamEnd' => $this->streamEnd,
            'audioTemplate' => $this->audioTemplate
        ];
    }
}