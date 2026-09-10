<?php

declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $db): void
    {
        // wp_app_password widened to TEXT: encrypted ciphertext (base64,
        // nonce-prefixed) runs longer than the original VARCHAR(255) plaintext.
        $db->exec(<<<SQL
            ALTER TABLE sites
                MODIFY wp_app_password TEXT NULL,
                ADD COLUMN wp_username VARCHAR(255) NULL AFTER wp_url,
                ADD COLUMN gsc_refresh_token TEXT NULL AFTER gsc_property,
                ADD COLUMN gsc_connected_at DATETIME NULL AFTER gsc_refresh_token
        SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec(<<<SQL
            ALTER TABLE sites
                DROP COLUMN gsc_connected_at,
                DROP COLUMN gsc_refresh_token,
                DROP COLUMN wp_username,
                MODIFY wp_app_password VARCHAR(255) NULL
        SQL);
    }
};
