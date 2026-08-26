<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Color;
use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GarmentFlowIndustryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::query()->pluck('id')->all();

        // 1. Departments & Roles
        $departments = [
            'EXEC' => Department::query()->firstOrCreate(['code' => 'EXEC'], ['name' => 'Executive Management']),
            'SCM' => Department::query()->firstOrCreate(['code' => 'SCM'], ['name' => 'Supply Chain Management']),
            'PROD' => Department::query()->firstOrCreate(['code' => 'PROD'], ['name' => 'Production']),
            'PROC' => Department::query()->firstOrCreate(['code' => 'PROC'], ['name' => 'Procurement']),
            'WH' => Department::query()->firstOrCreate(['code' => 'WH'], ['name' => 'Warehouse & Inventory']),
            'FIN' => Department::query()->firstOrCreate(['code' => 'FIN'], ['name' => 'Finance & Accounts']),
        ];

        $roles = [
            'ceo' => Role::query()->firstOrCreate(
                ['slug' => 'ceo'],
                ['name' => 'Chief Executive Officer (CEO)', 'description' => 'Executive visibility across overall business KPIs, financials and operations.']
            ),
            'supply-chain-manager' => Role::query()->firstOrCreate(
                ['slug' => 'supply-chain-manager'],
                ['name' => 'Supply Chain Manager', 'description' => 'Demand forecasting, supply planning, MRP and shortage mitigation.']
            ),
            'production-manager' => Role::query()->firstOrCreate(
                ['slug' => 'production-manager'],
                ['name' => 'Production Manager', 'description' => 'Production scheduling, floor progress, material consumption and finished goods.']
            ),
            'procurement-manager' => Role::query()->firstOrCreate(
                ['slug' => 'procurement-manager'],
                ['name' => 'Procurement Manager', 'description' => 'Supplier management, requisitions, purchase orders and goods receipt.']
            ),
            'warehouse-manager' => Role::query()->firstOrCreate(
                ['slug' => 'warehouse-manager'],
                ['name' => 'Warehouse Manager', 'description' => 'Inventory balance, warehouse tracking, stock in/out, transfers and adjustments.']
            ),
        ];

        // Assign all permissions to CEO role
        $roles['ceo']->permissions()->syncWithoutDetaching($allPermissions);

        // Assign domain permissions
        $this->syncRolePermissions($roles['supply-chain-manager'], [
            'dashboard.view', 'dashboard.supply-chain.view', 'planning.view', 'planning.manage',
            'buyer-order.view', 'bom.view', 'inventory.view', 'reports.view', 'alerts.view', 'alerts.manage',
        ]);
        $this->syncRolePermissions($roles['production-manager'], [
            'dashboard.view', 'dashboard.production.view', 'production.view', 'production.manage', 'production.approve',
            'production.override', 'inventory.view', 'bom.view', 'reports.view', 'alerts.view', 'alerts.manage',
        ]);
        $this->syncRolePermissions($roles['procurement-manager'], [
            'dashboard.view', 'dashboard.procurement.view', 'procurement.view', 'procurement.manage', 'procurement.approve',
            'inventory.view', 'master-data.view', 'reports.view', 'alerts.view', 'alerts.manage',
        ]);
        $this->syncRolePermissions($roles['warehouse-manager'], [
            'dashboard.view', 'dashboard.warehouse.view', 'inventory.view', 'inventory.manage', 'inventory.adjust',
            'delivery.view', 'delivery.manage', 'delivery.dispatch', 'master-data.view', 'reports.view', 'alerts.view', 'alerts.manage',
        ]);

        // Demo User Accounts
        $users = [
            [
                'email' => 'ceo@garmentflow.com',
                'name' => 'Sarah Jenkins (CEO)',
                'department_id' => $departments['EXEC']->id,
                'role' => $roles['ceo'],
            ],
            [
                'email' => 'supplychain@garmentflow.com',
                'name' => 'Tariq Rahman (SCM Lead)',
                'department_id' => $departments['SCM']->id,
                'role' => $roles['supply-chain-manager'],
            ],
            [
                'email' => 'production@garmentflow.com',
                'name' => 'Carlos Mendez (Production Head)',
                'department_id' => $departments['PROD']->id,
                'role' => $roles['production-manager'],
            ],
            [
                'email' => 'procurement@garmentflow.com',
                'name' => 'Li Wei (Procurement Lead)',
                'department_id' => $departments['PROC']->id,
                'role' => $roles['procurement-manager'],
            ],
            [
                'email' => 'warehouse@garmentflow.com',
                'name' => 'Kareem Mostafa (Warehouse Lead)',
                'department_id' => $departments['WH']->id,
                'role' => $roles['warehouse-manager'],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'department_id' => $userData['department_id'],
                    'status' => 'active',
                ]
            );
            $user->roles()->syncWithoutDetaching([$userData['role']->id]);
        }

        // 2. Global Buyers
        $buyers = [
            [
                'code' => 'BUY-HM-001',
                'name' => 'H&M Hennes & Mauritz AB',
                'company' => 'H&M Group International',
                'contact_name' => 'Lars Lindqvist',
                'email' => 'sourcing@hm-apparel.example',
                'phone' => '+46-8-796-5500',
                'country' => 'Sweden',
                'address' => 'Mäster Samuelsgatan 46A, 106 38 Stockholm',
                'status' => 'active',
                'notes' => 'Major European fast-fashion retailer.',
            ],
            [
                'code' => 'BUY-ZARA-002',
                'name' => 'Zara / Inditex Group',
                'company' => 'Industria de Diseño Textil, S.A.',
                'contact_name' => 'Elena Gomez',
                'email' => 'procurement@inditex.example',
                'phone' => '+34-981-185-400',
                'country' => 'Spain',
                'address' => 'Avenida de la Diputación, Arteixo, A Coruña',
                'status' => 'active',
                'notes' => 'Continuous agile replenishment buyer.',
            ],
            [
                'code' => 'BUY-TGT-003',
                'name' => 'Target Global Sourcing',
                'company' => 'Target Brands Inc',
                'contact_name' => 'Marcus Vance',
                'email' => 'sourcing@target-retail.example',
                'phone' => '+1-612-304-6073',
                'country' => 'United States',
                'address' => '1000 Nicollet Mall, Minneapolis, MN 55403',
                'status' => 'active',
                'notes' => 'Large volume department store orders.',
            ],
            [
                'code' => 'BUY-UNI-004',
                'name' => 'Uniqlo / Fast Retailing',
                'company' => 'Fast Retailing Co., Ltd.',
                'contact_name' => 'Kenji Takahashi',
                'email' => 'apparel-orders@uniqlo.example',
                'phone' => '+81-3-6865-0050',
                'country' => 'Japan',
                'address' => 'Midtown Tower, Akasaka, Minato-ku, Tokyo',
                'status' => 'active',
                'notes' => 'Strict quality inspection and high repeat volumes.',
            ],
        ];

        foreach ($buyers as $b) {
            Buyer::query()->firstOrCreate(['code' => $b['code']], $b);
        }

        // 3. Suppliers
        $suppliers = [
            [
                'code' => 'SUP-TEX-001',
                'name' => 'Pacific Textile & Knit Mills',
                'contact_name' => 'Nurul Islam',
                'email' => 'orders@pacifictextile.example',
                'phone' => '+880-1711-100200',
                'country' => 'Bangladesh',
                'address' => 'Kashimpur Industrial Area, Gazipur, Dhaka',
                'status' => 'active',
                'notes' => 'Specialist in 100% Combed Cotton, Pique, and Terry Fabrics.',
            ],
            [
                'code' => 'SUP-YKK-002',
                'name' => 'YKK Fastening Solutions BD',
                'contact_name' => 'Hiroshi Sato',
                'email' => 'sales@ykk-fasteners.example',
                'phone' => '+880-2-988-1234',
                'country' => 'Bangladesh',
                'address' => 'Dhaka EPZ, Savar, Dhaka',
                'status' => 'active',
                'notes' => 'Certified OEM zippers, sliders, and snap buttons.',
            ],
            [
                'code' => 'SUP-COAT-003',
                'name' => 'Coats Industrial Threads',
                'contact_name' => 'Arthur Pendelton',
                'email' => 'support@coats-threads.example',
                'phone' => '+44-20-8210-5000',
                'country' => 'United Kingdom',
                'address' => 'The Pavilions, Bridgwater Road, Bristol',
                'status' => 'active',
                'notes' => 'Epic & Gramax high-durability sewing threads.',
            ],
        ];

        foreach ($suppliers as $s) {
            Supplier::query()->firstOrCreate(['code' => $s['code']], $s);
        }

        // 4. Units & Categories
        $unitKg = Unit::query()->firstOrCreate(['code' => 'KG'], ['name' => 'Kilogram', 'symbol' => 'kg', 'decimal_places' => 4, 'status' => 'active']);
        $unitPcs = Unit::query()->firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'pcs', 'decimal_places' => 0, 'status' => 'active']);
        $unitYards = Unit::query()->firstOrCreate(['code' => 'YDS'], ['name' => 'Yards', 'symbol' => 'yds', 'decimal_places' => 2, 'status' => 'active']);
        $unitCones = Unit::query()->firstOrCreate(['code' => 'CONE'], ['name' => 'Thread Cones', 'symbol' => 'cone', 'decimal_places' => 0, 'status' => 'active']);

        $apparelCat = Category::query()->firstOrCreate(['code' => 'APP-MEN'], ['name' => "Men's Apparel", 'status' => 'active']);
        $fabCat = MaterialCategory::query()->firstOrCreate(['code' => 'FABRIC'], ['name' => 'Fabrics & Textiles', 'status' => 'active']);
        $trimCat = MaterialCategory::query()->firstOrCreate(['code' => 'TRIM'], ['name' => 'Trims & Accessories', 'status' => 'active']);

        // 5. Materials
        $matCotton = Material::query()->firstOrCreate(
            ['code' => 'FAB-PIQUE-100'],
            ['material_category_id' => $fabCat->id, 'unit_id' => $unitKg->id, 'name' => '100% Combed Cotton Pique 220 GSM', 'material_type' => 'Fabric', 'status' => 'active']
        );
        $matThread = Material::query()->firstOrCreate(
            ['code' => 'TRM-THREAD-120'],
            ['material_category_id' => $trimCat->id, 'unit_id' => $unitCones->id, 'name' => 'Spun Polyester Sewing Thread 120/2', 'material_type' => 'Trim', 'status' => 'active']
        );
        $matButtons = Material::query()->firstOrCreate(
            ['code' => 'TRM-BTN-4HOLE'],
            ['material_category_id' => $trimCat->id, 'unit_id' => $unitPcs->id, 'name' => 'Engraved Resin 4-Hole Buttons 18L', 'material_type' => 'Trim', 'status' => 'active']
        );

        // 6. Sizes & Colors
        $sizeM = Size::query()->firstOrCreate(['code' => 'M'], ['name' => 'Medium', 'sort_order' => 2, 'status' => 'active']);
        $sizeL = Size::query()->firstOrCreate(['code' => 'L'], ['name' => 'Large', 'sort_order' => 3, 'status' => 'active']);
        $colorNavy = Color::query()->firstOrCreate(['code' => 'NAVY'], ['name' => 'Navy Blue', 'hex_code' => '#000080', 'status' => 'active']);
        $colorBlack = Color::query()->firstOrCreate(['code' => 'BLK'], ['name' => 'Pitch Black', 'hex_code' => '#000000', 'status' => 'active']);

        // 7. Products & Variants
        $productPolo = Product::query()->firstOrCreate(
            ['code' => 'POLO-PREM'],
            [
                'category_id' => $apparelCat->id,
                'unit_id' => $unitPcs->id,
                'name' => 'Premium Men Pique Polo Shirt',
                'description' => 'Classic fit 100% combed cotton pique polo with ribbed collar.',
                'status' => 'active',
            ]
        );

        $variantPoloNavyM = ProductVariant::query()->firstOrCreate(
            ['sku' => 'POLO-PREM-NAVY-M'],
            [
                'product_id' => $productPolo->id,
                'size_id' => $sizeM->id,
                'color_id' => $colorNavy->id,
                'variant_name' => 'Premium Polo / Navy / M',
                'cost_price' => 7.50,
                'selling_price' => 18.00,
                'status' => 'active',
            ]
        );

        // 8. Engineering Bill of Materials (BOM)
        $bomHeader = BomHeader::query()->firstOrCreate(
            ['code' => 'BOM-POLO-PREM-001'],
            [
                'product_id' => $productPolo->id,
                'name' => 'Premium Polo Standard Technical BOM',
                'status' => 'active',
                'description' => 'Certified production specification for Premium Polo Shirt.',
            ]
        );

        $bomVersion = BomVersion::query()->firstOrCreate(
            ['bom_header_id' => $bomHeader->id, 'version_number' => 1],
            ['effective_from' => '2026-01-01', 'status' => 'active', 'notes' => 'Factory floor v1.0 standard.']
        );

        BomItem::query()->firstOrCreate(
            ['bom_version_id' => $bomVersion->id, 'material_id' => $matCotton->id],
            ['unit_id' => $unitKg->id, 'quantity' => 0.28, 'wastage_percentage' => 4.00, 'line_number' => 1, 'notes' => 'Pique knit body fabric']
        );
        BomItem::query()->firstOrCreate(
            ['bom_version_id' => $bomVersion->id, 'material_id' => $matButtons->id],
            ['unit_id' => $unitPcs->id, 'quantity' => 3.00, 'wastage_percentage' => 2.00, 'line_number' => 2, 'notes' => 'Placket buttons']
        );
        BomItem::query()->firstOrCreate(
            ['bom_version_id' => $bomVersion->id, 'material_id' => $matThread->id],
            ['unit_id' => $unitCones->id, 'quantity' => 0.05, 'wastage_percentage' => 1.00, 'line_number' => 3, 'notes' => 'Assembly stitching thread']
        );

        // 9. Warehouses & Bin Locations
        $whRaw = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-RAW-01'],
            ['name' => 'Central Fabric & Trims Warehouse', 'address' => 'Block A, Industrial Park, Gazipur', 'status' => 'active']
        );
        $whFG = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-FG-01'],
            ['name' => 'Finished Goods Export Warehouse', 'address' => 'Export Zone, Chittagong Port Access Road', 'status' => 'active']
        );

        WarehouseLocation::query()->firstOrCreate(['warehouse_id' => $whRaw->id, 'code' => 'LOC-RAW-A1'], ['name' => 'Fabric Staging Bay A1', 'status' => 'active']);
        WarehouseLocation::query()->firstOrCreate(['warehouse_id' => $whRaw->id, 'code' => 'LOC-TRM-B1'], ['name' => 'Accessories Vault B1', 'status' => 'active']);
        WarehouseLocation::query()->firstOrCreate(['warehouse_id' => $whFG->id, 'code' => 'LOC-FG-BAY1'], ['name' => 'Export Shipment Bay 1', 'status' => 'active']);
    }

    /** @param array<int, string> $permissionSlugs */
    private function syncRolePermissions(Role $role, array $permissionSlugs): void
    {
        $ids = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching($ids);
    }
}
