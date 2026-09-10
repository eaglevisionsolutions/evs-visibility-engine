<?php

declare(strict_types=1);

/**
 * Plain-PHP migration runner — no migration framework, per CLAUDE.md.
 * Each migration file returns an anonymous object with up(PDO)/down(PDO).
 * Applied migrations are tracked in a `migrations` table (filename +
 * applied_at) so re-running this script only applies what's pending.
 *
 * Usage:
 *   php migrations/migrate.php          # apply all pending migrations
 *   php migrations/migrate.php rollback  # roll back the most recently applied migration
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/env.php';

use App\Core\Database;

$db = Database::connection();

$db->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$files = glob(__DIR__ . '/*.php');
sort($files);
$files = array_filter($files, static fn (string $f) => basename($f) !== 'migrate.php');

$applied = $db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$command = $argv[1] ?? 'migrate';

if ($command === 'rollback') {
    $last = $db->query('SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')->fetch();

    if ($last === false) {
        echo "Nothing to roll back.\n";
        exit(0);
    }

    $name = $last['migration'];
    $migration = require __DIR__ . '/' . $name;
    $migration->down($db);

    $stmt = $db->prepare('DELETE FROM migrations WHERE migration = :name');
    $stmt->execute(['name' => $name]);

    echo "Rolled back {$name}\n";
    exit(0);
}

$pending = array_filter($files, static fn (string $f) => !in_array(basename($f), $applied, true));

if ($pending === []) {
    echo "Nothing to migrate.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    $migration = require $file;

    $db->beginTransaction();
    try {
        $migration->up($db);
        $stmt = $db->prepare('INSERT INTO migrations (migration) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        $db->commit();
        echo "Migrated {$name}\n";
    } catch (\Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, "Failed on {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}
