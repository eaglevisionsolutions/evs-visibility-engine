<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Models\ContentQueue;
use App\Models\Site;
use App\Services\Notifications\NotifierInterface;
use RuntimeException;

/**
 * Orchestrates the approved -> published leg of content_queue's status
 * machine. Runs from the worker as the `publish_content` job.
 */
final class PublishingService
{
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function publish(int $accountId, int $siteId, int $contentQueueId): void
    {
        $contentQueue = new ContentQueue($accountId);
        $row = $contentQueue->find($contentQueueId);

        if ($row === null) {
            throw new RuntimeException("content_queue row {$contentQueueId} not found for account {$accountId}.");
        }

        if ($row['status'] !== 'approved') {
            throw new RuntimeException(
                "content_queue row {$contentQueueId} is '{$row['status']}', not 'approved' — refusing to publish."
            );
        }

        $site = (new Site($accountId))->find($siteId);

        if ($site === null) {
            throw new RuntimeException("Site {$siteId} not found for account {$accountId}.");
        }

        try {
            $result = $this->publisher->publish($row, $site);
        } catch (\Throwable $e) {
            $contentQueue->update($contentQueueId, ['status' => 'error']);
            throw $e;
        }

        $contentQueue->update($contentQueueId, [
            'status' => 'published',
            'published_url' => $result['published_url'],
        ]);

        $this->notifier->notify('content.published', [
            'content_queue_id' => $contentQueueId,
            'keyword' => $row['keyword'],
            'site_domain' => $site['domain'],
            'published_url' => $result['published_url'],
        ]);
    }
}
