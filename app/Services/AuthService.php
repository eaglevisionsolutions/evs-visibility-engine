<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Support\Jwt;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly Account $accountModel,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
    ) {
    }

    /**
     * @return array{account_id: int, user_id: int, access_token: string, refresh_token: string}
     */
    public function register(string $accountName, string $email, string $password, string $planKey): array
    {
        if (User::findByEmailUnscoped($email) !== null) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $accountId = $this->accountModel->create($accountName, $planKey);

        $user = new User($accountId);
        $userId = $user->create([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'owner',
        ]);

        return $this->issueTokenPair($accountId, $userId);
    }

    /**
     * @return array{account_id: int, user_id: int, access_token: string, refresh_token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::findByEmailUnscoped($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
        }

        return $this->issueTokenPair((int) $user['account_id'], (int) $user['id']);
    }

    /**
     * @return array{access_token: string}
     */
    public function refresh(string $refreshToken): array
    {
        $claims = $this->jwt->decode($refreshToken);

        if ($claims === null || ($claims['type'] ?? null) !== 'refresh') {
            throw new RuntimeException('Invalid or expired refresh token.');
        }

        $accessToken = $this->issueAccessToken((int) $claims['account_id'], (int) $claims['user_id']);

        return ['access_token' => $accessToken];
    }

    /**
     * @return array{account_id: int, user_id: int, access_token: string, refresh_token: string}
     */
    private function issueTokenPair(int $accountId, int $userId): array
    {
        return [
            'account_id' => $accountId,
            'user_id' => $userId,
            'access_token' => $this->issueAccessToken($accountId, $userId),
            'refresh_token' => $this->issueRefreshToken($accountId, $userId),
        ];
    }

    private function issueAccessToken(int $accountId, int $userId): string
    {
        return $this->jwt->encode([
            'type' => 'access',
            'account_id' => $accountId,
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $this->accessTtl,
        ]);
    }

    private function issueRefreshToken(int $accountId, int $userId): string
    {
        return $this->jwt->encode([
            'type' => 'refresh',
            'account_id' => $accountId,
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $this->refreshTtl,
        ]);
    }
}
