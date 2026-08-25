<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'contact_name', 'email', 'phone', 'country', 'address', 'status', 'notes'])]
class Supplier extends Model
{
    use SoftDeletes;

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'supplier_materials')
            ->withPivot(['supplier_sku', 'unit_price', 'currency', 'lead_time_days', 'minimum_order_quantity', 'is_preferred', 'status', 'notes'])
            ->withTimestamps();
    }
}
