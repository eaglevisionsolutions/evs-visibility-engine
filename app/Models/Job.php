<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * System work queue — not tenant-scoped data, not exposed through any
 * tenant API endpoint. A job's payload carries whatever account_id/site_id
 * it needs to act on; only the worker (workers/worker.php) touches this
 * model, so it doesn't extend BaseModel.
 */
final class Job
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(string $type, array $payload = [], ?string $runAfter = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO jobs (type, payload, status, run_after, attempts, created_at, updated_at) '
            . "VALUES (:type, :payload, 'pending', :run_after, 0, NOW(), NOW())"
        );
        $stmt->execute([
            'type' => $type,
            'payload' => json_encode($payload),
            'run_after' => $runAfter ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Locks and returns the next pending, due job, or null if none.
     * Uses SELECT ... FOR UPDATE inside the caller's transaction so two
     * worker processes can never pick up the same row.
     */
    public function claimNext(): ?array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM jobs '
                . "WHERE status = 'pending' AND run_after <= NOW() "
                . 'ORDER BY id ASC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute();
            $job = $stmt->fetch();

            if ($job === false) {
                $this->db->commit();
                return null;
            }

            $update = $this->db->prepare(
                "UPDATE jobs SET status = 'running', updated_at = NOW() WHERE id = :id"
            );
            $update->execute(['id' => $job['id']]);

            $this->db->commit();

            $job['status'] = 'running';

            return $job;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function markCompleted(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE jobs SET status = 'completed', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jobs SET status = :status, attempts = attempts + 1, last_error = :error, updated_at = NOW() '
            . 'WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'error',
            'error' => $error,
            'id' => $id,
        ]);
    }
}
