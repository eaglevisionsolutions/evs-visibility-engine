<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;
use PDO;

final class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = ['email', 'password_hash', 'role'];

    /**
     * Login needs to find a user by email BEFORE account_id is known — the
     * email is what resolves the account in the first place. This is the
     * one lookup on this table that can't go through the tenant-scoped
     * instance methods below, so it's a static helper that takes its own
     * PDO connection rather than requiring a BaseModel construction.
     */
    public static function findByEmailUnscoped(string $email, ?PDO $db = null): ?array
    {
        $db ??= Database::connection();

        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    // find(int $id) for "get the authenticated user within their own
    // account" is inherited from BaseModel — no override needed.
}
