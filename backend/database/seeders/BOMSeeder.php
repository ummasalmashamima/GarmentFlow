<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Models\Material;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class BOMSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::query()->where('code', 'TEE-CLASSIC')->first();
        $material = Material::query()->where('code', 'FAB-COT-001')->first();
        $unit = Unit::query()->where('code', 'KG')->first();

        if (! $product || ! $material || ! $unit) {
            return;
        }

        $bomHeader = BomHeader::query()->firstOrCreate(
            ['product_id' => $product->id],
            [
                'code' => 'BOM-TEE-001',
                'name' => 'Classic Cotton Crewneck Tee Engineering BOM',
                'status' => 'active',
                'description' => 'Active production BOM for Classic Cotton Tee',
            ]
        );

        $bomVersion = BomVersion::query()->firstOrCreate(
            [
                'bom_header_id' => $bomHeader->id,
                'version_number' => 1,
            ],
            [
                'effective_from' => '2026-01-01',
                'status' => 'active',
                'notes' => 'Baseline production specification v1.0',
            ]
        );

        BomItem::query()->firstOrCreate(
            [
                'bom_version_id' => $bomVersion->id,
                'material_id' => $material->id,
            ],
            [
                'unit_id' => $unit->id,
                'quantity' => 1.5000,
                'wastage_percentage' => 5.0000,
                'line_number' => 1,
                'notes' => 'Primary knit body fabric',
            ]
        );
    }
}
