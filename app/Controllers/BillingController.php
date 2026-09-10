<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\Billing\BillingProviderInterface;
use RuntimeException;

final class BillingController
{
    public function __construct(
        private readonly BillingProviderInterface $billing,
    ) {
    }

    public function plans(Request $request): Response
    {
        return Response::json(['plans' => $this->billing->getAvailablePlans()]);
    }

    public function subscribe(Request $request): Response
    {
        $body = $request->body;

        if (empty($body['plan_key'])) {
            return Response::error('Missing required field: plan_key', 422);
        }

        try {
            $result = $this->billing->subscribe($request->accountId(), $body['plan_key']);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 422);
        }

        return Response::json($result);
    }

    public function status(Request $request): Response
    {
        $subscription = $this->billing->getSubscriptionStatus($request->accountId());

        return Response::json(['subscription' => $subscription]);
    }

    public function portal(Request $request): Response
    {
        try {
            $result = $this->billing->cancelOrManageSubscription($request->accountId());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 422);
        }

        return Response::json($result);
    }
}
