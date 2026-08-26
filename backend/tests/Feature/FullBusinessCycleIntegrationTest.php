<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Delivery\DeliveryWorkflow;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\BOMSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullBusinessCycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_garments_supply_chain_business_cycle_steps_1_to_11(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(BOMSeeder::class);

        $admin = User::query()->where('email', 'admin@garmentflow.com')->firstOrFail();
        $token = $admin->createToken('full-cycle-token', ['*'])->plainTextToken;

        $buyer = Buyer::query()->firstOrFail();
        $customer = Customer::query()->firstOrFail();
        $supplier = Supplier::query()->firstOrFail();
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $warehouse = Warehouse::query()->firstOrFail();
        $location = WarehouseLocation::query()->where('warehouse_id', $warehouse->id)->firstOrFail();
        $material = Material::query()->where('code', 'FAB-COT-001')->firstOrFail();
        $unit = Unit::query()->where('code', 'KG')->firstOrFail();
        $unitPcs = Unit::query()->where('code', 'PCS')->firstOrFail();

        // ==========================================
        // STEP 1: Buyer Order Creation & Confirmation
        // ==========================================
        $orderRes = $this->withToken($token)->postJson('/api/buyer-orders', [
            'buyer_id' => $buyer->id,
            'order_number' => 'BO-FULL-2026-001',
            'order_date' => '2026-08-01',
            'delivery_date' => '2026-09-01',
            'currency' => 'USD',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $unitPcs->id,
                    'quantity' => 100,
                    'unit_price' => 15.00,
                ],
            ],
        ])->assertCreated();

        $buyerOrderId = $orderRes->json('data.id');
        $this->withToken($token)->postJson("/api/buyer-orders/{$buyerOrderId}/submit")->assertOk();
        $this->withToken($token)->postJson("/api/buyer-orders/{$buyerOrderId}/approve")->assertOk();
        $this->withToken($token)->postJson("/api/buyer-orders/{$buyerOrderId}/confirm")->assertOk();

        // ==========================================
        // STEP 2: Demand Forecast Generation
        // ==========================================
        $forecastRes = $this->withToken($token)->postJson('/api/planning/forecasts', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'method' => 'manual',
            'forecast_quantity' => 100,
            'forecast_date' => '2026-08-01',
        ])->assertCreated();

        $forecastId = $forecastRes->json('data.id');
        $this->withToken($token)->postJson("/api/planning/forecasts/{$forecastId}/activate")->assertOk();

        // ==========================================
        // STEP 3: Supply Planning
        // ==========================================
        $supplyPlanRes = $this->withToken($token)->postJson('/api/planning/supply-plans/generate', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'available_quantity' => 0,
        ])->assertOk();

        $supplyPlanId = $supplyPlanRes->json('data.0.id');

        // ==========================================
        // STEP 4 & 5: Material Requirement (MRP) & Inventory Check
        // ==========================================
        $mrpRes = $this->withToken($token)->postJson('/api/planning/material-requirements/generate', [
            'supply_plan_ids' => [$supplyPlanId],
            'planning_date' => '2026-08-01',
            'availability' => [
                [
                    'material_id' => $material->id,
                    'unit_id' => $unit->id,
                    'available_quantity' => 0,
                    'allocated_quantity' => 0,
                ],
            ],
        ])->assertCreated();

        $grossRequirement = $mrpRes->json('data.total_gross_quantity');
        $netRequirement = $mrpRes->json('data.total_net_quantity');
        $this->assertGreaterThan(0, (float) $grossRequirement);

        // ==========================================
        // STEP 6: Procurement & Purchase Order & Goods Receipt
        // ==========================================
        $reqRes = $this->withToken($token)->postJson('/api/procurement/requisitions', [
            'request_date' => '2026-08-02',
            'required_date' => '2026-08-10',
            'priority' => 'high',
            'items' => [
                [
                    'material_id' => $material->id,
                    'unit_id' => $unit->id,
                    'quantity' => 200,
                    'remarks' => 'Fabric for August production',
                ],
            ],
        ])->assertCreated();

        $reqId = $reqRes->json('data.id');
        $this->withToken($token)->postJson("/api/procurement/requisitions/{$reqId}/submit", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/requisitions/{$reqId}/approve", [])->assertOk();

        $poRes = $this->withToken($token)->postJson('/api/procurement/purchase-orders', [
            'purchase_requisition_id' => $reqId,
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-03',
            'expected_delivery_date' => '2026-08-10',
            'currency' => 'USD',
            'items' => [
                [
                    'purchase_requisition_item_id' => $reqRes->json('data.items.0.id'),
                    'material_id' => $material->id,
                    'unit_id' => $unit->id,
                    'quantity' => 200,
                    'unit_price' => 4.00,
                ],
            ],
        ])->assertCreated();

        $poId = $poRes->json('data.id');
        $poItemId = $poRes->json('data.items.0.id');
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$poId}/submit", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$poId}/approve", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/purchase-orders/{$poId}/send", [])->assertOk();

        // Goods Receipt & Inspection
        $grnRes = $this->withToken($token)->postJson('/api/procurement/goods-receipts', [
            'purchase_order_id' => $poId,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => null,
            'receipt_date' => '2026-08-05',
            'items' => [
                [
                    'purchase_order_item_id' => $poItemId,
                    'received_quantity' => 200,
                    'accepted_quantity' => 200,
                    'rejected_quantity' => 0,
                ],
            ],
        ])->assertCreated();

        $grnId = $grnRes->json('data.id');
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$grnId}/receive", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$grnId}/accept", [])->assertOk();
        $this->withToken($token)->postJson("/api/procurement/goods-receipts/{$grnId}/post", [])->assertOk();

        // ==========================================
        // STEP 7: Warehouse Stock Inward Verification
        // ==========================================
        $balanceRes = $this->withToken($token)->getJson("/api/inventory?warehouse_id={$warehouse->id}&material_id={$material->id}")
            ->assertOk();
        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => null,
            'material_id' => $material->id,
            'quantity_on_hand' => '200.0000',
        ]);

        // ==========================================
        // STEP 8: Production Execution, Consumption & Finished Goods
        // ==========================================
        $planRes = $this->withToken($token)->postJson('/api/production/plans', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'supply_plan_id' => $supplyPlanId,
            'planned_quantity' => 100,
            'planned_start_date' => '2026-08-06',
            'planned_end_date' => '2026-08-15',
            'priority' => 'high',
        ])->assertCreated();

        $prodPlanId = $planRes->json('data.id');
        $this->withToken($token)->postJson("/api/production/plans/{$prodPlanId}/approve", [])->assertOk();

        $prodOrderRes = $this->withToken($token)->postJson('/api/production/orders', [
            'production_plan_id' => $prodPlanId,
            'expected_completion_date' => '2026-08-15',
            'issue_warehouse_id' => $warehouse->id,
            'issue_warehouse_location_id' => null,
        ])->assertCreated();

        $prodOrderId = $prodOrderRes->json('data.id');
        $prodOrderItemId = $prodOrderRes->json('data.items.0.id');
        $this->withToken($token)->postJson("/api/production/orders/{$prodOrderId}/start", [])->assertOk();

        // Material Consumption
        $this->withToken($token)->postJson("/api/production/orders/{$prodOrderId}/consume", [
            'production_order_item_id' => $prodOrderItemId,
            'quantity' => 157.5,
            'consumption_date' => '2026-08-08',
            'idempotency_key' => 'prod-cycle-consume-1',
        ])->assertCreated();

        // Production Progress Tracking
        $this->withToken($token)->postJson("/api/production/orders/{$prodOrderId}/progress", [
            'completed_quantity' => 100,
            'rejected_quantity' => 0,
            'production_date' => '2026-08-12',
        ])->assertCreated();

        // Complete Order & Post Finished Goods to Warehouse
        $this->withToken($token)->postJson("/api/production/orders/{$prodOrderId}/complete", [
            'finished_quantity' => 100,
            'completed_quantity' => 100,
            'rejected_quantity' => 0,
            'finished_date' => '2026-08-14',
        ])->assertOk();

        // ==========================================
        // STEP 9: Sales Order Creation & Confirmation
        // ==========================================
        $salesOrderRes = $this->withToken($token)->postJson('/api/sales/orders', [
            'buyer_id' => $buyer->id,
            'customer_id' => null,
            'order_date' => '2026-08-15',
            'required_delivery_date' => '2026-08-25',
            'warehouse_id' => $warehouse->id,
            'delivery_address' => 'Port of Rotterdam Terminal 4',
            'contact_information' => 'sales@example.com',
            'order_discount_amount' => 0,
            'order_tax_amount' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $unitPcs->id,
                    'ordered_quantity' => 100,
                    'unit_price' => 25.00,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ],
            ],
        ])->assertCreated();

        $salesOrderId = $salesOrderRes->json('data.id');
        $salesOrderItemId = $salesOrderRes->json('data.items.0.id');
        $this->withToken($token)->postJson("/api/sales/orders/{$salesOrderId}/submit", [])->assertOk();
        $this->withToken($token)->postJson("/api/sales/orders/{$salesOrderId}/confirm", [])->assertOk();

        // ==========================================
        // STEP 10: Delivery Creation, Dispatch, Tracking & Completion
        // ==========================================
        $deliveryRes = $this->withToken($token)->postJson('/api/deliveries', [
            'sales_order_id' => $salesOrderId,
            'delivery_date' => '2026-08-18',
            'expected_delivery_date' => '2026-08-22',
            'carrier_name' => 'DHL Global Forwarding',
            'tracking_number' => 'DHL-TRACK-9988',
            'remarks' => 'Full shipment dispatch.',
            'items' => [
                [
                    'sales_order_item_id' => $salesOrderItemId,
                    'delivery_quantity' => 100,
                ],
            ],
        ])->assertCreated();

        $deliveryId = $deliveryRes->json('data.id');
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::READY_FOR_SHIPMENT])->assertOk();
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/dispatch", ['remarks' => 'Dispatched from warehouse.'])->assertOk();
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::IN_TRANSIT])->assertOk();
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::OUT_FOR_DELIVERY])->assertOk();
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/status", ['status' => DeliveryWorkflow::DELIVERED])->assertOk();
        $this->withToken($token)->postJson("/api/deliveries/{$deliveryId}/complete", ['remarks' => 'Delivery complete.'])->assertOk();

        // Verify terminal delivery state
        $this->assertDatabaseHas('deliveries', [
            'id' => $deliveryId,
            'status' => DeliveryWorkflow::COMPLETED,
        ]);

        // ==========================================
        // STEP 11: Invoice Generation & Payment Recording
        // ==========================================
        $invoiceRes = $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $salesOrderId,
            'invoice_date' => '2026-08-16',
            'due_date' => '2026-09-16',
            'remarks' => 'Full cycle payment invoice.',
        ])->assertCreated();

        $invoiceId = $invoiceRes->json('data.id');
        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/issue", [])->assertOk();

        // Record Payment
        $this->withToken($token)->postJson('/api/finance/payments', [
            'invoice_id' => $invoiceId,
            'payment_date' => '2026-08-17',
            'amount' => 2500.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-88776655',
            'idempotency_key' => 'full-cycle-payment-001',
            'remarks' => 'Full payment received.',
        ])->assertCreated();

        // ==========================================
        // Verification of Analytics & Reports
        // ==========================================
        $this->withToken($token)->getJson('/api/reports/sales?per_page=15')
            ->assertOk()
            ->assertJsonPath('data.rows.total', 1);

        $this->withToken($token)->getJson('/api/reports/profit?per_page=15')
            ->assertOk();

        $this->withToken($token)->getJson('/api/dashboards/executive')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'series',
                ],
            ]);
    }
}
