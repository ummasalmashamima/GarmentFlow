<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SupplyPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\BOMSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_lifecycle_reuses_bom_consumes_inventory_tracks_progress_and_posts_finished_goods(): void
    {
        [$user, $product, $variant, $material, $unit, $warehouse, $location] = $this->fixtures(['production.view', 'production.manage', 'production.approve', 'production.override', 'inventory.view', 'inventory.manage']);
        $token = $this->token($user, ['production.view', 'production.manage', 'production.approve', 'production.override', 'inventory.view', 'inventory.manage']);
        $supplyPlan = SupplyPlan::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'confirmed_order_quantity' => 100,
            'forecast_quantity' => 0,
            'required_quantity' => 100,
            'available_quantity' => 0,
            'planned_production_quantity' => 100,
            'status' => 'calculated',
            'created_by' => $user->id,
        ]);

        $this->withToken($token)->postJson('/api/inventory/stock-in', [
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 157.5,
            'remarks' => 'Production fixture opening stock',
        ])->assertCreated();

        $planId = $this->withToken($token)->postJson('/api/production/plans', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'supply_plan_id' => $supplyPlan->id,
            'planned_quantity' => 100,
            'planned_start_date' => '2026-03-01',
            'planned_end_date' => '2026-03-31',
            'priority' => 'high',
            'remarks' => 'Production lifecycle fixture',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->withToken($token)->postJson("/api/production/plans/{$planId}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $orderId = $this->withToken($token)->postJson('/api/production/orders', [
            'production_plan_id' => $planId,
            'expected_completion_date' => '2026-03-31',
            'issue_warehouse_id' => $warehouse->id,
            'issue_warehouse_location_id' => $location->id,
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data.id');
        $orderItemId = $this->withToken($token)->getJson("/api/production/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.required_quantity', '157.5000')
            ->assertJsonPath('data.items.0.bom_quantity', '1.5000')
            ->assertJsonPath('data.items.0.wastage_percentage', '5.0000')
            ->json('data.items.0.id');

        $this->withToken($token)->getJson("/api/production/orders/{$orderId}/availability")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.lines.0.required_quantity', 157.5)
            ->assertJsonPath('data.lines.0.available_quantity', 157.5)
            ->assertJsonPath('data.lines.0.shortage_quantity', 0);

        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/start", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/consume", [
            'production_order_item_id' => $orderItemId,
            'quantity' => 157.5,
            'consumption_date' => '2026-03-15',
            'idempotency_key' => 'production-test-consumption-1',
        ])->assertCreated()->assertJsonPath('data.quantity', '157.5000');
        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/consume", [
            'production_order_item_id' => $orderItemId,
            'quantity' => 157.5,
            'consumption_date' => '2026-03-15',
            'idempotency_key' => 'production-test-consumption-1',
        ])->assertCreated();
        $this->assertDatabaseCount('material_consumptions', 1);
        $this->assertDatabaseHas('inventory_transactions', ['transaction_type' => 'STOCK_OUT', 'quantity' => '157.5000']);
        $this->assertDatabaseHas('inventory_balances', ['material_id' => $material->id, 'quantity_on_hand' => '0.0000']);

        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/progress", [
            'completed_quantity' => 50,
            'rejected_quantity' => 0,
            'production_date' => '2026-03-20',
        ])->assertCreated()->assertJsonPath('data.progress_percentage', '50.0000');
        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/progress", [
            'completed_quantity' => 100,
            'rejected_quantity' => 0,
            'production_date' => '2026-03-25',
        ])->assertCreated()->assertJsonPath('data.progress_percentage', '100.0000');

        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/complete", [
            'finished_quantity' => 100,
            'completed_quantity' => 100,
            'rejected_quantity' => 0,
            'finished_date' => '2026-03-31',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('finished_goods', ['production_order_id' => $orderId, 'quantity' => '100.0000']);
        $this->assertDatabaseHas('inventory_transactions', ['transaction_type' => 'STOCK_IN', 'product_variant_id' => $variant->id, 'quantity' => '100.0000']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => '100.0000']);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseCount('production_progress', 3);
        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/complete", [
            'finished_quantity' => 100,
            'completed_quantity' => 100,
            'rejected_quantity' => 0,
            'finished_date' => '2026-03-31',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $history = $this->withToken($token)->getJson('/api/production/history')->assertOk();
        $this->assertGreaterThan(0, count($history->json('data')));
        $this->assertGreaterThanOrEqual(1, InventoryBalance::query()->where('product_variant_id', $variant->id)->count());
        $this->assertGreaterThanOrEqual(1, InventoryTransaction::query()->where('transaction_type', 'STOCK_OUT')->count());
    }

    public function test_shortage_and_overproduction_require_explicit_override_and_invalid_transitions_are_rejected(): void
    {
        [$user, $product, $variant, , , $warehouse, $location] = $this->fixtures(['production.view', 'production.manage', 'production.approve']);
        $token = $this->token($user, ['production.view', 'production.manage', 'production.approve']);
        $supplyPlan = SupplyPlan::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'confirmed_order_quantity' => 100,
            'forecast_quantity' => 0,
            'required_quantity' => 100,
            'available_quantity' => null,
            'planned_production_quantity' => 100,
            'status' => 'pending_inventory',
            'created_by' => $user->id,
        ]);
        $planId = $this->withToken($token)->postJson('/api/production/plans', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'supply_plan_id' => $supplyPlan->id,
            'planned_quantity' => 100,
            'planned_start_date' => '2026-04-01',
            'planned_end_date' => '2026-04-30',
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson("/api/production/plans/{$planId}/approve", [])->assertOk();
        $orderId = $this->withToken($token)->postJson('/api/production/orders', [
            'production_plan_id' => $planId,
            'issue_warehouse_id' => $warehouse->id,
            'issue_warehouse_location_id' => $location->id,
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->getJson("/api/production/orders/{$orderId}/availability")
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.lines.0.shortage_quantity', 157.5);
        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/start", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability']);
        $this->withToken($token)->postJson("/api/production/orders/{$orderId}/status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->grant($user, ['production.override']);
        app('auth')->forgetGuards();
        $overrideToken = $this->token($user, ['production.view', 'production.manage', 'production.approve', 'production.override']);
        $this->withToken($overrideToken)->postJson("/api/production/orders/{$orderId}/start", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->withToken($overrideToken)->postJson("/api/production/orders/{$orderId}/progress", [
            'completed_quantity' => 110,
            'rejected_quantity' => 0,
            'production_date' => '2026-04-15',
        ])->assertCreated()->assertJsonPath('data.progress_percentage', '110.0000');
    }

    public function test_production_view_and_approve_permissions_are_separate(): void
    {
        [$user, $product, $variant] = $this->fixtures(['production.view']);
        $viewToken = $this->token($user, ['production.view']);
        $this->withToken($viewToken)->getJson('/api/production/plans')->assertOk();
        $this->withToken($viewToken)->postJson('/api/production/plans', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'planned_quantity' => 10,
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-05',
            'supply_plan_id' => 1,
        ])->assertForbidden();
    }

    /** @return array{0: User, 1: Product, 2: ProductVariant, 3: Material, 4: Unit, 5: Warehouse, 6: WarehouseLocation} */
    private function fixtures(array $permissionSlugs): array
    {
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class, BOMSeeder::class]);
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'production-test-'.md5(implode(',', $permissionSlugs))], ['name' => 'Production Test Role']);
        $this->grant($user, $permissionSlugs, $role);
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail();
        $material = Material::query()->where('code', 'FAB-COT-001')->firstOrFail();
        $unit = Unit::query()->where('code', 'KG')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'DHK-01')->firstOrFail();
        $location = WarehouseLocation::query()->where('code', 'A-01-01')->firstOrFail();

        return [$user->load('roles.permissions'), $product, $variant, $material, $unit, $warehouse, $location];
    }

    private function grant(User $user, array $slugs, ?Role $role = null): void
    {
        $role ??= $user->roles()->firstOrFail();
        if (! $user->roles()->whereKey($role->id)->exists()) {
            $user->roles()->attach($role);
        }
        $permissions = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching($permissions);
        $user->load('roles.permissions');
    }

    /** @param array<int, string> $abilities */
    private function token(User $user, array $abilities): string
    {
        return $user->createToken('production-test', $abilities)->plainTextToken;
    }
}
