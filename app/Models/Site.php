<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

final class Site extends BaseModel
{
    protected string $table = 'sites';
    protected array $fillable = [
        'domain', 'wp_url', 'wp_username', 'wp_app_password',
        'gsc_property', 'ga4_property_id', 'gsc_refresh_token', 'gsc_connected_at',
    ];
    protected array $encrypted = ['wp_app_password', 'gsc_refresh_token'];
}
