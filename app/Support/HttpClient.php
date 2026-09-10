<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Thin cURL wrapper shared by every outbound integration (Google OAuth/GSC,
 * Claude API, WordPress REST). No HTTP client dependency — cURL is a
 * standard PHP extension on any cPanel/CyberPanel host, and these three
 * integrations don't need anything more than JSON/form POST and GET.
 */
final class HttpClient
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function postJson(string $url, array $data, array $headers = []): array
    {
        return self::request('POST', $url, json_encode($data), [
            'Content-Type: application/json',
            ...$headers,
        ]);
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function postForm(string $url, array $data, array $headers = []): array
    {
        return self::request('POST', $url, http_build_query($data), [
            'Content-Type: application/x-www-form-urlencoded',
            ...$headers,
        ]);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function get(string $url, array $headers = []): array
    {
        return self::request('GET', $url, null, $headers);
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request to {$url} failed: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['raw' => $raw]];
    }
}
