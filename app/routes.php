<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\ContentQueueController;
use App\Controllers\GscOAuthController;
use App\Controllers\SiteController;
use App\Controllers\StripeWebhookController;
use App\Core\Router;

/**
 * All routes live under /api/v1/ per CLAUDE.md. `auth: false` marks routes
 * that run before a JWT exists: register/login/refresh, the Stripe
 * webhook (authenticates via signature instead), and the GSC OAuth
 * callback (Google redirects the browser here directly — tenant identity
 * comes from the signed `state` param instead, see GoogleOAuthService).
 *
 * @param array{
 *   auth: AuthController,
 *   account: AccountController,
 *   site: SiteController,
 *   billing: BillingController,
 *   stripeWebhook: StripeWebhookController,
 *   gscOAuth: GscOAuthController,
 *   contentQueue: ContentQueueController,
 * } $controllers
 */
return function (Router $router, array $controllers): void {
    $router->post('/api/v1/auth/register', $controllers['auth']->register(...), auth: false);
    $router->post('/api/v1/auth/login', $controllers['auth']->login(...), auth: false);
    $router->post('/api/v1/auth/refresh', $controllers['auth']->refresh(...), auth: false);

    $router->get('/api/v1/account', $controllers['account']->show(...));

    $router->get('/api/v1/sites', $controllers['site']->index(...));
    $router->post('/api/v1/sites', $controllers['site']->store(...));

    $router->get('/api/v1/billing/plans', $controllers['billing']->plans(...));
    $router->post('/api/v1/billing/subscribe', $controllers['billing']->subscribe(...));
    $router->get('/api/v1/billing/status', $controllers['billing']->status(...));
    $router->post('/api/v1/billing/portal', $controllers['billing']->portal(...));

    $router->post('/api/v1/billing/webhook', $controllers['stripeWebhook']->handle(...), auth: false);

    $router->get('/api/v1/sites/{id}/gsc/connect', $controllers['gscOAuth']->connect(...));
    $router->post('/api/v1/sites/{id}/gsc/sync', $controllers['gscOAuth']->sync(...));
    $router->get('/api/v1/gsc/callback', $controllers['gscOAuth']->callback(...), auth: false);

    $router->get('/api/v1/content-queue', $controllers['contentQueue']->index(...));
    $router->post('/api/v1/content-queue/{id}/approve', $controllers['contentQueue']->approve(...));
};
