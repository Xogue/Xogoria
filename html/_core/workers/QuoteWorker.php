<?php

class QuoteWorker implements WorkerInterface {
    private InputDataContext $inputDataContext;

    // MAGIC FUNCTIONS
    public function __construct( private MySqlManager $mySqlManager ) { }

    // PUBLIC FUNCTIONS
    public function prime( WorkerContext $workerContext, InputDataContext $inputDataContext ): void {
        $this->inputDataContext = $inputDataContext;
    }

    public function process( ): WorkerResult {
        $action = $this->inputDataContext->getAction( );
        $argument = $action === "quote" ? $this->inputDataContext->getQuote( ) : null;
        if ( $argument === null ) {
            return WorkerResult::failureCode( ResponseLibrary::R_QUOTE__INVALID_ACTION, [
                "action" => $action,
            ] );
        }

        $argument = trim( $argument );
        if ( $argument === "" ) {
            return WorkerResult::failureCode( ResponseLibrary::R_QUOTE__MISSING_ARGUMENT );
        }

        $rows = $this->fetchCollectionValue( $argument );
        $row = is_array( $rows ) ? ( $rows[ 0 ] ?? null ) : null;
        if ( !is_array( $row ) ) {
            return WorkerResult::failureCode( ResponseLibrary::R_QUOTE__NOT_FOUND, [
                "search" => $argument,
            ], 404 );
        }

        return WorkerResult::success( ResponseLibrary::R_QUOTE__FOUND, $row, [
            "quote" => (string) ( $row[ "text" ] ?? "" ),
            "search" => $argument,
        ] );
    }

    private function fetchCollectionValue( string $argument ): mixed {
        $argument = trim( $argument );
        if ( strcasecmp( $argument, "random" ) === 0 ) {
            return $this->mySqlManager->fetchData( "randomQuote" );
        }

        if ( $argument === "" ) {
            return [ ];
        }

        return $this->mySqlManager->fetchDataByDetail( "quote", $argument );
    }
}
