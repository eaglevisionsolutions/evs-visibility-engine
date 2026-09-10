<?php

declare(strict_types=1);

/**
 * Loads root .env, then merges .env.{APP_ENV} on top if present.
 * Included by both web entry points (public/index.php) and CLI scripts
 * (migrations/migrate.php, workers/worker.php) — anything that needs env()
 * requires this file directly, since there's no framework bootstrapping it.
 */

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($value !== '' && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

$rootPath = dirname(__DIR__, 2);

loadEnvFile($rootPath . '/.env');

$appEnv = env('APP_ENV', 'local');
$overlay = match ($appEnv) {
    'staging' => '.env.staging',
    'production' => '.env.production',
    default => '.env.local',
};

loadEnvFile($rootPath . '/' . $overlay);
