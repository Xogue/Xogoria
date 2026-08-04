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
            return WorkerResult::failureCode( ResponseLibrary::R_CONFIGURE__MODERATOR_REQUIRED, [ ], 403 );
        }
        $type = $this->inputData->getType();
        $action = $this->inputData->getAction();
        
        if ($type !== 'game') {
            return WorkerResult::failureCode( ResponseLibrary::R_CONFIGURE__INVALID_ACTION, [
                'configurationType' => $type,
            ] );
        }

        $parts = explode('-', $action, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return WorkerResult::failureCode( ResponseLibrary::R_CONFIGURE__INVALID_CONFIGURATION, [
                'configuration' => $action,
            ] );
        }
        [$game, $profile] = $parts;
        try {
            $this->workerContext->setActiveGameAndProfile($game, $profile);
        } catch ( InvalidArgumentException ) {
            return WorkerResult::failureCode( ResponseLibrary::R_CONFIGURE__INVALID_CONFIGURATION, [
                'configuration' => $action,
            ] );
        }
        return WorkerResult::success( ResponseLibrary::R_CONFIGURE__GAME_CHANGED, true, [
            'game' => $game,
            'profile' => $profile,
        ] );
    }

}
