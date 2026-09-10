<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Models\ContentQueue;
use App\Models\Job;
use App\Models\KeywordMap;
use App\Models\Site;
use App\Support\HttpClient;
use RuntimeException;

/**
 * The "Find" pillar's own-opportunity half (CLAUDE.md): pulls a site's
 * Search Console query data and turns any keyword not already in
 * keyword_map into a queued content_queue row, then immediately enqueues
 * the draft-generation job for it. Competitor gaps are Phase 5.
 *
 * Idempotent by construction: KeywordMap::insertIfNew relies on a unique
 * (site_id, keyword) constraint, so re-running a sync for the same site
 * only acts on keywords it hasn't seen before.
 */
final class GscSyncService
{
    private const LOOKBACK_DAYS = 28;
    private const ROW_LIMIT = 1000;

    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly Job $jobModel,
    ) {
    }

    public function syncSite(int $accountId, int $siteId): void
    {
        $site = (new Site($accountId))->find($siteId);

        if ($site === null) {
            throw new RuntimeException("Site {$siteId} not found for account {$accountId}.");
        }

        if (empty($site['gsc_refresh_token']) || empty($site['gsc_property'])) {
            throw new RuntimeException("Site {$siteId} has no connected Google Search Console property.");
        }

        $accessToken = $this->oauth->refreshAccessToken($site['gsc_refresh_token']);
        $rows = $this->fetchSearchAnalytics($site['gsc_property'], $accessToken);

        $keywordMap = new KeywordMap($accountId);
        $contentQueue = new ContentQueue($accountId);

        foreach ($rows as $row) {
            $keyword = trim((string) ($row['keys'][0] ?? ''));

            if ($keyword === '') {
                continue;
            }

            if (!$keywordMap->insertIfNew($siteId, $keyword, 'gsc_gap')) {
                continue; // already tracked from a previous sync
            }

            $contentQueueId = $contentQueue->create([
                'site_id' => $siteId,
                'keyword' => $keyword,
                'status' => 'queued',
                'source' => 'own_opportunity',
            ]);

            $this->jobModel->enqueue('generate_content_draft', [
                'content_queue_id' => $contentQueueId,
                'account_id' => $accountId,
                'site_id' => $siteId,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSearchAnalytics(string $siteUrl, string $accessToken): array
    {
        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($siteUrl) . '/searchAnalytics/query';

        $response = HttpClient::postJson($endpoint, [
            'startDate' => date('Y-m-d', strtotime('-' . self::LOOKBACK_DAYS . ' days')),
            'endDate' => date('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit' => self::ROW_LIMIT,
        ], ["Authorization: Bearer {$accessToken}"]);

        if ($response['status'] !== 200) {
            throw new RuntimeException(
                'Search Console query failed: ' . ($response['body']['error']['message'] ?? 'unknown error')
            );
        }

        return $response['body']['rows'] ?? [];
    }
}
