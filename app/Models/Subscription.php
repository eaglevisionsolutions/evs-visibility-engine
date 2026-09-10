<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Subscription extends BaseModel
{
    protected string $table = 'subscriptions';
    protected array $fillable = ['plan_key', 'status', 'current_period_end'];

    public function current(): ?array
    {
        $rows = $this->all();

        return $rows[0] ?? null;
    }
}
