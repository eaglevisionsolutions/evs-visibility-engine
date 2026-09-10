<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<SQL
            CREATE TABLE jobs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(100) NOT NULL,
                payload JSON NOT NULL,
                status ENUM('pending', 'running', 'completed', 'error') NOT NULL DEFAULT 'pending',
                run_after DATETIME NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_jobs_poll (status, run_after)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS jobs');
    }
};
