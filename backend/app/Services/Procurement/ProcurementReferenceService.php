<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Validation\ValidationException;

final class ProcurementReferenceService
{
    public function supplier(int $supplierId): Supplier
    {
        $supplier = Supplier::query()->whereKey($supplierId)->where('status', 'active')->first();
        if ($supplier === null) {
            throw ValidationException::withMessages(['supplier_id' => 'The selected Supplier must exist and be active.']);
        }

        return $supplier;
    }

    public function material(int $materialId): Material
    {
        $material = Material::query()->whereKey($materialId)->where('status', 'active')->first();
        if ($material === null) {
            throw ValidationException::withMessages(['material_id' => 'The selected Material must exist and be active.']);
        }

        return $material;
    }

    public function unit(int $unitId): Unit
    {
        $unit = Unit::query()->whereKey($unitId)->where('status', 'active')->first();
        if ($unit === null) {
            throw ValidationException::withMessages(['unit_id' => 'The selected Unit must exist and be active.']);
        }

        return $unit;
    }

    public function warehouse(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->where('status', 'active')->first();
        if ($warehouse === null) {
            throw ValidationException::withMessages(['warehouse_id' => 'The selected Warehouse must exist and be active.']);
        }

        return $warehouse;
    }

    public function warehouseLocation(?int $locationId, int $warehouseId): ?WarehouseLocation
    {
        if ($locationId === null) {
            return null;
        }

        $location = WarehouseLocation::query()
            ->whereKey($locationId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->first();
        if ($location === null) {
            throw ValidationException::withMessages(['warehouse_location_id' => 'The selected location must belong to the selected active Warehouse.']);
        }

        return $location;
    }
}
