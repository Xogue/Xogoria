<?php

class CollectionWorker implements WorkerInterface {
    private InputDataContext $inputDataContext;

    // MAGIC FUNCTIONS
    public function __construct( private MySqlManager $mySqlManager ) { }

    // PUBLIC FUNCTIONS
    public function prime( WorkerContext $workerContext, InputDataContext $inputDataContext ): void {
        $this->inputDataContext = $inputDataContext;
    }

    public function process( ): WorkerResult {
        $action = $this->inputDataContext->getAction( );
        $argument = match ( $action ) {
            "name" => $this->inputDataContext->getMonster( ),
            "objective" => $this->inputDataContext->getObjective( ),
            "quote" => $this->inputDataContext->getQuote( ),
            default => null,
        };
        if ( $argument === null ) {
            return WorkerResult::failureCode( ResponseLibrary::R_COLLECTION__INVALID_ACTION, [
                "action" => $action,
            ] );
        }

        $argument = trim( $argument );
        $collection = $action === "name" ? "monster name" : $action;
        if ( $argument === "" ) {
            return WorkerResult::failureCode( ResponseLibrary::R_COLLECTION__MISSING_ARGUMENT, [
                "collection" => $collection,
            ] );
        }

        $rows = $action === "name"
            ? $this->mySqlManager->fetchDataByDetail( "monster", $argument )
            : $this->fetchCollectionValue( $action, $argument );
        $row = is_array( $rows ) ? ( $rows[ 0 ] ?? null ) : null;
        if ( !is_array( $row ) ) {
            return WorkerResult::failureCode( ResponseLibrary::R_COLLECTION__NOT_FOUND, [
                "collection" => $collection,
                "search" => $argument,
            ], 404 );
        }

        return match ( $action ) {
            "name" => WorkerResult::success( ResponseLibrary::R_COLLECTION__MONSTER_FOUND, $row, [
                "gameName" => (string) ( $row[ "gameName" ] ?? $argument ),
                "customName" => (string) ( $row[ "customName" ] ?? "" ),
                "search" => $argument,
            ] ),
            "objective" => WorkerResult::success( ResponseLibrary::R_COLLECTION__OBJECTIVE_FOUND, $row, [
                "objective" => (string) ( $row[ "requirement" ] ?? "" ),
                "search" => $argument,
            ] ),
            "quote" => WorkerResult::success( ResponseLibrary::R_COLLECTION__QUOTE_FOUND, $row, [
                "quote" => (string) ( $row[ "text" ] ?? "" ),
                "search" => $argument,
            ] ),
        };
    }

    private function fetchCollectionValue( string $collection, string $argument ): mixed {
        $argument = trim( $argument );
        if ( strcasecmp( $argument, "random" ) === 0 ) {
            return $this->mySqlManager->fetchData( match ( $collection ) {
                "quote" => "randomQuote",
                "objective" => "randomObjective",
                default => "",
            } );
        }

        if ( $argument === "" ) {
            return [ ];
        }

        return $this->mySqlManager->fetchDataByDetail( $collection, $argument );
    }
}
