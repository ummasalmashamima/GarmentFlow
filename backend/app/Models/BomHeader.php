<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'code', 'name', 'status', 'description'])]
class BomHeader extends Model
{
    use SoftDeletes;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BomVersion::class)->orderByDesc('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(BomVersion::class)->where('status', 'active');
    }
}
