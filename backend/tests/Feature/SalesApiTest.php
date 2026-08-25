<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflow;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_creation_totals_draft_update_submit_confirm_history_and_delivery_prep_are_supported(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-sales-workflow', [
            'sales.view',
            'sales.manage',
            'sales.confirm',
        ])->plainTextToken;
        [$buyer, $customer, $product, $firstVariant, $secondVariant, $unit, $warehouse] = $this->salesFixtures();
        $this->stockIn($user, $warehouse, $unit, $product, $firstVariant, 20);
        $this->stockIn($user, $warehouse, $unit, $product, $secondVariant, 20);

        $payload = [
            'buyer_id' => $buyer->id,
            'customer_id' => null,
            'order_date' => '2026-04-01',
            'required_delivery_date' => '2026-04-30',
            'warehouse_id' => $warehouse->id,
            'delivery_address' => '12 Commerce Road',
            'contact_information' => 'ops@example.com',
            'order_discount_amount' => 10,
            'order_tax_amount' => 5,
            'remarks' => 'Seasonal sales order.',
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $firstVariant->id, 'unit_id' => $unit->id, 'ordered_quantity' => 4, 'unit_price' => 10, 'discount_amount' => 2, 'tax_amount' => 3],
                ['product_id' => $product->id, 'product_variant_id' => $secondVariant->id, 'unit_id' => $unit->id, 'ordered_quantity' => 2, 'unit_price' => 12, 'discount_amount' => 0, 'tax_amount' => 0],
            ],
        ];

        $create = $this->withToken($token)->postJson('/api/sales/orders', $payload);
        $create->assertCreated()
            ->assertJsonPath('data.status', SalesOrderWorkflow::DRAFT)
            ->assertJsonPath('data.subtotal', '64.0000')
            ->assertJsonPath('data.discount_amount', '12.0000')
            ->assertJsonPath('data.tax_amount', '8.0000')
            ->assertJsonPath('data.total_amount', '60.0000')
            ->assertJsonPath('data.ordered_quantity', '6.0000')
            ->assertJsonPath('data.remaining_quantity', '6.0000')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.status_history');
        $orderId = $create->json('data.id');
        $order = SalesOrder::query()->findOrFail($orderId);
        $salesInventoryTransactions = InventoryTransaction::query()->where('reference_type', SalesOrder::class)->count();
        $this->assertSame(0, $salesInventoryTransactions);

        $this->withToken($token)->postJson('/api/sales/orders/preview', [
            'order_discount_amount' => 10,
            'order_tax_amount' => 5,
            'items' => $payload['items'],
        ])->assertOk()
            ->assertJsonPath('data.subtotal', '64.0000')
            ->assertJsonPath('data.total_amount', '60.0000');

        $payload['items'][0]['ordered_quantity'] = 5;
        $this->withToken($token)->putJson("/api/sales/orders/{$orderId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.ordered_quantity', '7.0000')
            ->assertJsonPath('data.total_amount', '70.0000');

        $this->withToken($token)->postJson("/api/sales/orders/{$orderId}/submit", ['remarks' => 'Submit for stock confirmation.'])
            ->assertOk()
            ->assertJsonPath('data.status', SalesOrderWorkflow::SUBMITTED);
        $this->withToken($token)->putJson("/api/sales/orders/{$orderId}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken($token)->getJson("/api/sales/orders/{$orderId}/availability")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonCount(2, 'data.lines');

        $this->withToken($token)->postJson("/api/sales/orders/{$orderId}/confirm", ['remarks' => 'Finished goods confirmed.'])
            ->assertOk()
            ->assertJsonPath('data.status', SalesOrderWorkflow::CONFIRMED)
            ->assertJsonPath('data.confirmed_quantity', '7.0000')
            ->assertJsonPath('data.remaining_quantity', '7.0000');
        $this->assertSame($salesInventoryTransactions, InventoryTransaction::query()->where('reference_type', SalesOrder::class)->count());

        $this->withToken($token)->postJson("/api/sales/orders/{$orderId}/status", ['status' => SalesOrderWorkflow::READY_FOR_DELIVERY])
            ->assertOk()
            ->assertJsonPath('data.status', SalesOrderWorkflow::READY_FOR_DELIVERY);
        $this->withToken($token)->postJson("/api/sales/orders/{$orderId}/status", ['status' => SalesOrderWorkflow::COMPLETED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken($token)->getJson("/api/sales/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.sales_order_number', $order->sales_order_number)
            ->assertJsonCount(4, 'data.status_history');
        $this->withToken($token)->getJson("/api/sales/orders/{$orderId}/status-history")
            ->assertOk()
            ->assertJsonFragment(['new_status' => SalesOrderWorkflow::CONFIRMED]);
        $this->withToken($token)->getJson('/api/sales/history?action=status_changed&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
        $this->assertDatabaseHas('audit_logs', ['module' => 'sales-orders', 'record_id' => $orderId, 'action' => 'created']);
    }

    public function test_insufficient_finished_goods_prevent_confirmation_and_explicit_override_can_confirm(): void
    {
        $user = $this->administrator();
        $normalToken = $user->createToken('test-sales-confirm', ['sales.view', 'sales.manage', 'sales.confirm'])->plainTextToken;
        $overrideToken = $user->createToken('test-sales-override', ['sales.view', 'sales.manage', 'sales.confirm', 'sales.override'])->plainTextToken;
        [$buyer, $customer, $product, $variant, , $unit, $warehouse] = $this->salesFixtures();
        $orderId = $this->createOrder($normalToken, $buyer->id, null, $product->id, $variant->id, $unit->id, $warehouse->id, 5);
        $this->withToken($normalToken)->postJson("/api/sales/orders/{$orderId}/submit")->assertOk();
        $this->withToken($normalToken)->getJson("/api/sales/orders/{$orderId}/availability")
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.lines.0.shortage_quantity', '5.0000');
        $this->withToken($normalToken)->postJson("/api/sales/orders/{$orderId}/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability']);
        $this->assertDatabaseHas('sales_orders', ['id' => $orderId, 'status' => SalesOrderWorkflow::SUBMITTED]);

        app('auth')->forgetGuards();
        $this->withToken($overrideToken)->postJson("/api/sales/orders/{$orderId}/confirm", ['remarks' => 'Authorized shortage override.'])
            ->assertOk()
            ->assertJsonPath('data.status', SalesOrderWorkflow::CONFIRMED);
    }

    public function test_customer_party_filters_cancel_invalid_transition_and_authorization_are_enforced(): void
    {
        $user = $this->administrator();
        $manageToken = $user->createToken('test-sales-customer', ['sales.view', 'sales.manage', 'sales.confirm'])->plainTextToken;
        $viewToken = $user->createToken('test-sales-view', ['sales.view'])->plainTextToken;
        $dashboardToken = $user->createToken('test-sales-dashboard', ['dashboard.view'])->plainTextToken;
        [$buyer, $customer, $product, $variant, , $unit, $warehouse] = $this->salesFixtures();

        $orderId = $this->createOrder($manageToken, null, $customer->id, $product->id, $variant->id, $unit->id, $warehouse->id, 1);
        $this->withToken($viewToken)->getJson('/api/sales/orders?customer_id='.$customer->id.'&status=draft&search=SO-&per_page=1&sort=sales_order_number&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1);
        app('auth')->forgetGuards();
        $this->withToken($dashboardToken)->getJson('/api/sales/orders')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson('/api/sales/orders', [])->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson("/api/sales/orders/{$orderId}/confirm")->assertForbidden();

        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson("/api/sales/orders/{$orderId}/cancel", ['remarks' => 'Customer cancelled.'])
            ->assertOk()
            ->assertJsonPath('data.status', SalesOrderWorkflow::CANCELLED);
        $this->withToken($manageToken)->postJson("/api/sales/orders/{$orderId}/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->withToken($manageToken)->postJson("/api/sales/orders/{$orderId}/status", ['status' => SalesOrderWorkflow::DELIVERED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /** @return array{0: Buyer, 1: Customer, 2: Product, 3: ProductVariant, 4: ProductVariant, 5: Unit, 6: Warehouse} */
    private function salesFixtures(): array
    {
        $buyer = Buyer::query()->where('code', 'BUY-001')->firstOrFail();
        $customer = Customer::query()->where('code', 'CUS-001')->firstOrFail();
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $firstVariant = ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail();
        $secondVariant = ProductVariant::query()->firstOrCreate(
            ['sku' => 'TEE-CLASSIC-S-NAVY'],
            [
                'product_id' => $product->id,
                'size_id' => $firstVariant->size_id,
                'color_id' => $firstVariant->color_id,
                'variant_name' => 'Classic cotton tee / Small / Navy',
                'cost_price' => 5.5,
                'selling_price' => 12,
                'status' => 'active',
            ],
        );
        $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'DHK-01')->firstOrFail();

        return [$buyer, $customer, $product, $firstVariant, $secondVariant, $unit, $warehouse];
    }

    private function createOrder(string $token, ?int $buyerId, ?int $customerId, int $productId, int $variantId, int $unitId, int $warehouseId, int $quantity): int
    {
        return $this->withToken($token)->postJson('/api/sales/orders', [
            'buyer_id' => $buyerId,
            'customer_id' => $customerId,
            'order_date' => '2026-05-01',
            'required_delivery_date' => '2026-05-15',
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
            'idempotency_key' => 'sales-test-stock-in-'.$variant->id,
        ], $actor);
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
