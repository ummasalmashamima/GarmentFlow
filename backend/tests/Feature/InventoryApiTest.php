<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_out_summary_history_and_available_quantity_are_controlled(): void
    {
        [$user, $material, $unit, , $warehouse, $location] = $this->fixtures();
        $token = $this->token($user, ['inventory.view', 'inventory.manage']);
        $payload = [
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 100,
            'remarks' => 'Opening stock receipt',
        ];

        $this->withToken($token)->postJson('/api/inventory/stock-in', $payload)
            ->assertCreated()
            ->assertJsonPath('data.transaction_type', 'STOCK_IN')
            ->assertJsonPath('data.quantity', '100.0000');
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/inventory/stock-out', [...$payload, 'quantity' => 30, 'remarks' => 'Controlled issue'])
            ->assertCreated()
            ->assertJsonPath('data.transaction_type', 'STOCK_OUT')
            ->assertJsonPath('data.inventory_balance.quantity_on_hand', '70.0000');
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/inventory?warehouse_id='.$warehouse->id.'&warehouse_location_id='.$location->id)
            ->assertOk()
            ->assertJsonPath('data.0.quantity_available', '70.0000');
        $this->withToken($token)->getJson('/api/inventory/summary?warehouse_id='.$warehouse->id)
            ->assertOk()
            ->assertJsonPath('data.quantity_on_hand', 70);
        $this->withToken($token)->getJson('/api/inventory/history?transaction_type=STOCK_OUT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_type', 'STOCK_OUT');
    }

    public function test_insufficient_stock_negative_quantity_unit_and_location_rules_are_enforced(): void
    {
        [$user, $material, $unit, , $warehouse, $location] = $this->fixtures();
        $token = $this->token($user, ['inventory.view', 'inventory.manage']);
        $base = ['warehouse_id' => $warehouse->id, 'warehouse_location_id' => $location->id, 'material_id' => $material->id, 'unit_id' => $unit->id];

        $this->withToken($token)->postJson('/api/inventory/stock-out', [...$base, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/inventory/stock-in', [...$base, 'quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/inventory/stock-in', [...$base, 'unit_id' => Unit::query()->where('code', 'PCS')->firstOrFail()->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_id']);

        $otherWarehouse = Warehouse::query()->create(['code' => 'DHK-02', 'name' => 'Secondary warehouse', 'status' => 'active']);
        $otherLocation = WarehouseLocation::query()->create(['warehouse_id' => $otherWarehouse->id, 'code' => 'B-01-01', 'name' => 'Secondary bay', 'location_type' => 'bin', 'status' => 'active']);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/inventory/stock-in', [...$base, 'warehouse_location_id' => $otherLocation->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_location_id']);
    }

    public function test_transfer_creates_paired_transactions_and_is_atomic_on_failure(): void
    {
        [$user, $material, $unit, , $sourceWarehouse, $sourceLocation] = $this->fixtures();
        $destinationWarehouse = Warehouse::query()->create(['code' => 'DHK-02', 'name' => 'Secondary warehouse', 'status' => 'active']);
        $destinationLocation = WarehouseLocation::query()->create(['warehouse_id' => $destinationWarehouse->id, 'code' => 'B-01-01', 'name' => 'Secondary bay', 'location_type' => 'bin', 'status' => 'active']);
        $token = $this->token($user, ['inventory.view', 'inventory.manage']);
        $base = ['material_id' => $material->id, 'unit_id' => $unit->id];
        $this->withToken($token)->postJson('/api/inventory/stock-in', [...$base, 'warehouse_id' => $sourceWarehouse->id, 'warehouse_location_id' => $sourceLocation->id, 'quantity' => 70])->assertCreated();
        app('auth')->forgetGuards();

        $transferPayload = [
            'source_warehouse_id' => $sourceWarehouse->id,
            'source_location_id' => $sourceLocation->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'destination_location_id' => $destinationLocation->id,
            'items' => [[...$base, 'quantity' => 20]],
        ];
        $transferId = $this->withToken($token)->postJson('/api/inventory/transfers', $transferPayload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->json('data.id');
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseHas('inventory_transactions', ['reference_id' => 1, 'transaction_type' => 'TRANSFER_OUT']);
        $this->assertDatabaseHas('inventory_transactions', ['reference_id' => 1, 'transaction_type' => 'TRANSFER_IN']);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $sourceWarehouse->id, 'warehouse_location_id' => $sourceLocation->id, 'quantity_on_hand' => '50.0000']);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $destinationWarehouse->id, 'warehouse_location_id' => $destinationLocation->id, 'quantity_on_hand' => '20.0000']);
        app('auth')->forgetGuards();

        $this->withToken($token)->postJson('/api/inventory/transfers', [...$transferPayload, 'items' => [[...$base, 'quantity' => 60]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
        $this->assertDatabaseCount('stock_transfers', 1);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertNotNull($transferId);
    }

    public function test_adjustment_requires_separate_permission_and_reason(): void
    {
        [$user, $material, $unit, , $warehouse] = $this->fixtures();
        $viewToken = $this->token($user, ['inventory.view', 'inventory.manage']);
        $payload = ['direction' => 'IN', 'warehouse_id' => $warehouse->id, 'reason' => '', 'items' => [['material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 5]]];

        $this->withToken($viewToken)->postJson('/api/inventory/adjustments', $payload)->assertForbidden();
        app('auth')->forgetGuards();
        $adjustToken = $this->token($user, ['inventory.view', 'inventory.adjust']);
        $this->withToken($adjustToken)->postJson('/api/inventory/adjustments', $payload)->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        app('auth')->forgetGuards();
        $this->withToken($adjustToken)->postJson('/api/inventory/adjustments', [...$payload, 'reason' => 'Cycle count correction'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'IN');
        $this->assertDatabaseHas('inventory_transactions', ['transaction_type' => 'ADJUSTMENT_IN', 'quantity' => '5.0000']);
    }

    public function test_inventory_filters_keep_sort_direction_separate_from_adjustment_direction(): void
    {
        [$user, $material, $unit, , $warehouse] = $this->fixtures();
        $token = $this->token($user, ['inventory.view', 'inventory.manage', 'inventory.adjust']);
        $this->withToken($token)->postJson('/api/inventory/stock-in', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 5,
        ])->assertCreated();
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/inventory?direction=desc')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/inventory/summary?direction=desc')->assertOk()->assertJsonPath('data.quantity_on_hand', 5);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/inventory/adjustments', [
            'direction' => 'IN',
            'warehouse_id' => $warehouse->id,
            'reason' => 'Filter contract test',
            'items' => [['material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 2]],
        ])->assertCreated();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/inventory/adjustments?adjustment_direction=IN&direction=desc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'IN');
    }

    public function test_accepted_goods_receipt_posts_only_accepted_quantity_and_is_idempotent(): void
    {
        [$user, $material, $unit, $supplier, $warehouse] = $this->fixtures();
        $token = $this->token($user, ['inventory.view', 'inventory.manage', 'procurement.view', 'procurement.manage']);
        $order = PurchaseOrder::query()->create([
            'purchase_order_number' => 'PO-INV-0001',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-23',
            'expected_delivery_date' => '2026-09-01',
            'currency' => 'USD',
            'subtotal' => 100,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 100,
            'status' => 'sent_to_supplier',
            'created_by' => $user->id,
        ]);
        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 100,
            'unit_price' => 1,
            'line_total' => 100,
            'received_quantity' => 0,
            'line_number' => 1,
        ]);
        $receipt = GoodsReceipt::query()->create([
            'receipt_number' => 'GRN-INV-0001',
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => '2026-08-23',
            'received_by' => $user->id,
            'status' => 'accepted',
        ]);
        $receiptItem = GoodsReceiptItem::query()->create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'ordered_quantity' => 100,
            'received_quantity' => 60,
            'accepted_quantity' => 55,
            'rejected_quantity' => 5,
            'line_number' => 1,
        ]);

        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$receipt->id}/post", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');
        $this->assertDatabaseHas('inventory_transactions', ['reference_type' => GoodsReceiptItem::class, 'reference_id' => $receiptItem->id, 'transaction_type' => 'STOCK_IN', 'quantity' => '55.0000']);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'quantity_on_hand' => '55.0000']);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $poItem->id, 'received_quantity' => '60.0000']);
        $this->assertDatabaseCount('inventory_transactions', 1);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$receipt->id}/post", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    /** @return array{0: User, 1: Material, 2: Unit, 3: Supplier, 4: Warehouse, 5: WarehouseLocation} */
    private function fixtures(): array
    {
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role);

        return [
            $user->load('roles.permissions'),
            Material::query()->where('code', 'FAB-COT-001')->firstOrFail(),
            Unit::query()->where('code', 'KG')->firstOrFail(),
            Supplier::query()->where('code', 'SUP-001')->firstOrFail(),
            Warehouse::query()->where('code', 'DHK-01')->firstOrFail(),
            WarehouseLocation::query()->where('code', 'A-01-01')->firstOrFail(),
        ];
    }

    /** @param array<int, string> $abilities */
    private function token(User $user, array $abilities): string
    {
        return $user->createToken('inventory-test', $abilities)->plainTextToken;
    }
}
