<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = Buyer::query()->firstOrCreate(
            ['code' => 'BUY-001'],
            [
                'name' => 'Global Apparel Buyer',
                'company' => 'Global Brands Inc',
                'contact_name' => 'John Doe',
                'email' => 'buyer@globalbrands.example',
                'phone' => '+1-555-0100',
                'country' => 'United States',
                'address' => '100 Fashion Avenue, New York, NY 10001',
                'status' => 'active',
                'notes' => 'Primary international buyer account.',
            ]
        );

        $customer = Customer::query()->firstOrCreate(
            ['code' => 'CUS-001'],
            [
                'name' => 'Retail Partner Customer',
                'contact_name' => 'Jane Smith',
                'email' => 'customer@retailpartner.example',
                'phone' => '+1-555-0200',
                'country' => 'United States',
                'address' => '200 Retail Parkway, Chicago, IL 60601',
                'status' => 'active',
                'notes' => 'Major retail chain partner.',
            ]
        );

        $supplier = Supplier::query()->firstOrCreate(
            ['code' => 'SUP-001'],
            [
                'name' => 'Prime Fabrics & Trims Supplier',
                'contact_name' => 'Alex Wong',
                'email' => 'sales@primesupplier.example',
                'phone' => '+880-1700-000000',
                'country' => 'Bangladesh',
                'address' => 'Plot 45, Export Processing Zone, Dhaka',
                'status' => 'active',
                'notes' => 'Certified high-grade textile and trim supplier.',
            ]
        );

        $fabricCategory = MaterialCategory::query()->firstOrCreate(
            ['code' => 'FABRIC'],
            [
                'name' => 'Knitted & Woven Fabrics',
                'description' => 'Raw textile rolls and fabrics',
                'status' => 'active',
            ]
        );

        $trimCategory = MaterialCategory::query()->firstOrCreate(
            ['code' => 'TRIM'],
            [
                'name' => 'Trims & Accessories',
                'description' => 'Buttons, zippers, labels, and threads',
                'status' => 'active',
            ]
        );

        $unitKg = Unit::query()->firstOrCreate(
            ['code' => 'KG'],
            [
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'decimal_places' => 4,
                'status' => 'active',
                'description' => 'Standard metric weight unit.',
            ]
        );

        $unitPcs = Unit::query()->firstOrCreate(
            ['code' => 'PCS'],
            [
                'name' => 'Pieces',
                'symbol' => 'pcs',
                'decimal_places' => 0,
                'status' => 'active',
                'description' => 'Discrete item unit.',
            ]
        );

        $sizeM = Size::query()->firstOrCreate(
            ['code' => 'M'],
            [
                'name' => 'Medium',
                'sort_order' => 2,
                'status' => 'active',
                'description' => 'Standard Adult Medium',
            ]
        );

        $colorNavy = Color::query()->firstOrCreate(
            ['code' => 'NAVY'],
            [
                'name' => 'Navy Blue',
                'hex_code' => '#000080',
                'status' => 'active',
                'description' => 'Dark Navy Solid',
            ]
        );

        $categoryApparel = Category::query()->firstOrCreate(
            ['code' => 'APPAREL'],
            [
                'name' => 'Apparel & Garments',
                'description' => 'Finished ready-to-wear clothing',
                'status' => 'active',
            ]
        );

        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'DHK-01'],
            [
                'name' => 'Dhaka Central Warehouse',
                'contact_name' => 'Warehouse Manager',
                'phone' => '+880-1800-000000',
                'country' => 'Bangladesh',
                'address' => 'Tejgaon Industrial Area, Dhaka',
                'status' => 'active',
                'notes' => 'Central raw material and finished goods facility.',
            ]
        );

        $location = WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'A-01-01'],
            [
                'name' => 'Rack A-01 Bay 01',
                'location_type' => 'storage',
                'status' => 'active',
                'description' => 'Fabric storage bay',
            ]
        );

        $material = Material::query()->firstOrCreate(
            ['code' => 'FAB-COT-001'],
            [
                'material_category_id' => $fabricCategory->id,
                'unit_id' => $unitKg->id,
                'name' => '100% Cotton Single Jersey 180 GSM',
                'material_type' => 'Fabric',
                'status' => 'active',
                'description' => 'Premium combed cotton yarn knit fabric.',
            ]
        );

        $product = Product::query()->firstOrCreate(
            ['code' => 'TEE-CLASSIC'],
            [
                'category_id' => $categoryApparel->id,
                'unit_id' => $unitPcs->id,
                'name' => 'Classic Cotton Crewneck Tee',
                'product_type' => 'Finished Good',
                'description' => 'Standard regular fit classic t-shirt.',
                'standard_cost' => 5.0000,
                'standard_price' => 12.0000,
                'status' => 'active',
            ]
        );

        $variant = ProductVariant::query()->firstOrCreate(
            ['sku' => 'TEE-CLASSIC-M-NAVY'],
            [
                'product_id' => $product->id,
                'size_id' => $sizeM->id,
                'color_id' => $colorNavy->id,
                'variant_name' => 'Classic Tee - M / Navy',
                'cost_price' => 5.0000,
                'selling_price' => 12.0000,
                'status' => 'active',
                'description' => 'Size M in solid Navy Blue.',
            ]
        );

        $supplier->materials()->syncWithoutDetaching([
            $material->id => [
                'supplier_sku' => 'SUP-FAB-001',
                'unit_price' => 4.5000,
                'currency' => 'USD',
                'lead_time_days' => 7,
                'minimum_order_quantity' => 10.0000,
                'is_preferred' => true,
                'status' => 'active',
                'notes' => 'Contracted supplier rate.',
            ],
        ]);
    }
}
