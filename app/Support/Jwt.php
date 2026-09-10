<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal HS256 JWT encode/decode. No external dependency — the algorithm
 * is small enough that a vendored library would add more surface area than
 * it saves, and CLAUDE.md's "no framework" instinct extends to not reaching
 * for a package where ~60 lines of stdlib hash_hmac does the job.
 */
final class Jwt
{
    public function __construct(
        private readonly string $secret,
    ) {
        if ($this->secret === '') {
            throw new RuntimeException('JWT_SECRET is not set.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function encode(array $claims): string
    {
        $header = $this->base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64UrlEncode((string) json_encode($claims));

        $signature = $this->sign("{$header}.{$payload}");

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * @return array<string, mixed>|null Null if the token is malformed, unsigned correctly, or expired.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        if (!hash_equals($this->sign("{$header}.{$payload}"), $signature)) {
            return null;
        }

        $claims = json_decode($this->base64UrlDecode($payload), true);

        if (!is_array($claims)) {
            return null;
        }

        if (isset($claims['exp']) && time() >= (int) $claims['exp']) {
            return null;
        }

        return $claims;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, binary: true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');

        return base64_decode($padded) ?: '';
    }
}
