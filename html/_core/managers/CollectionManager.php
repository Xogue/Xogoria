<?php

class CollectionManager {
    private QuoteCollection $quotes;
    private CommandCollection $commands;

    // MAGIC FUNCTIONS
    public function __construct( private TemplateManager $templateManager ) {
        $this->quotes = new QuoteCollection( );
        $this->commands = new CommandCollection( );
    }

    // PUBLIC FUNCTIONS
    public function getQuotes( )    : QuoteCollection       { return $this->quotes; }
    public function getCommands( )  : CommandCollection     { return $this->commands; }

    public function insertQuotes( array $quotes ): void {
        foreach ( $quotes as $quote ) {
            $this->quotes->add( new Quote( $quote ) );
        }
    }


    public function insertCommands( array $commands ): void {
        foreach ( $commands as $command ) {
            $this->commands->add( new Command( $command ) );
        }
    }

    public function assembleQuotes( ): string {
        $startTemplate = $this->templateManager->getPart( "quoteStart" );
        $centerTemplate = $this->templateManager->getPart( "quote" );
        $endTemplate = $this->templateManager->getPart( "quoteEnd" );

        $assembledQuotes = $startTemplate;
        foreach ( $this->quotes->getAll( ) as $quote ) {
            $assembledQuotes .= $quote->assembleQuote( $centerTemplate );
        }
        return $assembledQuotes . $endTemplate;
    }

    public function assembleCommands( array $filters = [ ] ): string {
        $startTemplate = $this->templateManager->getPart( "commandStart" );
        $centerTemplate = $this->templateManager->getPart( "command" );
        $endTemplate = $this->templateManager->getPart( "commandEnd" );

        $assembledCommands = $startTemplate;
        foreach ( $this->commands->getAll( ) as $command ) {
            if ( !empty( $filters ) ) {
                if ( !$this->included( $command, $filters ) ) {
                    continue;
                }
            }
            $assembledCommands .= $command->assembleCommand( $centerTemplate );
        }
        return $assembledCommands . $endTemplate;
    }

    // PRIVATE FUNCTIONS
    private function included( Command $command, array $filters ): bool {
        if (
            isset( $filters[ "perms" ] ) &&
            strtolower( $command->getPerms( ) ) != strtolower( $filters[ "perms" ] )
        ) {
            return false;
        }
        if (
            isset( $filters[ "category" ] ) &&
            strtolower( $command->getCategory( ) ) != strtolower( $filters[ "category" ] )
        ) {
            return false;
        }
        return true;
    }
}
