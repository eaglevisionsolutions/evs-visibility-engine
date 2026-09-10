<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * One provider today (Stripe), but the contract is written as an interface
 * per CLAUDE.md's billing discipline — the same provider-adapter instinct
 * as CompetitorDataProvider/PublisherInterface in later phases. Nothing
 * outside a concrete provider (e.g. StripeBillingProvider) may reference
 * a vendor SDK type or a raw price ID; everything routes through plan_key.
 */
interface BillingProviderInterface
{
    /** @return list<array<string, mixed>> Rows from the `plans` table. */
    public function getAvailablePlans(): array;

    /** @return array{checkout_url: string} */
    public function subscribe(int $accountId, string $planKey): array;

    /** @return array<string, mixed>|null Latest local subscription row, or null if never subscribed. */
    public function getSubscriptionStatus(int $accountId): ?array;

    /** @return array{portal_url: string} */
    public function cancelOrManageSubscription(int $accountId): array;

    /**
     * Verifies the webhook signature and returns event info the caller
     * (StripeWebhookController) uses to decide whether to enqueue a
     * `sync_stripe_subscription` job. Does not write to the database
     * itself — see syncSubscriptionForCustomer(), which the worker calls,
     * so the actual sync is idempotent and retryable.
     *
     * @return array{type: string, stripe_customer_id: ?string}
     */
    public function handleWebhookEvent(string $payload, string $signature): array;

    /**
     * Re-fetches the customer's current subscription state from Stripe and
     * upserts the local `subscriptions` row + accounts.plan_key. Always
     * reflects live Stripe state rather than applying an incremental diff,
     * so running it twice for the same customer is a no-op the second time.
     */
    public function syncSubscriptionForCustomer(string $stripeCustomerId): void;
}
