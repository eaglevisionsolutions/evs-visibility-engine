<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Crypto;
use InvalidArgumentException;
use PDO;

/**
 * Every tenant-scoped table's model extends this. The constructor requires
 * an account_id — there is no code path to a scoped query without one. This
 * is the enforcement point CLAUDE.md calls out as the single most important
 * security rule in the codebase: a controller cannot construct a scoped
 * model without first resolving account_id from the authenticated JWT
 * (see Core/Middleware/JwtAuthMiddleware), and every method below folds
 * that account_id into the query — callers cannot opt out per-call.
 *
 * Tables that are NOT tenant-scoped this way:
 * - `accounts` (the tenant root itself — scoped by its own id, not a parent
 *   account_id; see Models/Account.php, which does not extend this class)
 * - `jobs` (system-level work queue processed by the CLI worker, not
 *   exposed through any tenant-scoped API endpoint; see Models/Job.php)
 */
abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    protected string $tenantColumn = 'account_id';

    /** @var list<string> Columns allowed in insert()/update() data arrays. */
    protected array $fillable = [];

    /** @var list<string> Fillable columns that are encrypted at rest (see Support/Crypto). */
    protected array $encrypted = [];

    public function __construct(
        protected readonly int $accountId,
        ?PDO $db = null,
    ) {
        if ($this->accountId <= 0) {
            throw new InvalidArgumentException(
                'A tenant-scoped model cannot be constructed without a valid account_id.'
            );
        }

        $this->db = $db ?? Database::connection();
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id AND {$this->tenantColumn} = :tenant_id LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $this->accountId]);

        $row = $stmt->fetch();

        return $row === false ? null : $this->decryptRow($row);
    }

    /**
     * @param array<string, scalar|null> $conditions Additional equality conditions, ANDed in.
     * @return list<array<string, mixed>>
     */
    public function all(array $conditions = []): array
    {
        [$whereSql, $params] = $this->buildWhere($conditions);

        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$whereSql}");
        $stmt->execute($params);

        return array_map($this->decryptRow(...), $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->encryptRow($this->filterFillable($data));
        $data[$this->tenantColumn] = $this->accountId;

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->encryptRow($this->filterFillable($data));

        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($data)));

        $sql = "UPDATE {$this->table} SET {$assignments} "
            . "WHERE id = :id AND {$this->tenantColumn} = :tenant_id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([...$data, 'id' => $id, 'tenant_id' => $this->accountId]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id = :id AND {$this->tenantColumn} = :tenant_id"
        );

        return $stmt->execute(['id' => $id, 'tenant_id' => $this->accountId]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function encryptRow(array $data): array
    {
        foreach ($this->encrypted as $column) {
            if (isset($data[$column]) && $data[$column] !== '') {
                $data[$column] = Crypto::encrypt((string) $data[$column]);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decryptRow(array $row): array
    {
        foreach ($this->encrypted as $column) {
            if (!empty($row[$column])) {
                $row[$column] = Crypto::decrypt((string) $row[$column]) ?? $row[$column];
            }
        }

        return $row;
    }

    /**
     * @param array<string, scalar|null> $conditions
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $conditions): array
    {
        $params = ['tenant_id' => $this->accountId];
        $clauses = ["{$this->tenantColumn} = :tenant_id"];

        foreach ($conditions as $column => $value) {
            $paramKey = 'cond_' . $column;
            $clauses[] = "{$column} = :{$paramKey}";
            $params[$paramKey] = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }
}
