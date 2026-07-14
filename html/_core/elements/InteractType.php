<?php

class InteractType {
    /** @var Interaction[] */
    private array $interactions = [];

    public function __construct( private string $name, private array $data, private TemplateManager $templateManager) {
        foreach ($data as $interactionName => $interactionData) {
            $panelTemplate = $this->templateManager->getPart('panel' . ucfirst($name));
            $this->interactions[$interactionName] = new Interaction($name, $interactionName, $interactionData, $panelTemplate);
        }
    }

    public function getInteractions(): array {
        return $this->interactions;
    }

    public function getInteraction(string $name): ?Interaction {
        return $this->interactions[$name] ?? null;
    }

    public function getHtml(): string {
        $output = $this->templateManager->getPart($this->name . "Start");
        foreach ($this->interactions as $interaction) {
            $output .= $interaction->replaceValues($this->templateManager->getPart($this->name));
        }
        $output .= $this->templateManager->getPart($this->name . "End");
        return $output;
    }
}