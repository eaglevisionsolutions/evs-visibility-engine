<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Job;
use App\Services\Billing\BillingProviderInterface;
use RuntimeException;

/**
 * Verifies the signature, then enqueues a `sync_stripe_subscription` job
 * rather than writing to the database inline — keeps the webhook response
 * fast and makes the actual sync idempotent/retryable via the job queue
 * (see StripeBillingProvider::syncSubscriptionForCustomer).
 */
final class StripeWebhookController
{
    private const RELEVANT_EVENT_TYPES = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'invoice.payment_failed',
    ];

    public function __construct(
        private readonly BillingProviderInterface $billing,
        private readonly Job $jobModel,
    ) {
    }

    public function handle(Request $request): Response
    {
        $signature = $request->header('Stripe-Signature') ?? '';

        try {
            $event = $this->billing->handleWebhookEvent($request->rawBody, $signature);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }

        if (in_array($event['type'], self::RELEVANT_EVENT_TYPES, true) && $event['stripe_customer_id'] !== null) {
            $this->jobModel->enqueue('sync_stripe_subscription', [
                'stripe_customer_id' => $event['stripe_customer_id'],
            ]);
        }

        return Response::json(['received' => true]);
    }
}
