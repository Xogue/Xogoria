<?php

class QuoteCollection {
    /** @var Quote[] */
    private array $quotes = [];

    public function add(Quote $quote): void {
        $this->quotes[] = $quote;
    }

    public function getAll(): array {
        return $this->quotes;
    }
}