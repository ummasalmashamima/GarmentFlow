<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'description', 'status'])]
class MaterialCategory extends Model
{
    use SoftDeletes;

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
