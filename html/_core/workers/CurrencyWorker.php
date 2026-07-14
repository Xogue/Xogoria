<?php

class CurrencyWorker implements WorkerInterface {
    private WorkerContext $workerContext;
    private InputDataContext $inputDataContext;

    public function __construct(
        private UserContext $user,
        private MySqlManager $bank
    ) { }

    public function prime(WorkerContext $workerContext, InputDataContext $inputDataContext): void {
        $this->workerContext = $workerContext;
        $this->inputDataContext = $inputDataContext;
    }

    public function process(): WorkerResult {
        if (!$this->user->userLoggedIn()) { return new WorkerResult(false); }
        $action = $this->inputDataContext->getAction();
        $amount = $this->inputDataContext->getAmount();

        $workerResult = match($action) {
            'checkBalance' => new WorkerResult($this->checkUserBalance()),
            'setUserBalance' => new WorkerResult($this->setUserBalance($amount)),
            'addToUser' => new WorkerResult($this->addToUser($amount)),
            'deductCost' => new WorkerResult($this->deductCost($amount)),
            default => new WorkerResult(false),
        };

        $workerResult->addMeta('action', $action);
        $workerResult->addMeta('amount', $amount);
        return $workerResult;
    }

    public function createWorkerResult(string $protocolString): WorkerResult {
        return new WorkerResult($protocolString);
    }

    // PRIVATE FUNCTIONS
    private function checkUserBalance(): string {
        $balance = $this->user->getGemBalance();
        if ($balance < 0) {
            (new Logger())->warning('User balance is missing', ['username' => $this->user->getDisplayName()]);
        }
        return (string)$balance;
    }
    private function setUserBalance(int $amount): bool {
        return $this->bank->setUserBalance($this->user->getUserId(), $amount);
    }

    private function addToUser(int $amount): bool | int {
        return $this->bank->addToUserBalance($this->user->getUserId(), $amount);
    }

    private function deductCost(int $cost): bool {
        if ($cost < 0 || $this->user->getGemBalance() < $cost) {
            return false;
        }
        return $this->bank->subtractFromUserBalance($this->user->getUserId(), $cost);
    }
}
