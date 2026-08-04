<?php

class CurrencyWorker implements WorkerInterface {
    private const RANDOM_AWARD_TRIGGER = -2147483648;
    private const RANDOM_AWARD_MIN = 1;
    private const RANDOM_AWARD_MAX = 5;

    private InputDataContext $inputDataContext;

    public function __construct(
        private UserContext $user,
        private MySqlManager $bank
    ) { }

    public function prime(WorkerContext $workerContext, InputDataContext $inputDataContext): void {
        $this->inputDataContext = $inputDataContext;
    }

    public function process(): WorkerResult {
        if (!$this->user->userLoggedIn()) {
            return WorkerResult::failureCode( ResponseLibrary::R_CURRENCY__USER_NOT_FOUND, [ ], 404 );
        }
        $action = $this->inputDataContext->getAction();
        $requestedAmount = $this->inputDataContext->getAmount();
        $isRandomAward = $action === 'addToUser' && $requestedAmount === self::RANDOM_AWARD_TRIGGER;
        $amount = $isRandomAward
            ? random_int(self::RANDOM_AWARD_MIN, self::RANDOM_AWARD_MAX)
            : $requestedAmount;

        $commonMeta = [
            'action' => $action,
            'amount' => $amount,
            'requestedAmount' => $requestedAmount,
            'randomAward' => $isRandomAward,
        ];

        return match($action) {
            'checkBalance' => $this->balanceResult($commonMeta),
            'setUserBalance' => $this->setBalanceResult($amount, $commonMeta),
            'addToUser' => $this->addBalanceResult($amount, $commonMeta),
            'deductCost' => $this->deductBalanceResult($amount, $commonMeta),
            default => WorkerResult::failureCode(
                ResponseLibrary::R_CURRENCY__INVALID_ACTION,
                $commonMeta,
            ),
        };
    }

    // PRIVATE FUNCTIONS
    private function checkUserBalance(): int {
        $balance = $this->user->getGemBalance();
        if ($balance < 0) {
            (new Logger())->warning('User balance is missing', ['username' => $this->user->getDisplayName()]);
        }
        return $balance;
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

    private function balanceResult(array $meta): WorkerResult {
        $balance = $this->checkUserBalance();
        return WorkerResult::success(
            ResponseLibrary::R_CURRENCY__BALANCE_CHECK,
            $balance,
            $meta + [ 'totalGems' => $balance ],
        );
    }

    private function setBalanceResult(int $amount, array $meta): WorkerResult {
        if (!$this->setUserBalance($amount)) {
            return WorkerResult::failureCode(
                ResponseLibrary::R_CURRENCY__UPDATE_FAILED,
                $meta,
                500,
            );
        }
        return WorkerResult::success(
            ResponseLibrary::R_CURRENCY__BALANCE_SET,
            $amount,
            $meta + [ 'totalGems' => $amount ],
        );
    }

    private function addBalanceResult(int $amount, array $meta): WorkerResult {
        $previousBalance = $this->user->getGemBalance();
        $newBalance = $this->addToUser($amount);
        if ($newBalance === false) {
            return WorkerResult::failureCode(
                ResponseLibrary::R_CURRENCY__UPDATE_FAILED,
                $meta,
                500,
            );
        }
        return WorkerResult::success(
            ResponseLibrary::R_CURRENCY__GEMS_ADD,
            $newBalance,
            $meta + [
                'foundGems' => $newBalance - $previousBalance,
                'totalGems' => $newBalance,
            ],
        );
    }

    private function deductBalanceResult(int $amount, array $meta): WorkerResult {
        $balance = $this->user->getGemBalance();
        $details = $meta + [ 'spentGems' => $amount, 'totalGems' => $balance ];
        if ($amount < 0 || $balance < $amount) {
            return WorkerResult::failureCode(
                ResponseLibrary::R_CURRENCY__INSUFFICIENT_FUNDS,
                $details,
            );
        }
        if (!$this->deductCost($amount)) {
            return WorkerResult::failureCode(
                ResponseLibrary::R_CURRENCY__UPDATE_FAILED,
                $details,
                500,
            );
        }

        $newBalance = $balance - $amount;
        return WorkerResult::success(
            ResponseLibrary::R_CURRENCY__GEMS_DEDUCTED,
            $newBalance,
            $meta + [ 'spentGems' => $amount, 'totalGems' => $newBalance ],
        );
    }
}
