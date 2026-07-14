<?php

class CollectionManager {
    private MonsterNameCollection $monsters;
    private QuoteCollection $quotes;
    private ObjectiveCollection $objectives;
    private CommandCollection $commands;

    public function __construct( private TemplateManager $templateManager ) {
        $this->monsters   = new MonsterNameCollection();
        $this->quotes     = new QuoteCollection();
        $this->objectives = new ObjectiveCollection();
        $this->commands   = new CommandCollection();
    }

    public function getMonsters(): MonsterNameCollection {return $this->monsters;}
    public function getQuotes(): QuoteCollection {return $this->quotes;}
    public function getObjectives(): ObjectiveCollection {return $this->objectives;}
    public function getCommands(): CommandCollection {return $this->commands;}

    public function insertMonsters( array $monsters ): void {
        foreach ( $monsters as $monster ) {
            $this->monsters->add( new MonsterName( $monster ) );
        }
    }

    public function insertQuotes( array $quotes ): void {
        foreach ( $quotes as $quote ) {
            $this->quotes->add( new Quote( $quote ) );
        }
    }

    public function insertObjectives( array $objectives ): void {
        foreach ( $objectives as $objective ) {
            $this->objectives->add( new Objective( $objective ) );
        }
    }

    public function insertCommands( array $commands ): void {
        foreach ( $commands as $command ) {
            $this->commands->add( new Command( $command ) );
        }
    }

    public function assembleMonsters(): string {
        $startTemplate  = $this->templateManager->getPart( 'monsterStart' );
        $centerTemplate = $this->templateManager->getPart( 'monster' );
        $endTemplate    = $this->templateManager->getPart( 'monsterEnd' );

        $assembledMonsters = $startTemplate;
        foreach ( $this->monsters->getAll() as $monster ) {
            $assembledMonsters .= $monster->assembleMonsterName( $centerTemplate );
        }
        return $assembledMonsters . $endTemplate;
    }

    public function assembleQuotes(): string {
        $startTemplate  = $this->templateManager->getPart( 'quoteStart' );
        $centerTemplate = $this->templateManager->getPart( 'quote' );
        $endTemplate    = $this->templateManager->getPart( 'quoteEnd' );

        $assembledQuotes = $startTemplate;
        foreach ( $this->quotes->getAll() as $quote ) {
            $assembledQuotes .= $quote->assembleQuote( $centerTemplate );
        }
        return $assembledQuotes . $endTemplate;
    }

    public function assembleObjectives(): string {
        $startTemplate  = $this->templateManager->getPart( 'objectiveStart' );
        $centerTemplate = $this->templateManager->getPart( 'objective' );
        $endTemplate    = $this->templateManager->getPart( 'objectiveEnd' );

        $assembledObjectives = $startTemplate;
        foreach ( $this->objectives->getAll() as $objective ) {
            $assembledObjectives .= $objective->assembleObjective( $centerTemplate );
        }
        return $assembledObjectives . $endTemplate;
    }

    public function assembleCommands( array $filters = [] ): string {
        $startTemplate  = $this->templateManager->getPart( 'commandStart' );
        $centerTemplate = $this->templateManager->getPart( 'command' );
        $endTemplate    = $this->templateManager->getPart( 'commandEnd' );

        $assembledCommands = $startTemplate;
        foreach ( $this->commands->getAll() as $command ) {
            if ( !empty( $filters ) ) {
                if ( !$this->included( $command, $filters ) ) { continue; }
            }
            $assembledCommands .= $command->assembleCommand( $centerTemplate );
        }
        return $assembledCommands . $endTemplate;
    }

    // PRIVATE FUNCTIONS
    private function included( Command $command, array $filters ): bool {
        if (isset($filters['perms']) && strtolower($command->getPerms()) != strtolower($filters['perms'])) {
            return false;
        }
        if (isset($filters['category']) && strtolower($command->getCategory()) != strtolower($filters['category'])) {
            return false;
        }
        return true;
    }
}
