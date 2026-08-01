<?php

class CollectionWorker implements WorkerInterface {
    private InputDataContext $inputDataContext;
    private WorkerContext $workerContext;

    // MAGIC FUNCTIONS
    public function __construct( private MySqlManager $mySqlManager ) { }

    // PUBLIC FUNCTIONS
    public function createWorkerResult( string $protocolString ): WorkerResult { return new WorkerResult( $protocolString ); }

    public function prime( WorkerContext $workerContext, InputDataContext $inputDataContext ): void {
        $this->inputDataContext = $inputDataContext;
        $this->workerContext = $workerContext;
    }

    public function process( ): WorkerResult {
        $result = match ( $this->inputDataContext->getAction( ) ) {
            "name" => $this->mySqlManager->fetchDataByDetail(
                "monster",
                $this->inputDataContext->getMonster( ),
            ),
            "objective" => $this->mySqlManager->fetchDataByDetail(
                "objective",
                $this->inputDataContext->getObjective( ),
            ),
            "quote" => $this->mySqlManager->fetchDataByDetail(
                "quote",
                $this->inputDataContext->getQuote( ),
            ),
            default => false,
        };
        return new WorkerResult( $result );
    }
}
