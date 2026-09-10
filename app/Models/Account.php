<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Deliberately does NOT extend BaseModel: `accounts` is the tenant root,
 * not a table scoped BY a tenant. A row here has no parent account_id to
 * check against — it IS the account_id every other model checks against.
 * Callers must always scope by the account's own id, resolved from the
 * JWT the same way BaseModel-backed models resolve account_id, and
 * registration is the one legitimate path that creates a row before any
 * JWT exists.
 */
final class Account
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByEmailOfOwner(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT accounts.* FROM accounts '
            . 'INNER JOIN users ON users.account_id = accounts.id '
            . 'WHERE users.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $name, string $planKey): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO accounts (name, plan_key, created_at) VALUES (:name, :plan_key, NOW())'
        );
        $stmt->execute(['name' => $name, 'plan_key' => $planKey]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStripeCustomerId(int $id, string $stripeCustomerId): bool
    {
        $stmt = $this->db->prepare('UPDATE accounts SET stripe_customer_id = :cid WHERE id = :id');

        return $stmt->execute(['cid' => $stripeCustomerId, 'id' => $id]);
    }

    public function updatePlan(int $id, string $planKey): bool
    {
        $stmt = $this->db->prepare('UPDATE accounts SET plan_key = :plan_key WHERE id = :id');

        return $stmt->execute(['plan_key' => $planKey, 'id' => $id]);
    }
}
