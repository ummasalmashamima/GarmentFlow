<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Models\Material;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BOM\BOMCalculationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BOMCalculationServiceTest extends TestCase
{
    public function test_it_calculates_material_requirement_with_wastage(): void
    {
        $product = new Product(['code' => 'TEE-001', 'name' => 'Classic tee']);
        $header = new BomHeader(['code' => 'BOM-001', 'name' => 'Classic tee BOM']);
        $header->setRelation('product', $product);
        $version = new BomVersion([
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'status' => 'draft',
        ]);
        $version->setRelation('bomHeader', $header);
        $material = new Material(['code' => 'FAB-001', 'name' => 'Fabric']);
        $material->setAttribute($material->getKeyName(), 1);
        $unit = new Unit(['code' => 'M', 'name' => 'Meter', 'symbol' => 'm']);
        $unit->setAttribute($unit->getKeyName(), 1);
        $item = new BomItem([
            'quantity' => 1.5,
            'wastage_percentage' => 5,
            'line_number' => 1,
        ]);
        $item->setAttribute($item->getKeyName(), 1);
        $item->setRelation('material', $material);
        $item->setRelation('unit', $unit);
        $version->setRelation('items', collect([$item]));

        $result = (new BOMCalculationService)->calculate($version, 100);

        self::assertSame(157.5, $result['lines'][0]['required_quantity']);
        self::assertSame(1.05, $result['lines'][0]['wastage_factor']);
        self::assertSame(1, $result['total_lines']);
    }

    public function test_it_rejects_non_positive_order_quantity(): void
    {
        $this->expectException(ValidationException::class);

        (new BOMCalculationService)->calculate(new BomVersion, 0);
    }
}
