<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'EXEC', 'name' => 'Executive Management'],
            ['code' => 'SCM', 'name' => 'Supply Chain Management'],
            ['code' => 'PROD', 'name' => 'Production'],
            ['code' => 'PROC', 'name' => 'Procurement'],
            ['code' => 'WH', 'name' => 'Warehouse & Inventory'],
            ['code' => 'FIN', 'name' => 'Finance & Accounts'],
        ];

        foreach ($departments as $dept) {
            Department::query()->firstOrCreate(['code' => $dept['code']], $dept);
        }

        $adminRole = Role::query()->firstOrCreate(
            ['slug' => 'administrator'],
            ['name' => 'Administrator', 'description' => 'Full administrative access across all modules.']
        );

        $operatorRole = Role::query()->firstOrCreate(
            ['slug' => 'operator'],
            ['name' => 'Operator', 'description' => 'General operational access.']
        );

        $permissions = [
            ['slug' => 'dashboard.view', 'name' => 'View Dashboards', 'description' => 'Access overview dashboard'],
            ['slug' => 'dashboard.executive.view', 'name' => 'View Executive Dashboard', 'description' => 'Access executive analytics'],
            ['slug' => 'dashboard.supply-chain.view', 'name' => 'View Supply Chain Dashboard', 'description' => 'Access supply chain analytics'],
            ['slug' => 'dashboard.production.view', 'name' => 'View Production Dashboard', 'description' => 'Access production analytics'],
            ['slug' => 'dashboard.procurement.view', 'name' => 'View Procurement Dashboard', 'description' => 'Access procurement analytics'],
            ['slug' => 'dashboard.warehouse.view', 'name' => 'View Warehouse Dashboard', 'description' => 'Access warehouse analytics'],
            ['slug' => 'reports.view', 'name' => 'View Reports', 'description' => 'Access system reports'],
            ['slug' => 'reports.export', 'name' => 'Export Reports', 'description' => 'Export reporting data to CSV'],
            ['slug' => 'alerts.view', 'name' => 'View Alerts', 'description' => 'Access alert notifications'],
            ['slug' => 'alerts.manage', 'name' => 'Manage Alerts', 'description' => 'Acknowledge and manage system alerts'],
            ['slug' => 'master-data.view', 'name' => 'View Master Data', 'description' => 'Access master data registers'],
            ['slug' => 'master-data.manage', 'name' => 'Manage Master Data', 'description' => 'Create and modify master data'],
            ['slug' => 'bom.view', 'name' => 'View BOM', 'description' => 'View Bills of Materials'],
            ['slug' => 'bom.manage', 'name' => 'Manage BOM', 'description' => 'Create, modify and activate Bills of Materials'],
            ['slug' => 'buyer-order.view', 'name' => 'View Buyer Orders', 'description' => 'Access buyer order register'],
            ['slug' => 'buyer-order.manage', 'name' => 'Manage Buyer Orders', 'description' => 'Create and modify buyer orders'],
            ['slug' => 'buyer-order.approve', 'name' => 'Approve Buyer Orders', 'description' => 'Review and approve buyer orders'],
            ['slug' => 'buyer-order.confirm', 'name' => 'Confirm Buyer Orders', 'description' => 'Confirm buyer orders for production'],
            ['slug' => 'planning.view', 'name' => 'View Planning', 'description' => 'View forecasts and supply plans'],
            ['slug' => 'planning.manage', 'name' => 'Manage Planning', 'description' => 'Create forecasts, supply plans and MRP runs'],
            ['slug' => 'procurement.view', 'name' => 'View Procurement', 'description' => 'View purchase requisitions and purchase orders'],
            ['slug' => 'procurement.manage', 'name' => 'Manage Procurement', 'description' => 'Create and edit procurement documents'],
            ['slug' => 'procurement.approve', 'name' => 'Approve Procurement', 'description' => 'Approve requisitions and purchase orders'],
            ['slug' => 'inventory.view', 'name' => 'View Inventory', 'description' => 'View stock balances and transactions'],
            ['slug' => 'inventory.manage', 'name' => 'Manage Inventory', 'description' => 'Perform stock movements and transfers'],
            ['slug' => 'inventory.adjust', 'name' => 'Adjust Inventory', 'description' => 'Perform stock adjustments'],
            ['slug' => 'production.view', 'name' => 'View Production', 'description' => 'View production plans and orders'],
            ['slug' => 'production.manage', 'name' => 'Manage Production', 'description' => 'Create production plans and orders'],
            ['slug' => 'production.approve', 'name' => 'Approve Production', 'description' => 'Approve and schedule production plans'],
            ['slug' => 'production.override', 'name' => 'Override Production', 'description' => 'Override material shortage during production start'],
            ['slug' => 'sales.view', 'name' => 'View Sales', 'description' => 'View sales orders and history'],
            ['slug' => 'sales.manage', 'name' => 'Manage Sales', 'description' => 'Create and update sales orders'],
            ['slug' => 'sales.confirm', 'name' => 'Confirm Sales', 'description' => 'Confirm sales orders against inventory'],
            ['slug' => 'sales.override', 'name' => 'Override Sales', 'description' => 'Override stock shortfall for sales order confirmation'],
            ['slug' => 'delivery.view', 'name' => 'View Deliveries', 'description' => 'View deliveries and shipment tracking'],
            ['slug' => 'delivery.manage', 'name' => 'Manage Deliveries', 'description' => 'Create and edit delivery notes'],
            ['slug' => 'delivery.dispatch', 'name' => 'Dispatch Deliveries', 'description' => 'Dispatch deliveries and deduct finished stock'],
            ['slug' => 'delivery.override', 'name' => 'Override Deliveries', 'description' => 'Override delivery validation constraints'],
            ['slug' => 'finance.view', 'name' => 'View Finance', 'description' => 'View invoices and payment ledgers'],
            ['slug' => 'finance.manage', 'name' => 'Manage Finance', 'description' => 'Generate and manage customer invoices'],
            ['slug' => 'finance.pay', 'name' => 'Process Payments', 'description' => 'Record and manage payments against invoices'],
            ['slug' => 'finance.override', 'name' => 'Override Finance', 'description' => 'Override finance controls'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $record = Permission::query()->firstOrCreate(
                ['slug' => $perm['slug']],
                ['name' => $perm['name'], 'description' => $perm['description']]
            );
            $permissionIds[] = $record->id;
        }

        $adminRole->permissions()->syncWithoutDetaching($permissionIds);

        $defaultExecDept = Department::query()->where('code', 'EXEC')->first();

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@garmentflow.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'department_id' => $defaultExecDept?->id,
                'status' => 'active',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $operator = User::query()->firstOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name' => 'GarmentFlow Operator',
                'password' => Hash::make('password'),
                'department_id' => $defaultExecDept?->id,
                'status' => 'active',
            ]
        );
        $operator->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
