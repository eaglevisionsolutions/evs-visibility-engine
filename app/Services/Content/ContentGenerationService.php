<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentQueue;
use App\Models\Site;
use App\Services\Notifications\NotifierInterface;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the queued -> writing -> ready_for_review (or error) leg of
 * content_queue's status machine (CLAUDE.md). Runs from the worker as the
 * `generate_content_draft` job.
 */
final class ContentGenerationService
{
    public function __construct(
        private readonly ContentGeneratorInterface $generator,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function generate(int $accountId, int $siteId, int $contentQueueId): void
    {
        $contentQueue = new ContentQueue($accountId);
        $row = $contentQueue->find($contentQueueId);

        if ($row === null) {
            throw new RuntimeException("content_queue row {$contentQueueId} not found for account {$accountId}.");
        }

        $site = (new Site($accountId))->find($siteId);

        if ($site === null) {
            throw new RuntimeException("Site {$siteId} not found for account {$accountId}.");
        }

        $contentQueue->update($contentQueueId, ['status' => 'writing']);

        try {
            $draft = $this->generator->generateDraft($row['keyword'], $site['domain']);
        } catch (Throwable $e) {
            $contentQueue->update($contentQueueId, ['status' => 'error']);
            throw $e;
        }

        $contentQueue->update($contentQueueId, [
            'status' => 'ready_for_review',
            'seo_title' => $draft['seo_title'],
            'meta_desc' => $draft['meta_desc'],
            'h1' => $draft['h1'],
            'outline' => $draft['outline'],
            'content_html' => $draft['content_html'],
            'slug' => $draft['slug'],
        ]);

        $this->notifier->notify('content.ready_for_review', [
            'content_queue_id' => $contentQueueId,
            'keyword' => $row['keyword'],
            'site_domain' => $site['domain'],
            'seo_title' => $draft['seo_title'],
        ]);
    }
}
