<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Support\Jwt;

/**
 * Resolves account_id/user_id from the Bearer token and attaches them to
 * the Request as attributes. This is the only place account_id is allowed
 * to enter a request's handling — controllers must read it from
 * $request->accountId(), never accept it as a route param or body field,
 * or tenant isolation depends on the client behaving honestly.
 */
final class JwtAuthMiddleware
{
    public function __construct(
        private readonly Jwt $jwt,
    ) {
    }

    public function handle(Request $request): Request|Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return Response::error('Missing bearer token', 401);
        }

        $claims = $this->jwt->decode($token);

        if ($claims === null) {
            return Response::error('Invalid or expired token', 401);
        }

        if (($claims['type'] ?? null) !== 'access') {
            return Response::error('Refresh tokens cannot be used to authenticate requests', 401);
        }

        if (!isset($claims['account_id'], $claims['user_id'])) {
            return Response::error('Malformed token claims', 401);
        }

        return $request
            ->withAttribute('account_id', (int) $claims['account_id'])
            ->withAttribute('user_id', (int) $claims['user_id']);
    }
}
