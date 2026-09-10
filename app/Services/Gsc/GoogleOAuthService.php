<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Support\HttpClient;
use App\Support\Jwt;
use RuntimeException;

/**
 * Handles the Google OAuth2 dance for per-site Search Console access.
 * The `state` param is a short-lived signed token (reusing Support/Jwt
 * rather than a new dependency) carrying site_id/account_id, so the
 * callback — which Google redirects to directly, with no Bearer token —
 * can still resolve which tenant the connection belongs to without a
 * database round trip or a client-supplied id it would have to trust blindly.
 */
final class GoogleOAuthService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
    private const STATE_TTL = 600;

    public function __construct(
        private readonly Jwt $jwt,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function buildAuthUrl(int $accountId, int $siteId): string
    {
        $state = $this->jwt->encode([
            'type' => 'gsc_oauth_state',
            'account_id' => $accountId,
            'site_id' => $siteId,
            'iat' => time(),
            'exp' => time() + self::STATE_TTL,
        ]);

        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::AUTH_ENDPOINT . '?' . $params;
    }

    /**
     * @return array{account_id: int, site_id: int}
     */
    public function verifyState(string $state): array
    {
        $claims = $this->jwt->decode($state);

        if ($claims === null || ($claims['type'] ?? null) !== 'gsc_oauth_state') {
            throw new RuntimeException('Invalid or expired OAuth state.');
        }

        return ['account_id' => (int) $claims['account_id'], 'site_id' => (int) $claims['site_id']];
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = HttpClient::postForm(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response['status'] !== 200 || empty($response['body']['refresh_token'])) {
            throw new RuntimeException(
                'Google token exchange failed: ' . ($response['body']['error_description'] ?? 'unknown error')
            );
        }

        return [
            'access_token' => $response['body']['access_token'],
            'refresh_token' => $response['body']['refresh_token'],
        ];
    }

    public function refreshAccessToken(string $refreshToken): string
    {
        $response = HttpClient::postForm(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response['status'] !== 200 || empty($response['body']['access_token'])) {
            throw new RuntimeException(
                'Google token refresh failed: ' . ($response['body']['error_description'] ?? 'unknown error')
            );
        }

        return $response['body']['access_token'];
    }
}
