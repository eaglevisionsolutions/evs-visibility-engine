<?php

declare(strict_types=1);

/**
 * Long-running CLI worker: polls the `jobs` table and dispatches by type.
 * Run via supervisor (or cron + lock file) on the VPS — see CLAUDE.md.
 * The dispatcher is a plain match expression, not a handler-class-per-type
 * abstraction — that's premature with one real job type and one no-op.
 *
 * Usage: php workers/worker.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/env.php';

use App\Models\Account;
use App\Models\Job;
use App\Models\Plan;
use App\Services\Billing\StripeBillingProvider;
use Stripe\StripeClient;

$jobModel = new Job();

$billing = new StripeBillingProvider(
    new StripeClient((string) env('STRIPE_SECRET_KEY')),
    new Account(),
    new Plan(),
    (string) env('STRIPE_WEBHOOK_SECRET'),
    (string) env('APP_URL'),
);

$running = true;

if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    $shutdown = function () use (&$running): void {
        $running = false;
    };
    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGINT, $shutdown);
}

/**
 * @param array<string, mixed> $job
 */
function dispatchJob(array $job, StripeBillingProvider $billing): void
{
    $payload = json_decode($job['payload'], true) ?? [];

    match ($job['type']) {
        // Proves the loop works end to end: claim -> dispatch -> complete,
        // with no side effects beyond that.
        'noop_test' => null,
        'sync_stripe_subscription' => $billing->syncSubscriptionForCustomer((string) $payload['stripe_customer_id']),
        default => throw new \RuntimeException("Unknown job type: {$job['type']}"),
    };
}

echo "Worker started. Polling every " . env('WORKER_POLL_INTERVAL_SECONDS', 5) . "s.\n";

while ($running) {
    $job = $jobModel->claimNext();

    if ($job === null) {
        sleep((int) env('WORKER_POLL_INTERVAL_SECONDS', 5));
        continue;
    }

    echo "[{$job['id']}] running {$job['type']}\n";

    try {
        dispatchJob($job, $billing);
        $jobModel->markCompleted((int) $job['id']);
        echo "[{$job['id']}] completed\n";
    } catch (\Throwable $e) {
        $jobModel->markFailed((int) $job['id'], $e->getMessage());
        echo "[{$job['id']}] failed: {$e->getMessage()}\n";
    }
}

echo "Worker shutting down.\n";
