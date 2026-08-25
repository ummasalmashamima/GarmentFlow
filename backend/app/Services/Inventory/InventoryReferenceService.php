<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Validation\ValidationException;

final class InventoryReferenceService
{
    public function warehouse(int $id): Warehouse
    {
        return Warehouse::query()->where('status', 'active')->findOrFail($id);
    }

    public function location(?int $id, int $warehouseId): ?WarehouseLocation
    {
        if ($id === null) {
            return null;
        }

        $location = WarehouseLocation::query()
            ->where('status', 'active')
            ->where('warehouse_id', $warehouseId)
            ->find($id);

        if ($location === null) {
            throw ValidationException::withMessages(['warehouse_location_id' => 'The selected location must belong to the selected active warehouse.']);
        }

        return $location;
    }

    public function locationModel(int $id): WarehouseLocation
    {
        $location = WarehouseLocation::query()->where('status', 'active')->find($id);
        if ($location === null) {
            throw ValidationException::withMessages(['warehouse_location_id' => 'The selected active warehouse location does not exist.']);
        }

        return $location;
    }

    public function unit(int $id): Unit
    {
        return Unit::query()->where('status', 'active')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{item_type: string, material_id: int|null, product_id: int|null, product_variant_id: int|null, unit_id: int}
     */
    public function item(array $attributes): array
    {
        $materialId = isset($attributes['material_id']) && $attributes['material_id'] !== '' ? (int) $attributes['material_id'] : null;
        $productId = isset($attributes['product_id']) && $attributes['product_id'] !== '' ? (int) $attributes['product_id'] : null;
        $variantId = isset($attributes['product_variant_id']) && $attributes['product_variant_id'] !== '' ? (int) $attributes['product_variant_id'] : null;
        $provided = array_values(array_filter([$materialId, $productId, $variantId], static fn (?int $id): bool => $id !== null));
        if (count($provided) !== 1 && ! ($productId !== null && $variantId !== null && $materialId === null)) {
            throw ValidationException::withMessages(['item' => 'Provide exactly one material, product, or product variant.']);
        }

        $expectedUnitId = null;
        if ($materialId !== null) {
            $material = Material::query()->where('status', 'active')->findOrFail($materialId);
            $expectedUnitId = (int) $material->unit_id;
            $itemType = 'material';
            $productId = null;
            $variantId = null;
        } elseif ($variantId !== null) {
            $variant = ProductVariant::query()->with('product')->where('status', 'active')->findOrFail($variantId);
            if ($productId !== null && (int) $variant->product_id !== $productId) {
                throw ValidationException::withMessages(['product_variant_id' => 'The selected variant does not belong to the selected product.']);
            }
            $productId = (int) $variant->product_id;
            $expectedUnitId = (int) $variant->product->unit_id;
            $itemType = 'product_variant';
        } else {
            $product = Product::query()->where('status', 'active')->findOrFail((int) $productId);
            $expectedUnitId = (int) $product->unit_id;
            $itemType = 'product';
        }

        $unitId = (int) ($attributes['unit_id'] ?? 0);
        $this->unit($unitId);
        if ($expectedUnitId !== $unitId) {
            throw ValidationException::withMessages(['unit_id' => 'The selected unit must match the item master unit.']);
        }

        return [
            'item_type' => $itemType,
            'material_id' => $materialId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'unit_id' => $unitId,
        ];
    }

    /** @param array<string, mixed> $item */
    public function stockKey(array $item, int $warehouseId, ?int $locationId): string
    {
        $identity = match ($item['item_type']) {
            'material' => 'material:'.$item['material_id'],
            'product' => 'product:'.$item['product_id'],
            'product_variant' => 'variant:'.$item['product_variant_id'],
            default => throw ValidationException::withMessages(['item' => 'Unsupported inventory item type.']),
        };

        return implode('|', [$warehouseId, $locationId ?? 0, $identity, $item['unit_id']]);
    }
}
