<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Models\ContentQueue;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 adds content_queue as a second tenant-scoped table. Unlike sites,
 * it has no creation endpoint (rows only ever come from the sync_gsc job
 * pipeline), so this test seeds a row directly through the ContentQueue
 * model rather than via HTTP — the read/write isolation checks below are
 * what matter, not how the fixture row got there.
 *
 * Mirrors TenantIsolationTest.php's structure: the HTTP layer
 * (GET /api/v1/content-queue, POST .../approve) and the BaseModel
 * mechanism directly, since every content_queue-scoped endpoint relies on
 * that mechanism for enforcement.
 */
final class ContentQueueTenantIsolationTest extends TestCase
{
    private PDO $db;
    private Router $router;
    private string $planKey = 'test_plan_cq';
    private ?int $contentQueueId = null;

    protected function setUp(): void
    {
        $this->db = Database::connection();
        $this->router = (require __DIR__ . '/../app/bootstrap.php')();

        $this->db->prepare(
            'INSERT INTO plans (plan_key, stripe_price_id, site_limit, competitor_limit, keyword_limit, aeo_prompt_limit) '
            . 'VALUES (:key, :price, 5, 5, 5, 5) '
            . 'ON DUPLICATE KEY UPDATE site_limit = VALUES(site_limit)'
        )->execute(['key' => $this->planKey, 'price' => 'price_test_cq']);
    }

    protected function tearDown(): void
    {
        if ($this->contentQueueId !== null) {
            $this->db->exec(
                "DELETE FROM jobs WHERE payload LIKE '%\"content_queue_id\":{$this->contentQueueId}%'"
            );
        }
        $this->db->exec("DELETE FROM content_queue WHERE keyword LIKE 'tenant-cq-test-%'");
        $this->db->exec("DELETE FROM sites WHERE domain LIKE 'tenant-cq-test-%'");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'tenant-cq-test-%'");
        $this->db->exec("DELETE FROM accounts WHERE name LIKE 'Tenant CQ Test %'");
        $this->db->prepare('DELETE FROM plans WHERE plan_key = :key')->execute(['key' => $this->planKey]);
    }

    public function testAccountCannotReadOrWriteAnotherAccountsContentQueueRow(): void
    {
        $suffix = uniqid();
        $keyword = "tenant-cq-test-{$suffix}";

        $accountA = $this->registerAccount("tenant-cq-test-a-{$suffix}@example.com");
        $accountB = $this->registerAccount("tenant-cq-test-b-{$suffix}@example.com");

        $siteResponse = $this->router->dispatch(new Request(
            method: 'POST',
            path: '/api/v1/sites',
            body: ['domain' => "tenant-cq-test-{$suffix}.example.com"],
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountA['access_token']],
        ));

        self::assertSame(201, $siteResponse->status);
        $siteId = (int) $siteResponse->data['id'];

        // Seeded directly (no public creation endpoint — see class docblock),
        // status = ready_for_review so it's eligible for the approve endpoint.
        $contentQueueId = (new ContentQueue($accountA['account_id']))->create([
            'site_id' => $siteId,
            'keyword' => $keyword,
            'status' => 'ready_for_review',
            'source' => 'own_opportunity',
            'seo_title' => 'Test title',
        ]);
        $this->contentQueueId = $contentQueueId;

        // Through the endpoint: account B's content queue must never contain account A's row.
        $listAsB = $this->router->dispatch(new Request(
            method: 'GET',
            path: '/api/v1/content-queue',
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountB['access_token']],
        ));

        self::assertSame(200, $listAsB->status);
        self::assertSame([], $listAsB->data['content_queue']);

        // Sanity check: account A can see its own row.
        $listAsA = $this->router->dispatch(new Request(
            method: 'GET',
            path: '/api/v1/content-queue',
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountA['access_token']],
        ));

        self::assertCount(1, $listAsA->data['content_queue']);
        self::assertSame($contentQueueId, (int) $listAsA->data['content_queue'][0]['id']);

        // Through the endpoint: account B cannot approve account A's row —
        // must 404, and must not enqueue a publish_content job.
        $approveAsB = $this->router->dispatch(new Request(
            method: 'POST',
            path: "/api/v1/content-queue/{$contentQueueId}/approve",
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountB['access_token']],
        ));

        self::assertSame(404, $approveAsB->status);

        // Directly against BaseModel: the mechanism every content_queue-scoped
        // endpoint relies on.
        $cqAsB = new ContentQueue($accountB['account_id']);

        self::assertNull($cqAsB->find($contentQueueId));
        self::assertFalse($cqAsB->update($contentQueueId, ['status' => 'approved']));
        self::assertFalse($cqAsB->delete($contentQueueId));

        $cqAsA = new ContentQueue($accountA['account_id']);
        $stillOwnedByA = $cqAsA->find($contentQueueId);

        self::assertNotNull($stillOwnedByA, 'Account B\'s failed delete must not have removed the row.');
        self::assertSame('ready_for_review', $stillOwnedByA['status'], 'Account B\'s failed update must not have changed status.');

        // Sanity check: account A can approve its own row through the endpoint.
        $approveAsA = $this->router->dispatch(new Request(
            method: 'POST',
            path: "/api/v1/content-queue/{$contentQueueId}/approve",
            headers: ['AUTHORIZATION' => 'Bearer ' . $accountA['access_token']],
        ));

        self::assertSame(200, $approveAsA->status);
        self::assertSame('approved', $cqAsA->find($contentQueueId)['status']);
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
                'account_name' => 'Tenant CQ Test ' . $email,
                'email' => $email,
                'password' => 'correct-horse-battery-staple',
                'plan_key' => $this->planKey,
            ],
        ));

        self::assertSame(201, $response->status, 'Registration failed: ' . json_encode($response->data));

        return $response->data;
    }
}
