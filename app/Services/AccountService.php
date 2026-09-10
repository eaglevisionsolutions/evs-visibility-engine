<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use RuntimeException;

final class AccountService
{
    public function __construct(
        private readonly Account $accountModel,
    ) {
    }

    public function get(int $accountId): array
    {
        $account = $this->accountModel->find($accountId);

        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }

        return $account;
    }
}
