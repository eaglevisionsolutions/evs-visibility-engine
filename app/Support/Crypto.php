<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Symmetric encryption for at-rest secrets (GSC refresh tokens, WP
 * application passwords) using libsodium — bundled with PHP core since
 * 7.2, so no dependency needed. APP_KEY must be a base64-encoded 32-byte
 * key (generate with: php -r "echo base64_encode(sodium_crypto_secretbox_keygen());").
 */
final class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(string $encoded): ?string
    {
        $key = self::key();
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        return $plaintext === false ? null : $plaintext;
    }

    private static function key(): string
    {
        $encoded = env('APP_KEY', '');

        if ($encoded === '') {
            throw new RuntimeException(
                'APP_KEY is not set. Generate one with: '
                . 'php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"'
            );
        }

        $key = base64_decode((string) $encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }
}
