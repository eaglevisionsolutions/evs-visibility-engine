<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use RuntimeException;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

final class StripeBillingProvider implements BillingProviderInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly Account $accountModel,
        private readonly Plan $planModel,
        private readonly string $webhookSecret,
        private readonly string $appUrl,
    ) {
    }

    public function getAvailablePlans(): array
    {
        return $this->planModel->all();
    }

    public function subscribe(int $accountId, string $planKey): array
    {
        $account = $this->accountModel->find($accountId);

        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }

        $plan = $this->planModel->findByKey($planKey);

        if ($plan === null) {
            throw new RuntimeException("Unknown plan_key: {$planKey}");
        }

        $stripeCustomerId = $account['stripe_customer_id'] ?? null;

        if ($stripeCustomerId === null) {
            $customer = $this->stripe->customers->create([
                'name' => $account['name'],
                'metadata' => ['account_id' => (string) $accountId],
            ]);
            $stripeCustomerId = $customer->id;
            $this->accountModel->updateStripeCustomerId($accountId, $stripeCustomerId);
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $stripeCustomerId,
            'line_items' => [[
                'price' => $plan['stripe_price_id'],
                'quantity' => 1,
            ]],
            'success_url' => "{$this->appUrl}/billing/success?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$this->appUrl}/billing/cancelled",
            'metadata' => ['account_id' => (string) $accountId, 'plan_key' => $planKey],
        ]);

        return ['checkout_url' => $session->url];
    }

    public function getSubscriptionStatus(int $accountId): ?array
    {
        return (new Subscription($accountId))->current();
    }

    public function cancelOrManageSubscription(int $accountId): array
    {
        $account = $this->accountModel->find($accountId);

        if ($account === null || empty($account['stripe_customer_id'])) {
            throw new RuntimeException('Account has no billing profile yet.');
        }

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $account['stripe_customer_id'],
            'return_url' => "{$this->appUrl}/billing",
        ]);

        return ['portal_url' => $session->url];
    }

    public function handleWebhookEvent(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (\Throwable $e) {
            throw new RuntimeException('Invalid webhook signature: ' . $e->getMessage(), previous: $e);
        }

        $object = $event->data->object ?? null;
        $stripeCustomerId = is_object($object) && isset($object->customer)
            ? (string) $object->customer
            : null;

        return [
            'type' => $event->type,
            'stripe_customer_id' => $stripeCustomerId,
        ];
    }

    public function syncSubscriptionForCustomer(string $stripeCustomerId): void
    {
        $customer = $this->stripe->customers->retrieve($stripeCustomerId);
        $accountId = (int) ($customer->metadata['account_id'] ?? 0);

        if ($accountId <= 0) {
            throw new RuntimeException("Stripe customer {$stripeCustomerId} has no account_id metadata.");
        }

        $subscriptions = $this->stripe->subscriptions->all([
            'customer' => $stripeCustomerId,
            'status' => 'all',
            'limit' => 1,
        ]);

        $latest = $subscriptions->data[0] ?? null;

        $subscriptionModel = new Subscription($accountId);
        $existing = $subscriptionModel->current();

        if ($latest === null) {
            return;
        }

        $planKey = $this->resolvePlanKeyFromStripeSubscription($latest);

        $data = [
            'plan_key' => $planKey,
            'status' => $latest->status,
            'current_period_end' => date('Y-m-d H:i:s', $latest->current_period_end),
        ];

        if ($existing === null) {
            $subscriptionModel->create($data);
        } else {
            $subscriptionModel->update((int) $existing['id'], $data);
        }

        if ($planKey !== null && $latest->status === StripeSubscription::STATUS_ACTIVE) {
            $this->accountModel->updatePlan($accountId, $planKey);
        }
    }

    private function resolvePlanKeyFromStripeSubscription(StripeSubscription $subscription): ?string
    {
        $priceId = $subscription->items->data[0]->price->id ?? null;

        if ($priceId === null) {
            return null;
        }

        foreach ($this->planModel->all() as $plan) {
            if ($plan['stripe_price_id'] === $priceId) {
                return $plan['plan_key'];
            }
        }

        return null;
    }
}
