<?php

class ConfigureWorker implements WorkerInterface {
    private WorkerContext $workerContext;
    private InputDataContext $inputData;

    public function __construct(private UserContext $user) {}
    public function prime(WorkerContext $workerContext, InputDataContext $inputDataContext): void {
        $this->workerContext = $workerContext;
        $this->inputData = $inputDataContext;
    }
    public function process(): WorkerResult {
        if (!$this->user->isAdmin()) {
            return WorkerResult::failure('Moderator access is required.', 'moderator_required', 403);
        }
        $type = $this->inputData->getType();
        $action = $this->inputData->getAction();
        
        if ($type === 'game') {
            $parts = explode('-', $action, 2);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                return WorkerResult::failure('A valid game and profile are required.', 'invalid_configuration');
            }
            [$game, $profile] = $parts;
            $this->workerContext->setActiveGameAndProfile($game, $profile);
        }
        return new WorkerResult(true);
    }

    public function createWorkerResult(string $protocolString): WorkerResult {
        return new WorkerResult($protocolString);
    }
}
