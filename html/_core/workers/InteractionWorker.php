<?php

class InteractionWorker implements WorkerInterface {
    private WorkerContext $workerContext;
    private InputDataContext $inputDataContext;
    private array $commandsToSend;

    public function __construct(
        private InteractionManager $interactionManager,
        private UserContext $user,
        private MySqlManager $bank
    ) { }
    public function prime(WorkerContext $workerContext, InputDataContext $inputDataContext): void {
        $this->workerContext = $workerContext;
        $this->inputDataContext = $inputDataContext;
    }

    public function process(): WorkerResult {
        $type = $this->inputDataContext->getType();
        $action = $this->inputDataContext->getAction();

        if (!$this->workerContext->getProfile()->allowsSimpleInteraction($type, $action)) {
            return WorkerResult::failure('That command is not available for the active game and profile.', 'wrong_game');
        }

        if (!$this->user->userLoggedIn()) {
            return WorkerResult::failure('You must be logged in to use interactions.', 'login_required', 401);
        }

        $details = [
            '{DURATION}' => $this->inputDataContext->getDuration(),
            '{COST}' => $this->inputDataContext->getCost(),
            '{COOLDOWN}' => $this->inputDataContext->getCooldown()
        ];
        
        $simpleType = $this->workerContext->getGame()->getSimpleType($type);
        $interaction = $simpleType?->getInteraction($action);
        if ($interaction === null) {
            return WorkerResult::failure('The requested interaction does not exist.', 'interaction_not_found');
        }

        if (!$interaction->isEnabled()) {
            return WorkerResult::failure('That interaction is currently disabled.', 'interaction_disabled');
        }

        $duration = max(1, min(300, $this->inputDataContext->getDuration()));
        $cost = max(0, $interaction->getCost() * $duration);
        if ($this->user->getGemBalance() < $cost) {
            return WorkerResult::failure('You do not have enough Airo Gems.', 'insufficient_funds');
        }
        

        $details['{DURATION}'] = $duration;
        $details['{COST}'] = $cost;
        if ($cost > 0 && !$this->bank->subtractFromUserBalance($this->user->getUserId(), $cost)) {
            return WorkerResult::failure('Your Airo Gems could not be charged.', 'currency_error', 500);
        }

        $this->commandsToSend = $interaction->getCommandArray($details);
        $success = $this->interactionManager->sendCommands($this->workerContext->getServerId(), $this->commandsToSend);
        if (!$success && $cost > 0) {
            $this->bank->addToUserBalance($this->user->getUserId(), $cost);
            return WorkerResult::failure('The interaction failed; your Airo Gems were refunded.', 'interaction_failed', 502);
        }

        $result = new WorkerResult(true, 'Interaction completed.');
        $result->addMeta('cost', $cost);
        return $result;
    }

    public function createWorkerResult(string $protocolString): WorkerResult {
        return new WorkerResult($protocolString);
    }
}
