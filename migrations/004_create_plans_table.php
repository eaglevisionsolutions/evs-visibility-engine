<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<SQL
            CREATE TABLE plans (
                plan_key VARCHAR(50) PRIMARY KEY,
                stripe_price_id VARCHAR(255) NOT NULL,
                site_limit INT UNSIGNED NOT NULL,
                competitor_limit INT UNSIGNED NOT NULL,
                keyword_limit INT UNSIGNED NOT NULL,
                aeo_prompt_limit INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS plans');
    }
};
