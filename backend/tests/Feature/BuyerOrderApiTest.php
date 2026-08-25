<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\BuyerOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_crud_totals_and_full_workflow_are_supported(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-buyer-orders', [
            'buyer-order.view',
            'buyer-order.manage',
            'buyer-order.approve',
            'buyer-order.confirm',
        ])->plainTextToken;
        [$buyer, $product, $firstVariant, $secondVariant] = $this->orderFixtures();

        $payload = [
            'buyer_id' => $buyer->id,
            'order_date' => '2026-01-02',
            'delivery_date' => '2026-02-20',
            'remarks' => 'Initial customer order.',
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $firstVariant->id, 'quantity' => 10, 'unit_price' => 12],
                ['product_id' => $product->id, 'product_variant_id' => $secondVariant->id, 'quantity' => 5, 'unit_price' => 12],
            ],
        ];

        $create = $this->withToken($token)->postJson('/api/buyer-orders', $payload);
        $create->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_quantity', '15.0000')
            ->assertJsonPath('data.total_amount', '180.0000')
            ->assertJsonCount(2, 'data.items');
        $orderId = $create->json('data.id');
        $order = BuyerOrder::query()->findOrFail($orderId);

        $this->withToken($token)->postJson('/api/buyer-orders/preview', ['items' => $payload['items']])
            ->assertOk()
            ->assertJsonPath('data.total_quantity', '15.0000')
            ->assertJsonPath('data.total_amount', '180.0000');

        $payload['items'][0]['quantity'] = 20;
        $this->withToken($token)->putJson("/api/buyer-orders/{$orderId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.total_quantity', '25.0000')
            ->assertJsonPath('data.total_amount', '300.0000');

        $this->withToken($token)->getJson("/api/buyer-orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.status_history');

        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/submit", ['remarks' => 'Ready for review.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.latest_approval.status', 'pending');

        $this->withToken($token)->putJson("/api/buyer-orders/{$orderId}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/approve", ['remarks' => 'Approved by operations.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.latest_approval.status', 'approved');

        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/confirm", ['remarks' => 'Confirmed for planning handoff.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.planning_input.status', 'ready')
            ->assertJsonPath('data.planning_input.total_quantity', '25.0000');

        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/status", ['status' => 'planning'])
            ->assertOk()
            ->assertJsonPath('data.status', 'planning')
            ->assertJsonPath('data.creator.id', $user->id)
            ->assertJsonPath('data.planning_input.status', 'ready')
            ->assertJsonCount(6, 'data.status_history');

        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken($token)->getJson("/api/buyer-orders/{$orderId}/history")
            ->assertOk()
            ->assertJsonPath('data.status_history.0.new_status', 'draft')
            ->assertJsonFragment(['new_status' => 'pending_approval'])
            ->assertJsonFragment(['new_status' => 'confirmed'])
            ->assertJsonFragment(['new_status' => 'planning']);

        $this->withToken($token)->deleteJson("/api/buyer-orders/{$orderId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('order_planning_inputs', ['buyer_order_id' => $orderId, 'status' => 'ready']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'buyer-orders', 'record_id' => $orderId, 'action' => 'confirmed']);
    }

    public function test_order_rejection_returns_to_draft_and_duplicate_lines_are_blocked(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-buyer-order-rejection', [
            'buyer-order.view',
            'buyer-order.manage',
            'buyer-order.approve',
            'buyer-order.confirm',
        ])->plainTextToken;
        [$buyer, $product, $firstVariant] = $this->orderFixtures();

        $payload = [
            'buyer_id' => $buyer->id,
            'order_date' => '2026-01-02',
            'delivery_date' => '2026-02-20',
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $firstVariant->id, 'quantity' => 1, 'unit_price' => 10],
                ['product_id' => $product->id, 'product_variant_id' => $firstVariant->id, 'quantity' => 2, 'unit_price' => 10],
            ],
        ];

        $this->withToken($token)->postJson('/api/buyer-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        array_pop($payload['items']);
        $orderId = $this->withToken($token)->postJson('/api/buyer-orders', $payload)->assertCreated()->json('data.id');
        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/submit")->assertOk();
        $this->withToken($token)->postJson("/api/buyer-orders/{$orderId}/reject", ['remarks' => 'Please revise quantities.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.latest_approval.status', 'rejected');
    }

    public function test_order_validation_catches_variant_mismatch_and_invalid_dates(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-buyer-order-validation', ['buyer-order.view', 'buyer-order.manage'])->plainTextToken;
        [$buyer, $product, $firstVariant] = $this->orderFixtures();
        $otherProduct = Product::query()->create([
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'code' => 'HOOD-001',
            'name' => 'Test hoodie',
            'product_type' => 'Finished garment',
            'standard_cost' => 8,
            'standard_price' => 20,
            'status' => 'active',
        ]);

        $this->withToken($token)->postJson('/api/buyer-orders', [
            'buyer_id' => $buyer->id,
            'order_date' => '2026-03-01',
            'delivery_date' => '2026-02-01',
            'items' => [[
                'product_id' => $otherProduct->id,
                'product_variant_id' => $firstVariant->id,
                'quantity' => 0,
                'unit_price' => -1,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_date', 'items.0.quantity', 'items.0.unit_price']);

        $this->withToken($token)->postJson('/api/buyer-orders', [
            'buyer_id' => $buyer->id,
            'order_date' => '2026-03-01',
            'delivery_date' => '2026-03-15',
            'items' => [[
                'product_id' => $otherProduct->id,
                'product_variant_id' => $firstVariant->id,
                'quantity' => 1,
                'unit_price' => 10,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_variant_id']);
    }

    public function test_order_permissions_and_query_filters_are_enforced(): void
    {
        $user = $this->administrator();
        [$buyer, $product, $firstVariant] = $this->orderFixtures();
        $manageToken = $user->createToken('test-buyer-order-query', ['buyer-order.view', 'buyer-order.manage', 'master-data.view'])->plainTextToken;
        $viewOnlyToken = $user->createToken('test-buyer-order-view-only', ['buyer-order.view'])->plainTextToken;
        $dashboardOnlyToken = $user->createToken('test-buyer-order-dashboard-only', ['dashboard.view'])->plainTextToken;

        $this->withToken($manageToken)->getJson('/api/master-data/product-variants/options')
            ->assertOk()
            ->assertJsonFragment(['id' => $firstVariant->id, 'product_id' => $product->id]);

        $orderId = $this->withToken($manageToken)->postJson('/api/buyer-orders', [
            'buyer_id' => $buyer->id,
            'order_date' => '2026-01-02',
            'delivery_date' => '2026-02-20',
            'items' => [['product_id' => $product->id, 'product_variant_id' => $firstVariant->id, 'quantity' => 4, 'unit_price' => 10]],
        ])->assertCreated()->json('data.id');

        app('auth')->forgetGuards();
        $this->withToken($dashboardOnlyToken)->getJson('/api/buyer-orders')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewOnlyToken)->getJson('/api/buyer-orders?status=draft&buyer_id='.$buyer->id.'&search=BO-&per_page=1&sort=order_number&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
        $this->withToken($viewOnlyToken)->postJson("/api/buyer-orders/{$orderId}/confirm")->assertForbidden();
    }

    /**
     * @return array{0: Buyer, 1: Product, 2: ProductVariant, 3: ProductVariant}
     */
    private function orderFixtures(): array
    {
        $buyer = Buyer::query()->where('code', 'BUY-001')->firstOrFail();
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

        return [$buyer, $product, $firstVariant, $secondVariant];
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
