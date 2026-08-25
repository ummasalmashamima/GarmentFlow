<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_lifecycle_supports_approvals_partial_conversion_partial_receipts_and_traceability(): void
    {
        [$user, $material, $unit, $supplier, $warehouse] = $this->fixtures();
        $token = $this->token($user, ['procurement.view', 'procurement.manage', 'procurement.approve']);
        $secondMaterial = Material::query()->create([
            'material_category_id' => $material->material_category_id,
            'unit_id' => $unit->id,
            'code' => 'FAB-COT-002',
            'name' => 'Organic cotton ribbing',
            'material_type' => 'Fabric',
            'status' => 'active',
        ]);

        $requisitionId = $this->withToken($token)->postJson('/api/procurement/requisitions', [
            'request_date' => '2026-08-23',
            'required_date' => '2026-09-15',
            'priority' => 'high',
            'source' => 'MRP-20260823-0001',
            'items' => [
                ['material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 100, 'remarks' => 'Main fabric'],
                ['material_id' => $secondMaterial->id, 'unit_id' => $unit->id, 'quantity' => 50, 'remarks' => 'Trim fabric'],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.items')->json('data.id');

        $this->withToken($token)->postJson("/api/procurement/requisitions/{$requisitionId}/submit", ['remarks' => 'Submit for approval'])
            ->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->withToken($token)->postJson("/api/procurement/requisitions/{$requisitionId}/approve", ['remarks' => 'Approved'])
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $firstOrderId = $this->withToken($token)->postJson('/api/procurement/purchase-orders', [
            'purchase_requisition_id' => $requisitionId,
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-23',
            'expected_delivery_date' => '2026-09-10',
            'currency' => 'USD',
            'payment_terms' => '30 days',
            'shipping_terms' => 'FOB',
            'tax_total' => 10,
            'discount_total' => 5,
            'items' => [
                ['purchase_requisition_item_id' => 1, 'quantity' => 60, 'unit_price' => 4],
                ['purchase_requisition_item_id' => 2, 'quantity' => 50, 'unit_price' => 5],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.subtotal', '490.0000')->assertJsonPath('data.total_amount', '495.0000')->json('data.id');

        $this->assertDatabaseHas('purchase_requisitions', ['id' => $requisitionId, 'status' => 'approved']);
        $this->assertDatabaseHas('purchase_requisition_items', ['purchase_requisition_id' => $requisitionId, 'converted_quantity' => '60.0000']);

        $this->withToken($token)->postJson('/api/procurement/purchase-orders', [
            'purchase_requisition_id' => $requisitionId,
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-23',
            'expected_delivery_date' => '2026-09-10',
            'currency' => 'USD',
            'items' => [
                ['purchase_requisition_item_id' => 1, 'quantity' => 40, 'unit_price' => 4],
            ],
        ])->assertCreated()->assertJsonPath('data.subtotal', '160.0000');
        $this->assertDatabaseHas('purchase_requisitions', ['id' => $requisitionId, 'status' => 'converted_to_po']);

        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$firstOrderId}/submit", [])->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$firstOrderId}/approve", [])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$firstOrderId}/send", [])->assertOk()->assertJsonPath('data.status', 'sent_to_supplier');

        $firstReceiptId = $this->withToken($token)->postJson('/api/procurement/goods-receipts', [
            'purchase_order_id' => $firstOrderId,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => '2026-09-08',
            'items' => [
                ['purchase_order_item_id' => 1, 'received_quantity' => 60, 'accepted_quantity' => 55, 'rejected_quantity' => 5],
                ['purchase_order_item_id' => 2, 'received_quantity' => 20, 'accepted_quantity' => 20, 'rejected_quantity' => 0],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$firstReceiptId}/receive", [])->assertOk()->assertJsonPath('data.status', 'received');
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$firstReceiptId}/accept", [])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$firstReceiptId}/post", [])->assertOk()->assertJsonPath('data.status', 'posted');
        $this->assertDatabaseHas('purchase_orders', ['id' => $firstOrderId, 'status' => 'partially_received']);

        $secondReceiptId = (int) $this->withToken($token)->postJson('/api/procurement/goods-receipts', [
            'purchase_order_id' => $firstOrderId,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => '2026-09-09',
            'items' => [
                ['purchase_order_item_id' => 2, 'received_quantity' => 30, 'accepted_quantity' => 30, 'rejected_quantity' => 0],
            ],
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$secondReceiptId}/receive", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$secondReceiptId}/accept", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$secondReceiptId}/post", [])->assertOk()->assertJsonPath('data.status', 'posted');
        $this->assertDatabaseHas('purchase_orders', ['id' => $firstOrderId, 'status' => 'fully_received']);
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$firstOrderId}/close", [])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $firstOrderId, 'received_quantity' => '60.0000']);
        $this->assertDatabaseHas('goods_receipt_items', ['goods_receipt_id' => $firstReceiptId, 'accepted_quantity' => '55.0000', 'rejected_quantity' => '5.0000']);
        $this->assertDatabaseCount('procurement_status_histories', 20);
        $this->assertDatabaseHas('audit_logs', ['module' => 'procurement-purchase-orders', 'action' => 'status_changed']);
    }

    public function test_procurement_validation_transitions_and_permissions_are_enforced(): void
    {
        [$user, $material, $unit, $supplier, $warehouse] = $this->fixtures();
        $manageToken = $this->token($user, ['procurement.view', 'procurement.manage']);
        $viewToken = $this->token($user, ['procurement.view']);
        $dashboardToken = $this->token($user, ['dashboard.view']);
        $payload = [
            'request_date' => '2026-08-23',
            'required_date' => '2026-09-15',
            'priority' => 'normal',
            'items' => [['material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 10]],
        ];

        $this->withToken($dashboardToken)->getJson('/api/procurement/requisitions')->assertForbidden();
        app('auth')->forgetGuards();
        $requisitionId = $this->withToken($manageToken)->postJson('/api/procurement/requisitions', $payload)->assertCreated()->json('data.id');
        $this->withToken($manageToken)->postJson("/api/procurement/requisitions/{$requisitionId}/convert", [
            'purchase_requisition_id' => $requisitionId,
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-23',
            'expected_delivery_date' => '2026-09-01',
            'currency' => 'USD',
            'items' => [['purchase_requisition_item_id' => 1, 'quantity' => 10, 'unit_price' => 4]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson('/api/procurement/requisitions', $payload)->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/procurement/requisitions', [
            ...$payload,
            'items' => [['material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/procurement/goods-receipts', [
            'purchase_order_id' => 999999,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => '2026-09-01',
            'items' => [['purchase_order_item_id' => 1, 'received_quantity' => 10, 'accepted_quantity' => 9, 'rejected_quantity' => 0]],
        ])->assertUnprocessable();
    }

    /** @return array{0: User, 1: Material, 2: Unit, 3: Supplier, 4: Warehouse} */
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
        ];
    }

    /** @param array<int, string> $abilities */
    private function token(User $user, array $abilities): string
    {
        return $user->createToken('procurement-test', $abilities)->plainTextToken;
    }
}
