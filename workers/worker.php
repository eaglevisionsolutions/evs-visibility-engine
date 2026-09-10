<?php

declare(strict_types=1);

/**
 * Short-lived CLI batch worker, not a long-running daemon — cPanel/
 * CyberPanel hosting generally can't guarantee a supervised background
 * process stays up, so cron drives this instead (e.g. every minute).
 * Each run claims and processes up to WORKER_BATCH_SIZE pending jobs, then
 * exits. A file lock stops two cron-triggered runs from overlapping if a
 * batch takes longer than the cron interval. The dispatcher is a plain
 * match expression, not a handler-class-per-type abstraction — that's
 * premature at this job-type count.
 *
 * Usage: php workers/worker.php
 * Cron:  * * * * * php /path/to/workers/worker.php >> /path/to/storage/worker.log 2>&1
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/env.php';

use App\Models\Account;
use App\Models\Job;
use App\Models\Plan;
use App\Services\Billing\StripeBillingProvider;
use App\Services\Content\ClaudeContentGenerator;
use App\Services\Content\ContentGenerationService;
use App\Services\Gsc\GoogleOAuthService;
use App\Services\Gsc\GscSyncService;
use App\Services\Notifications\EmailNotifier;
use App\Services\Publishing\PublishingService;
use App\Services\Publishing\WordPressPublisher;
use App\Support\Jwt;
use App\Support\SmtpMailer;
use Stripe\StripeClient;

$lockDir = dirname(__DIR__) . '/storage';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0755, recursive: true);
}

$lockPath = $lockDir . '/worker.lock';
$lockHandle = fopen($lockPath, 'c');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Another worker run is still in progress. Exiting.\n";
    exit(0);
}

/**
 * @param array<string, mixed> $job
 * @param array<string, object> $services
 */
function dispatchJob(array $job, array $services): void
{
    $payload = json_decode($job['payload'], true) ?? [];

    match ($job['type']) {
        // Proves the batch works end to end: claim -> dispatch -> complete,
        // with no side effects beyond that.
        'noop_test' => null,

        'sync_stripe_subscription' => $services['billing']->syncSubscriptionForCustomer(
            (string) $payload['stripe_customer_id']
        ),

        'sync_gsc' => $services['gscSync']->syncSite(
            (int) $payload['account_id'],
            (int) $payload['site_id'],
        ),

        'generate_content_draft' => $services['contentGeneration']->generate(
            (int) $payload['account_id'],
            (int) $payload['site_id'],
            (int) $payload['content_queue_id'],
        ),

        'publish_content' => $services['publishing']->publish(
            (int) $payload['account_id'],
            (int) $payload['site_id'],
            (int) $payload['content_queue_id'],
        ),

        default => throw new \RuntimeException("Unknown job type: {$job['type']}"),
    };
}

$jobModel = new Job();
$jwt = new Jwt((string) env('JWT_SECRET'));

$notifier = new EmailNotifier(
    new SmtpMailer(
        (string) env('SMTP_HOST'),
        (int) env('SMTP_PORT', 587),
        (string) env('SMTP_ENCRYPTION', 'tls'),
        (string) env('SMTP_USERNAME'),
        (string) env('SMTP_PASSWORD'),
        (string) env('SMTP_FROM_EMAIL'),
        (string) env('SMTP_FROM_NAME'),
    ),
    (string) env('NOTIFICATION_EMAIL_TO'),
);

$services = [
    'billing' => new StripeBillingProvider(
        new StripeClient((string) env('STRIPE_SECRET_KEY')),
        new Account(),
        new Plan(),
        (string) env('STRIPE_WEBHOOK_SECRET'),
        (string) env('APP_URL'),
    ),
    'gscSync' => new GscSyncService(
        new GoogleOAuthService(
            $jwt,
            (string) env('GOOGLE_CLIENT_ID'),
            (string) env('GOOGLE_CLIENT_SECRET'),
            (string) env('GOOGLE_REDIRECT_URI'),
        ),
        $jobModel,
    ),
    'contentGeneration' => new ContentGenerationService(
        new ClaudeContentGenerator(
            (string) env('ANTHROPIC_API_KEY'),
            (string) env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        ),
        $notifier,
    ),
    'publishing' => new PublishingService(new WordPressPublisher(), $notifier),
];

$batchSize = (int) env('WORKER_BATCH_SIZE', 20);
$processed = 0;

while ($processed < $batchSize) {
    $job = $jobModel->claimNext();

    if ($job === null) {
        break;
    }

    echo "[{$job['id']}] running {$job['type']}\n";

    try {
        dispatchJob($job, $services);
        $jobModel->markCompleted((int) $job['id']);
        echo "[{$job['id']}] completed\n";
    } catch (\Throwable $e) {
        $jobModel->markFailed((int) $job['id'], $e->getMessage());
        echo "[{$job['id']}] failed: {$e->getMessage()}\n";
    }

    $processed++;
}

echo "Processed {$processed} job(s).\n";

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
