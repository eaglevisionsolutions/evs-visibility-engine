<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Support\HttpClient;
use RuntimeException;

final class ClaudeContentGenerator implements ContentGeneratorInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_TOKENS = 4096;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an SEO content writer. Given a target keyword and a site's
        domain, draft a complete blog post optimized for that keyword.

        Respond with ONLY a single JSON object — no markdown fences, no prose
        before or after — matching exactly this shape:
        {
          "seo_title": string (under 60 chars),
          "meta_desc": string (under 155 chars),
          "h1": string,
          "outline": array of strings (section headings, 4-8 items),
          "content_html": string (full article body as semantic HTML: <p>,
            <h2>, <h3>, <ul>/<li> as appropriate — no <html>/<body> wrapper),
          "slug": string (lowercase, hyphenated, under 60 chars)
        }
        PROMPT;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generateDraft(string $keyword, string $siteDomain): array
    {
        $response = HttpClient::postJson(self::ENDPOINT, [
            'model' => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [[
                'role' => 'user',
                'content' => "Target keyword: {$keyword}\nSite domain: {$siteDomain}",
            ]],
        ], [
            "x-api-key: {$this->apiKey}",
            'anthropic-version: ' . self::API_VERSION,
        ]);

        if ($response['status'] !== 200) {
            throw new RuntimeException(
                'Claude API request failed: ' . ($response['body']['error']['message'] ?? 'unknown error')
            );
        }

        $text = $response['body']['content'][0]['text'] ?? null;

        if (!is_string($text)) {
            throw new RuntimeException('Claude API response had no text content block.');
        }

        $draft = json_decode(trim($text), true);

        if (!is_array($draft) || !isset($draft['seo_title'], $draft['content_html'], $draft['slug'])) {
            throw new RuntimeException('Claude API response was not the expected JSON draft shape.');
        }

        return [
            'seo_title' => $draft['seo_title'],
            'meta_desc' => $draft['meta_desc'] ?? '',
            'h1' => $draft['h1'] ?? $draft['seo_title'],
            'outline' => $draft['outline'] ?? [],
            'content_html' => $draft['content_html'],
            'slug' => $draft['slug'],
        ];
    }
}
