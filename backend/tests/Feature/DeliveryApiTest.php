<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Customer;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryWorkflow;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflow;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_creation_partial_dispatch_inventory_traceability_tracking_and_final_progress_are_supported(): void
    {
        $user = $this->administrator();
        $salesToken = $user->createToken('test-delivery-sales', [
            'sales.view',
            'sales.manage',
            'sales.confirm',
        ])->plainTextToken;
        $deliveryToken = $user->createToken('test-delivery-workflow', [
            'delivery.view',
            'delivery.manage',
            'delivery.dispatch',
        ])->plainTextToken;
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->fixtures();
        $this->stockIn($user, $warehouse, $unit, $product, $variant, 10);
        $salesOrderId = $this->createConfirmedSalesOrder($salesToken, $buyer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 5);

        $create = $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-24',
            'expected_delivery_date' => '2026-08-31',
            'carrier_name' => 'Dhaka Express',
            'tracking_number' => 'DE-0001',
            'remarks' => 'First partial delivery.',
            'items' => [[
                'sales_order_item_id' => SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id,
                'delivery_quantity' => 2,
            ]],
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.status', DeliveryWorkflow::CREATED)
            ->assertJsonPath('data.ordered_quantity', '2.0000')
            ->assertJsonPath('data.dispatched_quantity', '0.0000')
            ->assertJsonPath('data.remaining_quantity', '2.0000')
            ->assertJsonPath('data.sales_order.status', SalesOrderWorkflow::CONFIRMED)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(1, 'data.tracking_history');
        $deliveryId = $create->json('data.id');
        $deliveryItemId = $create->json('data.items.0.id');
        $this->assertSame(10.0, $this->balanceQuantity($product, $variant, $unit, $warehouse));
        $this->assertDatabaseCount('inventory_transactions', 1);

        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::IN_TRANSIT])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::READY_FOR_SHIPMENT])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryWorkflow::READY_FOR_SHIPMENT);
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::SHIPPED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $dispatch = $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/dispatch", ['remarks' => 'Dispatch first partial.']);
        $dispatch->assertOk()
            ->assertJsonPath('data.status', DeliveryWorkflow::SHIPPED)
            ->assertJsonPath('data.dispatched_quantity', '2.0000')
            ->assertJsonPath('data.items.0.inventory_transaction.transaction_type', InventoryService::STOCK_OUT)
            ->assertJsonPath('data.items.0.inventory_transaction.idempotency_key', 'delivery-item:'.$deliveryItemId.':dispatch');
        $this->assertSame(8.0, $this->balanceQuantity($product, $variant, $unit, $warehouse));
        $this->assertSame(2, InventoryTransaction::query()->count());
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => DeliveryItem::class,
            'reference_id' => $deliveryItemId,
            'transaction_type' => InventoryService::STOCK_OUT,
            'quantity' => 2,
        ]);

        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/dispatch")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->assertSame(8.0, $this->balanceQuantity($product, $variant, $unit, $warehouse));
        $this->assertSame(2, InventoryTransaction::query()->count());

        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/tracking", [
            'carrier_name' => 'Dhaka Express',
            'tracking_number' => 'DE-0001',
            'location' => 'Dhaka Hub',
            'remarks' => 'Handed to carrier.',
        ])->assertOk()
            ->assertJsonPath('data.tracking_number', 'DE-0001');
        $this->withFreshToken($deliveryToken)->getJson("/api/deliveries/{$deliveryId}/tracking-history")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['location' => 'Dhaka Hub']);

        foreach ([DeliveryWorkflow::IN_TRANSIT, DeliveryWorkflow::OUT_FOR_DELIVERY, DeliveryWorkflow::DELIVERED] as $status) {
            $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => $status, 'remarks' => 'Progress '.$status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/complete", ['remarks' => 'Partial delivery completed.'])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryWorkflow::COMPLETED);
        $this->assertDatabaseHas('sales_order_items', ['sales_order_id' => $salesOrderId, 'delivered_quantity' => 2, 'remaining_quantity' => 3]);
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrderId, 'delivered_quantity' => 2, 'remaining_quantity' => 3, 'status' => SalesOrderWorkflow::CONFIRMED]);

        $second = $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-25',
            'items' => [[
                'sales_order_item_id' => SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id,
                'delivery_quantity' => 3,
            ]],
        ])->assertCreated();
        $secondDeliveryId = $second->json('data.id');
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$secondDeliveryId}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryWorkflow::SHIPPED);
        $this->assertSame(5.0, $this->balanceQuantity($product, $variant, $unit, $warehouse));
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrderId, 'delivered_quantity' => 5, 'remaining_quantity' => 0, 'status' => SalesOrderWorkflow::DELIVERED]);
        $this->assertDatabaseHas('sales_order_status_histories', ['sales_order_id' => $salesOrderId, 'new_status' => SalesOrderWorkflow::READY_FOR_DELIVERY]);
        $this->assertDatabaseHas('sales_order_status_histories', ['sales_order_id' => $salesOrderId, 'new_status' => SalesOrderWorkflow::DELIVERED]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'sales-orders', 'record_id' => $salesOrderId, 'action' => 'delivery_progressed']);
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$secondDeliveryId}/status", ['status' => DeliveryWorkflow::IN_TRANSIT])->assertOk();
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$secondDeliveryId}/status", ['status' => DeliveryWorkflow::OUT_FOR_DELIVERY])->assertOk();
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$secondDeliveryId}/status", ['status' => DeliveryWorkflow::DELIVERED])->assertOk();
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$secondDeliveryId}/complete")->assertOk();
        $this->withFreshToken($salesToken)->postJson("/api/sales/orders/{$salesOrderId}/status", ['status' => SalesOrderWorkflow::COMPLETED])->assertOk();
        $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-26',
            'items' => [[
                'sales_order_item_id' => SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id,
                'delivery_quantity' => 1,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['sales_order_id']);

        $this->withFreshToken($deliveryToken)->getJson('/api/deliveries?status=completed&search=DLV-&per_page=10&sort=delivery_number&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->withFreshToken($deliveryToken)->getJson('/api/deliveries/history?search=dispatched&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->withFreshToken($deliveryToken)->getJson("/api/deliveries/{$deliveryId}/history")
            ->assertOk()
            ->assertJsonPath('data.audit_logs.0.module', 'deliveries');
    }

    public function test_delivery_creation_rejects_unconfirmed_orders_invalid_quantities_and_wrong_source_identity(): void
    {
        $user = $this->administrator();
        $salesToken = $user->createToken('test-delivery-validation-sales', ['sales.view', 'sales.manage', 'sales.confirm'])->plainTextToken;
        $deliveryToken = $user->createToken('test-delivery-validation', ['delivery.view', 'delivery.manage', 'delivery.dispatch'])->plainTextToken;
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->fixtures();
        $salesOrderId = $this->createSalesOrder($salesToken, $buyer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 2);
        $salesItemId = SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id;

        $base = ['sales_order_id' => $salesOrderId, 'delivery_date' => '2026-08-24', 'items' => [['sales_order_item_id' => $salesItemId, 'delivery_quantity' => 1]]];
        $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', $base)->assertUnprocessable()->assertJsonValidationErrors(['sales_order_id']);
        $this->withFreshToken($salesToken)->postJson("/api/sales/orders/{$salesOrderId}/submit")->assertOk();
        $this->withFreshToken($salesToken)->postJson("/api/sales/orders/{$salesOrderId}/cancel")->assertOk();
        $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', $base)->assertUnprocessable()->assertJsonValidationErrors(['sales_order_id']);

        $this->stockIn($user, $warehouse, $unit, $product, $variant, 2);
        $confirmedOrderId = $this->createConfirmedSalesOrder($salesToken, $buyer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 2);
        $confirmedItemId = SalesOrder::findOrFail($confirmedOrderId)->items()->firstOrFail()->id;
        $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $confirmedOrderId,
            'delivery_date' => '2026-08-24',
            'items' => [['sales_order_item_id' => $confirmedItemId, 'delivery_quantity' => 3]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.delivery_quantity']);
        $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $confirmedOrderId,
            'delivery_date' => '2026-08-24',
            'items' => [['sales_order_item_id' => $confirmedItemId, 'product_id' => 99999, 'delivery_quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_dispatch_rechecks_sales_order_status_after_delivery_creation(): void
    {
        $user = $this->administrator();
        $salesToken = $user->createToken('test-delivery-recheck-sales', ['sales.view', 'sales.manage', 'sales.confirm'])->plainTextToken;
        $deliveryToken = $user->createToken('test-delivery-recheck-delivery', ['delivery.view', 'delivery.manage', 'delivery.dispatch'])->plainTextToken;
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->fixtures();
        $this->stockIn($user, $warehouse, $unit, $product, $variant, 1);
        $salesOrderId = $this->createConfirmedSalesOrder($salesToken, $buyer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 1);
        $salesItemId = SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id;
        $delivery = $this->withFreshToken($deliveryToken)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-24',
            'items' => [['sales_order_item_id' => $salesItemId, 'delivery_quantity' => 1]],
        ])->assertCreated();
        $deliveryId = $delivery->json('data.id');

        $this->withFreshToken($salesToken)->postJson("/api/sales/orders/{$salesOrderId}/cancel")->assertOk();
        $this->withFreshToken($deliveryToken)->postJson("/api/deliveries/{$deliveryId}/dispatch")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sales_order_id']);
        $this->assertSame(1, InventoryTransaction::query()->count());
        $this->assertSame(1.0, $this->balanceQuantity($product, $variant, $unit, $warehouse));
    }

    public function test_delivery_permissions_are_separate_and_dispatch_requires_its_own_ability(): void
    {
        $user = $this->administrator();
        $viewToken = $user->createToken('test-delivery-view', ['delivery.view'])->plainTextToken;
        $manageToken = $user->createToken('test-delivery-manage', ['delivery.view', 'delivery.manage'])->plainTextToken;
        $dashboardToken = $user->createToken('test-delivery-dashboard', ['dashboard.view'])->plainTextToken;
        $dispatchToken = $user->createToken('test-delivery-dispatch', ['delivery.view', 'delivery.manage', 'delivery.dispatch'])->plainTextToken;
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->fixtures();
        $salesToken = $user->createToken('test-delivery-permission-sales', ['sales.view', 'sales.manage', 'sales.confirm'])->plainTextToken;
        $this->stockIn($user, $warehouse, $unit, $product, $variant, 1);
        $salesOrderId = $this->createConfirmedSalesOrder($salesToken, $buyer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 1);
        $salesItemId = SalesOrder::findOrFail($salesOrderId)->items()->firstOrFail()->id;

        app('auth')->forgetGuards();
        $this->withFreshToken($dashboardToken)->getJson('/api/deliveries')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withFreshToken($viewToken)->postJson('/api/deliveries', [])->assertForbidden();
        app('auth')->forgetGuards();
        $create = $this->withFreshToken($manageToken)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-24',
            'items' => [['sales_order_item_id' => $salesItemId, 'delivery_quantity' => 1]],
        ])->assertCreated();
        $deliveryId = $create->json('data.id');
        app('auth')->forgetGuards();
        $this->withFreshToken($manageToken)->postJson("/api/deliveries/{$deliveryId}/dispatch")->assertForbidden();
        app('auth')->forgetGuards();
        $this->withFreshToken($dispatchToken)->postJson("/api/deliveries/{$deliveryId}/dispatch")->assertOk();
    }

    /** @return array{0: Buyer, 1: Customer, 2: Product, 3: ProductVariant, 4: Unit, 5: Warehouse} */
    private function fixtures(): array
    {
        $buyer = Buyer::query()->where('code', 'BUY-001')->firstOrFail();
        $customer = Customer::query()->where('code', 'CUS-001')->firstOrFail();
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail();
        $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'DHK-01')->firstOrFail();

        return [$buyer, $customer, $product, $variant, $unit, $warehouse];
    }

    private function createSalesOrder(string $token, int $buyerId, int $productId, int $variantId, int $unitId, int $warehouseId, int $quantity): int
    {
        return $this->withFreshToken($token)->postJson('/api/sales/orders', [
            'buyer_id' => $buyerId,
            'customer_id' => null,
            'order_date' => '2026-08-24',
            'required_delivery_date' => '2026-09-01',
            'warehouse_id' => $warehouseId,
            'items' => [[
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'unit_id' => $unitId,
                'ordered_quantity' => $quantity,
                'unit_price' => 12,
            ]],
        ])->assertCreated()->json('data.id');
    }

    private function createConfirmedSalesOrder(string $token, int $buyerId, int $productId, int $variantId, int $unitId, int $warehouseId, int $quantity): int
    {
        $id = $this->createSalesOrder($token, $buyerId, $productId, $variantId, $unitId, $warehouseId, $quantity);
        $this->withFreshToken($token)->postJson("/api/sales/orders/{$id}/submit")->assertOk();
        $this->withFreshToken($token)->postJson("/api/sales/orders/{$id}/confirm")->assertOk();

        return $id;
    }

    private function stockIn(User $actor, Warehouse $warehouse, Unit $unit, Product $product, ProductVariant $variant, int $quantity): void
    {
        app(InventoryService::class)->stockIn([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'unit_id' => $unit->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'reference_type' => self::class,
            'reference_id' => $variant->id,
            'idempotency_key' => 'delivery-test-stock-in-'.$variant->id.'-'.$quantity,
        ], $actor);
    }

    private function balanceQuantity(Product $product, ProductVariant $variant, Unit $unit, Warehouse $warehouse): float
    {
        $balance = InventoryBalance::query()->where('product_id', $product->id)->where('product_variant_id', $variant->id)->where('unit_id', $unit->id)->where('warehouse_id', $warehouse->id)->first();

        return (float) ($balance?->quantity_on_hand ?? 0);
    }

    private function withFreshToken(string $token)
    {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    }

    private function administrator(): User
    {
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }
}
