<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class KeywordMap extends BaseModel
{
    protected string $table = 'keyword_map';
    protected array $fillable = ['site_id', 'keyword', 'mapped_url', 'source'];

    /**
     * INSERT IGNORE relies on uq_keyword_map_site_keyword — sync_gsc can
     * run repeatedly for the same site without creating duplicate rows,
     * satisfying CLAUDE.md's "every job handler must be idempotent" rule.
     * Returns true if a new row was actually inserted.
     */
    public function insertIfNew(int $siteId, string $keyword, string $source): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO keyword_map (account_id, site_id, keyword, source, created_at) '
            . 'VALUES (:account_id, :site_id, :keyword, :source, NOW())'
        );
        $stmt->execute([
            'account_id' => $this->accountId,
            'site_id' => $siteId,
            'keyword' => $keyword,
            'source' => $source,
        ]);

        return $stmt->rowCount() > 0;
    }
}
