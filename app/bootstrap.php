<?php

declare(strict_types=1);

/**
 * Wires up the request-handling dependency graph for public/index.php.
 * No framework/container here — CLAUDE.md rules out one, so this is a
 * plain function returning a fully-built Router. Kept out of index.php
 * itself so the front controller stays a one-line dispatch call.
 */

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\SiteController;
use App\Controllers\StripeWebhookController;
use App\Core\Middleware\JwtAuthMiddleware;
use App\Core\Router;
use App\Models\Account;
use App\Models\Job;
use App\Models\Plan;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Services\Billing\StripeBillingProvider;
use App\Services\SiteService;
use App\Support\Jwt;
use Stripe\StripeClient;

return function (): Router {
    $jwt = new Jwt((string) env('JWT_SECRET'));
    $accountModel = new Account();
    $planModel = new Plan();

    $authService = new AuthService(
        $jwt,
        $accountModel,
        (int) env('JWT_ACCESS_TTL', 900),
        (int) env('JWT_REFRESH_TTL', 1209600),
    );

    $billing = new StripeBillingProvider(
        new StripeClient((string) env('STRIPE_SECRET_KEY')),
        $accountModel,
        $planModel,
        (string) env('STRIPE_WEBHOOK_SECRET'),
        (string) env('APP_URL'),
    );

    $controllers = [
        'auth' => new AuthController($authService),
        'account' => new AccountController(new AccountService($accountModel)),
        'site' => new SiteController(new SiteService($planModel), new AccountService($accountModel)),
        'billing' => new BillingController($billing),
        'stripeWebhook' => new StripeWebhookController($billing, new Job()),
    ];

    $router = new Router(new JwtAuthMiddleware($jwt));
    (require __DIR__ . '/routes.php')($router, $controllers);

    return $router;
};
