<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<SQL
            CREATE TABLE content_queue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                keyword VARCHAR(500) NOT NULL,
                status ENUM('queued', 'writing', 'ready_for_review', 'approved', 'published', 'error')
                    NOT NULL DEFAULT 'queued',
                source VARCHAR(50) NOT NULL,
                seo_title VARCHAR(255) NULL,
                meta_desc VARCHAR(500) NULL,
                h1 VARCHAR(255) NULL,
                outline JSON NULL,
                content_html LONGTEXT NULL,
                slug VARCHAR(255) NULL,
                published_url VARCHAR(2048) NULL,
                sessions_30d INT UNSIGNED NULL,
                conversions_30d INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_content_queue_account_id (account_id),
                KEY idx_content_queue_site_status (site_id, status),
                CONSTRAINT fk_content_queue_account
                    FOREIGN KEY (account_id) REFERENCES accounts (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_content_queue_site
                    FOREIGN KEY (site_id) REFERENCES sites (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS content_queue');
    }
};
