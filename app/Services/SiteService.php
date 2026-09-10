<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Site;
use RuntimeException;

final class SiteService
{
    public function __construct(
        private readonly Plan $planModel,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $accountId): array
    {
        return (new Site($accountId))->all();
    }

    public function create(int $accountId, string $planKey, string $domain, array $attributes = []): int
    {
        $site = new Site($accountId);
        $this->assertUnderSiteLimit($accountId, $planKey, $site);

        return $site->create(['domain' => $domain, ...$attributes]);
    }

    private function assertUnderSiteLimit(int $accountId, string $planKey, Site $site): void
    {
        $plan = $this->planModel->findByKey($planKey);

        if ($plan === null) {
            throw new RuntimeException("Unknown plan_key: {$planKey}");
        }

        $currentCount = count($site->all());

        if ($currentCount >= (int) $plan['site_limit']) {
            throw new RuntimeException(
                "Site limit reached for plan '{$planKey}' ({$plan['site_limit']} sites). Upgrade to add more."
            );
        }
    }
}
