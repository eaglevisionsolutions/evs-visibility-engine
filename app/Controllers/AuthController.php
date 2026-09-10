<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use RuntimeException;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function register(Request $request): Response
    {
        $body = $request->body;

        foreach (['account_name', 'email', 'password', 'plan_key'] as $field) {
            if (empty($body[$field])) {
                return Response::error("Missing required field: {$field}", 422);
            }
        }

        try {
            $result = $this->authService->register(
                $body['account_name'],
                $body['email'],
                $body['password'],
                $body['plan_key'],
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 409);
        }

        return Response::json($result, 201);
    }

    public function login(Request $request): Response
    {
        $body = $request->body;

        if (empty($body['email']) || empty($body['password'])) {
            return Response::error('Missing email or password', 422);
        }

        try {
            $result = $this->authService->login($body['email'], $body['password']);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 401);
        }

        return Response::json($result);
    }

    public function refresh(Request $request): Response
    {
        $body = $request->body;

        if (empty($body['refresh_token'])) {
            return Response::error('Missing refresh_token', 422);
        }

        try {
            $result = $this->authService->refresh($body['refresh_token']);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 401);
        }

        return Response::json($result);
    }
}
