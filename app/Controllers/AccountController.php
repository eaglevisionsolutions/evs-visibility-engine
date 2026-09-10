<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AccountService;
use RuntimeException;

final class AccountController
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    /**
     * Always operates on $request->accountId() (resolved from the JWT by
     * JwtAuthMiddleware) — there is no route param for account id, so a
     * caller can never ask for another account's data by editing a URL.
     */
    public function show(Request $request): Response
    {
        try {
            $account = $this->accountService->get($request->accountId());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 404);
        }

        return Response::json($account);
    }
}
