<?php

class InteractionWorker implements WorkerInterface {
    private WorkerContext $workerContext;
    private InputDataContext $inputDataContext;

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
        $interactionMeta = [ 'interaction' => $action, 'interactionType' => $type ];

        if (!$this->user->userLoggedIn()) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__LOGIN_REQUIRED, $interactionMeta, 401 );
        }

        if ($type === 'powerSpawn') {
            return $this->processPowerSpawn();
        }

        if ($type === 'special' && $action === 'batClaim') {
            return $this->processBatClaim();
        }

        if (!$this->workerContext->getProfile()->allowsSimpleInteraction($type, $action)) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__WRONG_GAME, $interactionMeta );
        }

        $simpleType = $this->workerContext->getGame()->getSimpleType($type);
        $interaction = $simpleType?->getInteraction($action);
        if ($interaction === null) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__NOT_FOUND, $interactionMeta, 404 );
        }

        if (!$interaction->isEnabled()) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__DISABLED, $interactionMeta );
        }

        $duration = max(1, min(30, $this->inputDataContext->getDuration()));
        $costPerSecond = max(0, $interaction->getCost());
        $cost = $duration <= 10
            ? $costPerSecond * $duration
            : (10 * $costPerSecond) + (($duration - 10) * ($costPerSecond * 10));
        $cooldown = $interaction->getCooldown() + max(0, $duration - 10);

        $details = [
            '{DURATION}' => $duration,
            '{COST}' => $cost,
            '{COOLDOWN}' => $cooldown,
        ];
        $commandsToSend = $interaction->getCommandArray($details);
        return $this->chargeAndSend(
            $commandsToSend,
            $cost,
            $cooldown,
            ResponseLibrary::R_INTERACTION__COMPLETED,
            $interactionMeta + [
                'duration' => $duration,
            ],
        );
    }

    private function processPowerSpawn(): WorkerResult {
        $powerSpawn = $this->workerContext->getGame()->getSpecialType('powerSpawn');
        if (!$powerSpawn instanceof PowerSpawn) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__NOT_FOUND, [
                'interaction' => 'powerSpawn',
            ], 404 );
        }

        $commands = [];
        $cost = 0;
        $count = 0;
        $cooldown = 0;

        foreach ($this->inputDataContext->getMobs() as $mobName => $requestedCount) {
            $mobName = (string) $mobName;
            $requestedCount = filter_var($requestedCount, FILTER_VALIDATE_INT);
            if ($requestedCount === false || $requestedCount < 0 || $requestedCount > 25) {
                return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__INVALID_QUANTITY, [
                    'mobCount' => $count,
                ] );
            }
            if ($requestedCount === 0) {
                continue;
            }

            $mob = $powerSpawn->getMobs()[$mobName] ?? null;
            if (!$mob instanceof Mob || !$mob->isEnabled() || !$this->workerContext->getProfile()->allowsSpecialInteraction('powerSpawn', $mobName)) {
                return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__WRONG_GAME, [
                    'interaction' => $mobName,
                ] );
            }
            if ($mob->getCommand() === '') {
                return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__NOT_FOUND, [
                    'interaction' => $mobName,
                ], 500 );
            }

            $count += $requestedCount;
            $cost += $mob->getCost() * $requestedCount;
            $cooldown += $mob->getCooldown() * $requestedCount;
            for ($i = 0; $i < $requestedCount; $i++) {
                $commands[] = $mob->getCommand();
            }
        }

        if ($count < 1 || $count > 100) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__INVALID_QUANTITY, [
                'mobCount' => $count,
            ] );
        }

        return $this->chargeAndSend(
            $commands,
            $cost,
            max($powerSpawn->getCooldownMin(), min($powerSpawn->getCooldownMax(), $cooldown)),
            ResponseLibrary::R_INTERACTION__POWER_SPAWN_COMPLETED,
            [ 'interaction' => 'powerSpawn', 'mobCount' => $count ],
        );
    }

    private function processBatClaim(): WorkerResult {
        $batClaim = $this->workerContext->getGame()->getSpecialType('batClaim');
        if (!$batClaim instanceof BatClaim || !$batClaim->isEnabled() || !$this->workerContext->getProfile()->allowsSpecialInteraction('special', 'batClaim')) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__WRONG_GAME, [
                'interaction' => 'batClaim',
            ] );
        }

        $batName = $this->inputDataContext->getBatName();
        $effectiveName = $batName !== '' ? $batName : $this->user->getDisplayName() . "'s Pet";
        $length = function_exists('mb_strlen') ? mb_strlen($effectiveName, 'UTF-8') : strlen($effectiveName);
        if ($length > 32) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__INVALID_TEXT, [
                'batName' => $effectiveName,
                'textLength' => $length,
            ] );
        }
        if (!$this->userTextPolicy->isAllowed($effectiveName)) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__RESTRICTED_TEXT, [
                'batName' => $effectiveName,
            ] );
        }

        if (!$this->interactionManager->viewerBatClaimed($effectiveName)) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__BAT_CLAIM_FAILED, [
                'batName' => $effectiveName,
            ], 502 );
        }

        return WorkerResult::success( ResponseLibrary::R_INTERACTION__BAT_CLAIMED, true, [
            'batName' => $effectiveName,
        ] );
    }

    private function chargeAndSend(
        array $commands,
        int $cost,
        int $cooldown,
        string $successCode,
        array $meta = [ ],
    ): WorkerResult {
        if ($this->user->getGemBalance() < $cost) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__INSUFFICIENT_FUNDS, $meta + [
                'cost' => $cost,
                'totalGems' => $this->user->getGemBalance(),
            ] );
        }
        if ($cost > 0 && !$this->bank->subtractFromUserBalance($this->user->getUserId(), $cost)) {
            return WorkerResult::failureCode( ResponseLibrary::R_INTERACTION__CURRENCY_ERROR, $meta + [
                'cost' => $cost,
                'totalGems' => $this->user->getGemBalance(),
            ], 500 );
        }
        if (!$this->interactionManager->sendCommands($this->workerContext->getServerId(), $commands)) {
            $refunded = true;
            if ($cost > 0) {
                $refunded = $this->bank->addToUserBalance($this->user->getUserId(), $cost) !== false;
            }
            return WorkerResult::failureCode(
                match ( true ) {
                    $cost === 0 => ResponseLibrary::R_INTERACTION__FAILED,
                    $refunded => ResponseLibrary::R_INTERACTION__FAILED_REFUNDED,
                    default => ResponseLibrary::R_INTERACTION__FAILED_REFUND_ERROR,
                },
                $meta + [
                    'cost' => $cost,
                    'totalGems' => $refunded
                        ? $this->user->getGemBalance()
                        : $this->user->getGemBalance() - $cost,
                    'refunded' => $refunded,
                ],
                $cost > 0 && !$refunded ? 500 : 502,
            );
        }

        return WorkerResult::success( $successCode, true, $meta + [
            'cost' => $cost,
            'cooldown' => $cooldown,
            'totalGems' => $this->user->getGemBalance() - $cost,
        ] );
    }

}
