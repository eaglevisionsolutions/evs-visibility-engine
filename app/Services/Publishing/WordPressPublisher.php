<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Support\HttpClient;
use RuntimeException;

/**
 * Publishes as a WordPress DRAFT via the REST API (Application Passwords
 * auth), not a live post — CLAUDE.md's own done-when criterion for Phase 2
 * is "a published WP draft", i.e. handed off into WordPress for a human to
 * hit Publish there, not auto-published live. Our own approve step
 * already gates this job from running at all; the WP-side draft state is
 * a second, independent safety margin.
 */
final class WordPressPublisher implements PublisherInterface
{
    public function publish(array $contentQueueRow, array $site): array
    {
        if (empty($site['wp_url']) || empty($site['wp_username']) || empty($site['wp_app_password'])) {
            throw new RuntimeException('Site has no WordPress credentials configured.');
        }

        $endpoint = rtrim($site['wp_url'], '/') . '/wp-json/wp/v2/posts';
        $auth = base64_encode($site['wp_username'] . ':' . $site['wp_app_password']);

        $response = HttpClient::postJson($endpoint, [
            'title' => $contentQueueRow['seo_title'],
            'content' => $contentQueueRow['content_html'],
            'excerpt' => $contentQueueRow['meta_desc'] ?? '',
            'slug' => $contentQueueRow['slug'],
            'status' => 'draft',
        ], [
            "Authorization: Basic {$auth}",
        ]);

        if ($response['status'] !== 201 || empty($response['body']['link'])) {
            throw new RuntimeException(
                'WordPress publish failed: ' . ($response['body']['message'] ?? 'unknown error')
            );
        }

        return ['published_url' => $response['body']['link']];
    }
}
