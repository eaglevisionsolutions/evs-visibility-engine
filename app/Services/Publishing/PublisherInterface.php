<?php

declare(strict_types=1);

namespace App\Services\Publishing;

/**
 * WordPressPublisher is the only implementation in Phase 2; CLAUDE.md
 * names ShopifyPublisher etc. as later additions behind this same
 * interface — the swappable-provider pattern used throughout (billing,
 * content generation, and eventually CompetitorDataProvider in Phase 5).
 */
interface PublisherInterface
{
    /**
     * @param array<string, mixed> $contentQueueRow
     * @param array<string, mixed> $site
     * @return array{published_url: string}
     */
    public function publish(array $contentQueueRow, array $site): array;
}
