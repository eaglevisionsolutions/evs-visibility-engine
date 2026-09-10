<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<SQL
            CREATE TABLE keyword_map (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                keyword VARCHAR(500) NOT NULL,
                mapped_url VARCHAR(2048) NULL,
                source VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_keyword_map_site_keyword (site_id, keyword(255)),
                KEY idx_keyword_map_account_id (account_id),
                CONSTRAINT fk_keyword_map_account
                    FOREIGN KEY (account_id) REFERENCES accounts (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_keyword_map_site
                    FOREIGN KEY (site_id) REFERENCES sites (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS keyword_map');
    }
};
