<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<SQL
            CREATE TABLE subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                plan_key VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                current_period_end DATETIME NULL,
                KEY idx_subscriptions_account_id (account_id),
                CONSTRAINT fk_subscriptions_account
                    FOREIGN KEY (account_id) REFERENCES accounts (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_subscriptions_plan
                    FOREIGN KEY (plan_key) REFERENCES plans (plan_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS subscriptions');
    }
};
