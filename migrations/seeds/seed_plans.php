<?php

declare(strict_types=1);

/**
 * Seeds the `plans` table from Stripe price IDs in .env — kept separate
 * from migrations/ (schema) since this is data, and outside the migrate.php
 * glob so the runner never tries to treat it as a migration. Deliberately
 * does not hardcode a stripe_price_id: create the Products/Prices in your
 * Stripe test dashboard first, put the price IDs in .env, then run this.
 *
 * Usage: php migrations/seeds/seed_plans.php
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/config/env.php';

use App\Core\Database;

$db = Database::connection();

$plans = [
    [
        'plan_key' => 'starter',
        'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
        'site_limit' => 1,
        'competitor_limit' => 3,
        'keyword_limit' => 50,
        'aeo_prompt_limit' => 10,
    ],
    [
        'plan_key' => 'pro',
        'stripe_price_id' => env('STRIPE_PRICE_PRO'),
        'site_limit' => 5,
        'competitor_limit' => 10,
        'keyword_limit' => 250,
        'aeo_prompt_limit' => 50,
    ],
    [
        'plan_key' => 'agency',
        'stripe_price_id' => env('STRIPE_PRICE_AGENCY'),
        'site_limit' => 25,
        'competitor_limit' => 25,
        'keyword_limit' => 1000,
        'aeo_prompt_limit' => 200,
    ],
];

$stmt = $db->prepare(<<<SQL
    INSERT INTO plans (plan_key, stripe_price_id, site_limit, competitor_limit, keyword_limit, aeo_prompt_limit)
    VALUES (:plan_key, :stripe_price_id, :site_limit, :competitor_limit, :keyword_limit, :aeo_prompt_limit)
    ON DUPLICATE KEY UPDATE
        stripe_price_id = VALUES(stripe_price_id),
        site_limit = VALUES(site_limit),
        competitor_limit = VALUES(competitor_limit),
        keyword_limit = VALUES(keyword_limit),
        aeo_prompt_limit = VALUES(aeo_prompt_limit)
SQL);

foreach ($plans as $plan) {
    if (empty($plan['stripe_price_id'])) {
        fwrite(STDERR, "Skipping '{$plan['plan_key']}' — no Stripe price ID set in .env for it.\n");
        continue;
    }

    $stmt->execute($plan);
    echo "Seeded plan: {$plan['plan_key']}\n";
}
