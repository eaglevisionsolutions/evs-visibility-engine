<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class ContentQueue extends BaseModel
{
    protected string $table = 'content_queue';
    protected array $fillable = [
        'site_id', 'keyword', 'status', 'source', 'seo_title', 'meta_desc',
        'h1', 'outline', 'content_html', 'slug', 'published_url',
        'sessions_30d', 'conversions_30d',
    ];

    public function create(array $data): int
    {
        if (isset($data['outline']) && is_array($data['outline'])) {
            $data['outline'] = json_encode($data['outline']);
        }

        return parent::create($data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['outline']) && is_array($data['outline'])) {
            $data['outline'] = json_encode($data['outline']);
        }

        return parent::update($id, $data);
    }
}
