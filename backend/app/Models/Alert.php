<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'alert_key',
    'rule_code',
    'severity',
    'title',
    'description',
    'related_type',
    'related_id',
    'role_slug',
    'permission_slug',
    'occurred_at',
    'resolved_at',
])]
class Alert extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AlertRead::class);
    }
}
