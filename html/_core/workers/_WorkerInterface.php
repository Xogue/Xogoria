<?php

interface WorkerInterface {
    public function prime( WorkerContext $workerContext, InputDataContext $inputDataContext ): void;
    public function process( ): WorkerResult;
}
