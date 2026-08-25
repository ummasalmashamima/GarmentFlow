<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'hex_code', 'status', 'description'])]
class Color extends Model
{
    use SoftDeletes;

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
