<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Global plan catalog, not tenant data — doesn't extend BaseModel for the
 * same reason Account doesn't: there's no account_id to scope by, every
 * account reads the same rows.
 */
final class Plan
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findByKey(string $planKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE plan_key = :plan_key LIMIT 1');
        $stmt->execute(['plan_key' => $planKey]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM plans');

        return $stmt->fetchAll();
    }
}
