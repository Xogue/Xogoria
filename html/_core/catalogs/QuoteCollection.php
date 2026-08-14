<?php

class QuoteCollection {
    /** @var Quote[] */
    private array $quotes = [ ];

    // PUBLIC FUNCTIONS
    public function getAll( ): array { return $this->quotes; }
    public function add( Quote $quote ): void { $this->quotes[ ] = $quote; }
}
