<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Models\Site;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The Phase 1 definition-of-done test: two accounts, verify account A
 * cannot read/write account B's data through any endpoint. Exercises both
 * the HTTP layer (the only endpoint that returns site data today,
 * GET /api/v1/sites) and the BaseModel mechanism directly, since that
 * mechanism is what every future site-scoped endpoint (show/update/delete
 * by id) will rely on for enforcement.
 */
final class TenantIsolationTest extends TestCase
{
    private PDO $db;
    private Router $router;
    private string $planKey = 'test_plan';

    protected function setUp(): void
    {
        $this->db = Database::connection();
        $this->router = (require __DIR__ . '/../app/bootstrap.php')();

        $this->db->prepare(
            'INSERT INTO plans (plan_key, stripe_price_id, site_limit, competitor_limit, keyword_limit, aeo_prompt_limit) '
            . 'VALUES (:key, :price, 5, 5, 5, 5) '
            . 'ON DUPLICATE KEY UPDATE site_limit = VALUES(site_limit)'
        )->execute(['key' => $this->planKey, 'price' => 'price_test']);
    }

    protected function tearDown(): void
    {
        $this->db->exec("DELETE FROM sites WHERE domain LIKE 'tenant-test-%'");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'tenant-test-%'");
        $this->db->exec("DELETE FROM accounts WHERE name LIKE 'Tenant Test %'");
        $this->db->prepare('DELETE FROM plans WHERE plan_key = :key')->execute(['key' => $this->planKey]);
    }

    public function testAccountCannotReadOrWriteAnotherAccountsSite(): void
    {
        $suffix = uniqid();

        $accountA = $this->registerAccount("tenant-test-a-{$suffix}@example.com");
        $accountB = $this->registerAccount("tenant-test-b-{$suffix}@example.com");

        $createResponse = $this->router->dispatch(new Request(
            method: 'POST',
            path: '/api/v1/sites',
            body: ['domain' => "tenant-test-{$suffix}.example.com"],
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountA['access_token']],
        ));

        self::assertSame(201, $createResponse->status);
        $siteId = (int) $createResponse->data['id'];

        // Through the endpoint: account B's site list must never contain account A's site.
        $listAsB = $this->router->dispatch(new Request(
            method: 'GET',
            path: '/api/v1/sites',
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountB['access_token']],
        ));

        self::assertSame(200, $listAsB->status);
        self::assertSame([], $listAsB->data['sites']);

        // Sanity check: account A can see its own site.
        $listAsA = $this->router->dispatch(new Request(
            method: 'GET',
            path: '/api/v1/sites',
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountA['access_token']],
        ));

        self::assertCount(1, $listAsA->data['sites']);
        self::assertSame($siteId, (int) $listAsA->data['sites'][0]['id']);

        // Directly against BaseModel: this is the mechanism every future
        // site-scoped endpoint (show/update/delete by id) will rely on.
        $siteAsB = new Site($accountB['account_id']);

        self::assertNull($siteAsB->find($siteId));
        self::assertFalse($siteAsB->update($siteId, ['domain' => 'hijacked.example.com']));
        self::assertFalse($siteAsB->delete($siteId));

        $siteAsA = new Site($accountA['account_id']);
        $stillOwnedByA = $siteAsA->find($siteId);

        self::assertNotNull($stillOwnedByA, 'Account B\'s failed delete must not have removed the row.');
        self::assertSame("tenant-test-{$suffix}.example.com", $stillOwnedByA['domain']);
    }

    /**
     * @return array{account_id: int, user_id: int, access_token: string, refresh_token: string}
     */
    private function registerAccount(string $email): array
    {
        $response = $this->router->dispatch(new Request(
            method: 'POST',
            path: '/api/v1/auth/register',
            body: [
                'account_name' => 'Tenant Test ' . $email,
                'email' => $email,
                'password' => 'correct-horse-battery-staple',
                'plan_key' => $this->planKey,
            ],
        ));

        self::assertSame(201, $response->status, 'Registration failed: ' . json_encode($response->data));

        return $response->data;
    }
}
