<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Buyer;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\SupplyPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase12ReportsDashboardAlertsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_filters_and_csv_export_use_the_selected_filters(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['reports.view', 'reports.export']);
        [$buyer, $product, $variant, $unit, $warehouse] = $this->fixtures();
        $this->salesOrder($user, $buyer, $product, $variant, $unit, $warehouse, 'SO-P12-ONE', '2026-08-01', 'confirmed', 10, 100);
        $this->salesOrder($user, $buyer, $product, $variant, $unit, $warehouse, 'SO-P12-TWO', '2026-08-02', 'draft', 5, 50);

        $this->withToken($token)->getJson('/api/reports/sales?status=confirmed&search=SO-P12-ONE&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.report', 'sales')
            ->assertJsonPath('data.summary.order_count', 1)
            ->assertJsonPath('data.rows.total', 1)
            ->assertJsonPath('data.rows.data.0.sales_order_number', 'SO-P12-ONE');

        app('auth')->forgetGuards();
        $csv = $this->withToken($token)->get('/api/reports/sales/export?status=confirmed');
        $csv->assertOk();
        $this->assertStringContainsString('SO-P12-ONE', $csv->streamedContent());
        $this->assertStringNotContainsString('SO-P12-TWO', $csv->streamedContent());
    }

    public function test_supplier_performance_does_not_multiply_ordered_quantity_for_multiple_receipts(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['reports.view']);
        [, , , $unit, $warehouse] = $this->fixtures();
        $supplier = Supplier::query()->firstOrFail();
        $material = Material::query()->firstOrFail();
        $order = PurchaseOrder::query()->create([
            'purchase_order_number' => 'PO-P12-MULTI-RECEIPT',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-01',
            'expected_delivery_date' => '2026-08-31',
            'currency' => 'USD',
            'subtotal' => 100,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 100,
            'status' => 'sent_to_supplier',
            'created_by' => $user->id,
        ]);
        $orderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $order->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 10,
            'unit_price' => 10,
            'line_total' => 100,
            'received_quantity' => 10,
            'line_number' => 1,
        ]);
        foreach ([
            ['number' => 'GRN-P12-MULTI-1', 'received' => 5, 'accepted' => 4, 'rejected' => 1],
            ['number' => 'GRN-P12-MULTI-2', 'received' => 5, 'accepted' => 5, 'rejected' => 0],
        ] as $index => $receiptData) {
            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $receiptData['number'],
                'purchase_order_id' => $order->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'receipt_date' => '2026-08-10',
                'received_by' => $user->id,
                'status' => 'posted',
                'posted_at' => '2026-08-10 10:00:00',
            ]);
            GoodsReceiptItem::query()->create([
                'goods_receipt_id' => $receipt->id,
                'purchase_order_item_id' => $orderItem->id,
                'material_id' => $material->id,
                'unit_id' => $unit->id,
                'ordered_quantity' => 10,
                'received_quantity' => $receiptData['received'],
                'accepted_quantity' => $receiptData['accepted'],
                'rejected_quantity' => $receiptData['rejected'],
                'line_number' => $index + 1,
            ]);
        }

        $this->withToken($token)->getJson('/api/reports/supplier-performance?supplier_id='.$supplier->id)
            ->assertOk()
            ->assertJsonPath('data.summary.supplier_count', 1)
            ->assertJsonPath('data.summary.ordered_quantity', 10)
            ->assertJsonPath('data.summary.received_quantity', 10)
            ->assertJsonPath('data.summary.accepted_quantity', 9)
            ->assertJsonPath('data.summary.rejected_quantity', 1)
            ->assertJsonPath('data.rows.data.0.ordered_quantity', 10)
            ->assertJsonPath('data.rows.data.0.rejection_rate', 10);
    }

    public function test_dashboards_return_real_kpis_and_shortfall_alerts_are_idempotent_and_per_user(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['dashboard.view', 'dashboard.supply-chain.view', 'alerts.view', 'alerts.manage']);
        [$buyer, $product, $variant, $unit, $warehouse] = $this->fixtures();
        SupplyPlan::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'confirmed_order_quantity' => 20,
            'forecast_quantity' => 0,
            'required_quantity' => 20,
            'available_quantity' => 3,
            'planned_production_quantity' => 0,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->withToken($token)->getJson('/api/dashboards/supply-chain')
            ->assertOk()
            ->assertJsonPath('data.dashboard', 'supply-chain')
            ->assertJsonStructure(['data' => ['kpis', 'series', 'tables', 'insights']]);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/alerts/refresh')->assertOk();
        $first = $this->withToken($token)->getJson('/api/alerts?rule_code=demand_without_available_stock')->assertOk();
        $first->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.is_read', false);
        $alertId = $first->json('data.data.0.id');
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/alerts/refresh')->assertOk();
        $this->assertSame(1, Alert::query()->where('rule_code', 'demand_without_available_stock')->count());
        app('auth')->forgetGuards();
        $this->withToken($token)->putJson('/api/alerts/'.$alertId.'/state', ['read' => true])->assertOk()->assertJsonPath('data.is_read', true);
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/alerts?rule_code=demand_without_available_stock&read=1')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_all_report_and_dashboard_contracts_are_available(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['reports.view', 'dashboard.view', 'dashboard.executive.view', 'dashboard.supply-chain.view', 'dashboard.production.view', 'dashboard.procurement.view', 'dashboard.warehouse.view']);
        foreach (['sales', 'purchase', 'stock', 'profit', 'production', 'payment', 'delivery', 'inventory-movement', 'supplier-performance', 'buyer-customer'] as $report) {
            app('auth')->forgetGuards();
            $this->withToken($token)->getJson('/api/reports/'.$report)->assertOk()->assertJsonPath('data.report', $report);
        }
        foreach (['executive', 'supply-chain', 'production', 'procurement', 'warehouse'] as $dashboard) {
            app('auth')->forgetGuards();
            $this->withToken($token)->getJson('/api/dashboards/'.$dashboard)->assertOk()->assertJsonPath('data.dashboard', $dashboard);
        }
    }

    public function test_phase12_endpoints_require_their_specific_permissions(): void
    {
        $user = $this->administrator();
        $dashboardOnly = $this->token($user, ['dashboard.view']);
        $this->withToken($dashboardOnly)->getJson('/api/dashboards/executive')->assertForbidden();
        app('auth')->forgetGuards();
        $reportOnly = $this->token($user, ['reports.view']);
        $this->withToken($reportOnly)->getJson('/api/alerts')->assertForbidden();
        app('auth')->forgetGuards();
        $alertsOnly = $this->token($user, ['alerts.view']);
        $this->withToken($alertsOnly)->postJson('/api/alerts/refresh')->assertForbidden();
    }

    /** @return array{0: Buyer, 1: Product, 2: ProductVariant, 3: Unit, 4: Warehouse} */
    private function fixtures(): array
    {
        return [
            Buyer::query()->where('code', 'BUY-001')->firstOrFail(),
            Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail(),
            ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail(),
            Unit::query()->where('code', 'PCS')->firstOrFail(),
            Warehouse::query()->where('code', 'DHK-01')->firstOrFail(),
        ];
    }

    private function salesOrder(User $user, Buyer $buyer, Product $product, ProductVariant $variant, Unit $unit, Warehouse $warehouse, string $number, string $date, string $status, int $quantity, float $total): SalesOrder
    {
        $order = SalesOrder::query()->create([
            'sales_order_number' => $number,
            'buyer_id' => $buyer->id,
            'order_date' => $date,
            'required_delivery_date' => '2026-09-01',
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'subtotal' => $total,
            'total_amount' => $total,
            'ordered_quantity' => $quantity,
            'confirmed_quantity' => $status === 'confirmed' ? $quantity : 0,
            'delivered_quantity' => 0,
            'remaining_quantity' => $status === 'confirmed' ? $quantity : 0,
            'confirmed_at' => $status === 'confirmed' ? '2026-08-01 09:00:00' : null,
            'created_by' => $user->id,
        ]);
        SalesOrderItem::query()->create([
            'sales_order_id' => $order->id,
            'line_number' => 1,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'unit_id' => $unit->id,
            'ordered_quantity' => $quantity,
            'confirmed_quantity' => $status === 'confirmed' ? $quantity : 0,
            'delivered_quantity' => 0,
            'remaining_quantity' => $status === 'confirmed' ? $quantity : 0,
            'unit_price' => $quantity > 0 ? $total / $quantity : 0,
            'line_total' => $total,
        ]);

        return $order;
    }

    private function token(User $user, array $abilities): string
    {
        return $user->createToken('phase12-'.uniqid('', true), $abilities)->plainTextToken;
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
