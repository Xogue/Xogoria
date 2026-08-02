<?php

class InteractionWorker implements WorkerInterface {
    private WorkerContext $workerContext;
    private InputDataContext $inputDataContext;
    private array $commandsToSend;

    public function __construct(
        private InteractionManager $interactionManager,
        private UserContext $user,
        private MySqlManager $bank,
        private UserTextPolicy $userTextPolicy,
    ) { }
    public function prime(WorkerContext $workerContext, InputDataContext $inputDataContext): void {
        $this->workerContext = $workerContext;
        $this->inputDataContext = $inputDataContext;
    }

    public function process(): WorkerResult {
        $type = $this->inputDataContext->getType();
        $action = $this->inputDataContext->getAction();

        if (!$this->user->userLoggedIn()) {
            return WorkerResult::failure('You must be logged in to use interactions.', 'login_required', 401);
        }

        if ($type === 'powerSpawn') {
            return $this->processPowerSpawn();
        }

        if ($type === 'special' && $action === 'batClaim') {
            return $this->processBatClaim();
        }

        if (!$this->workerContext->getProfile()->allowsSimpleInteraction($type, $action)) {
            return WorkerResult::failure('That command is not available for the active game and profile.', 'wrong_game');
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

    private function processPowerSpawn(): WorkerResult {
        $powerSpawn = $this->workerContext->getGame()->getSpecialType('powerSpawn');
        if (!$powerSpawn instanceof PowerSpawn) {
            return WorkerResult::failure('Power spawning is not available for the active game.', 'interaction_not_found');
        }

        $commands = [];
        $cost = 0;
        $count = 0;
        $cooldown = 0;

        foreach ($this->inputDataContext->getMobs() as $mobName => $requestedCount) {
            $mobName = (string) $mobName;
            $requestedCount = filter_var($requestedCount, FILTER_VALIDATE_INT);
            if ($requestedCount === false || $requestedCount < 0 || $requestedCount > 25) {
                return WorkerResult::failure('Each mob quantity must be between 0 and 25.', 'invalid_quantity');
            }
            if ($requestedCount === 0) {
                continue;
            }

            $mob = $powerSpawn->getMobs()[$mobName] ?? null;
            if (!$mob instanceof Mob || !$mob->isEnabled() || !$this->workerContext->getProfile()->allowsSpecialInteraction('powerSpawn', $mobName)) {
                return WorkerResult::failure('That mob is not available for the active game and profile.', 'wrong_game');
            }
            if ($mob->getCommand() === '') {
                return WorkerResult::failure('That mob is not configured correctly.', 'interaction_not_found', 500);
            }

            $count += $requestedCount;
            $cost += $mob->getCost() * $requestedCount;
            $cooldown += $mob->getCooldown() * $requestedCount;
            for ($i = 0; $i < $requestedCount; $i++) {
                $commands[] = $mob->getCommand();
            }
        }

        if ($count < 1 || $count > 100) {
            return WorkerResult::failure('Choose between 1 and 100 total mobs.', 'invalid_quantity');
        }

        return $this->chargeAndSend($commands, $cost, max($powerSpawn->getCooldownMin(), min($powerSpawn->getCooldownMax(), $cooldown)));
    }

    private function processBatClaim(): WorkerResult {
        $batClaim = $this->workerContext->getGame()->getSpecialType('batClaim');
        if (!$batClaim instanceof BatClaim || !$batClaim->isEnabled() || !$this->workerContext->getProfile()->allowsSpecialInteraction('special', 'batClaim')) {
            return WorkerResult::failure('Bat claiming is not available for the active game and profile.', 'wrong_game');
        }

        $batName = $this->inputDataContext->getBatName();
        $effectiveName = $batName !== '' ? $batName : $this->user->getDisplayName() . "'s Pet";
        $length = function_exists('mb_strlen') ? mb_strlen($effectiveName, 'UTF-8') : strlen($effectiveName);
        if ($length > 32) {
            return WorkerResult::failure('Bat names may contain at most 32 characters.', 'invalid_text');
        }
        if (!$this->userTextPolicy->isAllowed($effectiveName)) {
            return WorkerResult::failure('That bat name is not allowed.', 'restricted_text');
        }

        if (!$this->interactionManager->viewerBatClaimed($effectiveName)) {
            return WorkerResult::failure('The bat could not be claimed.', 'interaction_failed', 502);
        }

        return new WorkerResult(true, 'Bat claimed.');
    }

    private function chargeAndSend(array $commands, int $cost, int $cooldown): WorkerResult {
        if ($this->user->getGemBalance() < $cost) {
            return WorkerResult::failure('You do not have enough Airo Gems.', 'insufficient_funds');
        }
        if ($cost > 0 && !$this->bank->subtractFromUserBalance($this->user->getUserId(), $cost)) {
            return WorkerResult::failure('Your Airo Gems could not be charged.', 'currency_error', 500);
        }
        if (!$this->interactionManager->sendCommands($this->workerContext->getServerId(), $commands)) {
            if ($cost > 0) {
                $this->bank->addToUserBalance($this->user->getUserId(), $cost);
            }
            return WorkerResult::failure('The interaction failed; your Airo Gems were refunded.', 'interaction_failed', 502);
        }

        $result = new WorkerResult(true, 'Interaction completed.');
        $result->addMeta('cost', $cost);
        $result->addMeta('cooldown', $cooldown);
        return $result;
    }

    public function createWorkerResult(string $protocolString): WorkerResult {
        return new WorkerResult($protocolString);
    }
}
