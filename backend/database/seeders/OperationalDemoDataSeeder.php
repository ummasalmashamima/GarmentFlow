<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\BomHeader;
use App\Models\BomVersion;
use App\Models\Buyer;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryTrackingHistory;
use App\Models\DemandForecast;
use App\Models\FinishedGoods;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\MaterialRequirement;
use App\Models\MrpRun;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\ProductionPlan;
use App\Models\ProductionProgress;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplyPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;

class OperationalDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@garmentflow.com')->first()
            ?? User::query()->first();

        $buyers = Buyer::all();
        $suppliers = Supplier::all();
        $products = Product::with('variants')->get();
        $materials = Material::all();
        $warehouses = Warehouse::with('locations')->get();
        $units = Unit::all();

        if ($buyers->isEmpty() || $products->isEmpty() || $materials->isEmpty() || $warehouses->isEmpty()) {
            return;
        }

        $whRaw = Warehouse::query()->where('code', 'WH-RAW-01')->first() ?? $warehouses->first();
        $whFG = Warehouse::query()->where('code', 'WH-FG-01')->first() ?? $warehouses->last();
        $locRawA1 = WarehouseLocation::query()->where('warehouse_id', $whRaw->id)->first();
        $locFGBay1 = WarehouseLocation::query()->where('warehouse_id', $whFG->id)->first();

        $buyerHM = Buyer::query()->where('code', 'BUY-HM-001')->first() ?? $buyers->first();
        $buyerZara = Buyer::query()->where('code', 'BUY-ZARA-002')->first() ?? $buyers->skip(1)->first() ?? $buyers->first();
        $buyerTarget = Buyer::query()->where('code', 'BUY-TGT-003')->first() ?? $buyers->first();

        $supPacific = Supplier::query()->where('code', 'SUP-TEX-001')->first() ?? $suppliers->first();
        $supYkk = Supplier::query()->where('code', 'SUP-YKK-002')->first() ?? $suppliers->skip(1)->first() ?? $suppliers->first();

        $prodPolo = Product::query()->where('code', 'POLO-PREM')->first() ?? $products->first();
        $varPoloNavyM = ProductVariant::query()->where('product_id', $prodPolo->id)->first() ?? $prodPolo->variants->first();

        $prodTee = Product::query()->where('code', 'TEE-CLASSIC')->first() ?? $products->skip(1)->first() ?? $prodPolo;
        $varTee = ProductVariant::query()->where('product_id', $prodTee->id)->first();

        $matCotton = Material::query()->where('code', 'FAB-PIQUE-100')->first() ?? $materials->first();
        $matThread = Material::query()->where('code', 'TRM-THREAD-120')->first() ?? $materials->skip(1)->first() ?? $materials->first();
        $matButtons = Material::query()->where('code', 'TRM-BTN-4HOLE')->first() ?? $materials->skip(2)->first() ?? $materials->first();

        $unitPcs = Unit::query()->where('code', 'PCS')->first() ?? $units->first();
        $unitKg = Unit::query()->where('code', 'KG')->first() ?? $units->first();
        $unitCone = Unit::query()->where('code', 'CONE')->first() ?? $units->first();

        // 1. Initial Raw Material Inventory Stock
        $this->seedInitialInventory($whRaw, $locRawA1, $matCotton, $unitKg, 2500.0, $admin);
        $this->seedInitialInventory($whRaw, $locRawA1, $matThread, $unitCone, 400.0, $admin);
        $this->seedInitialInventory($whRaw, $locRawA1, $matButtons, $unitPcs, 15000.0, $admin);

        // 2. Buyer Orders
        $bo1 = BuyerOrder::query()->firstOrCreate(
            ['order_number' => 'BO-2026-HM-001'],
            [
                'buyer_id' => $buyerHM->id,
                'order_date' => '2026-08-01',
                'delivery_date' => '2026-09-15',
                'total_quantity' => 1200,
                'total_amount' => 22200.00,
                'status' => 'confirmed',
                'created_by' => $admin?->id,
                'remarks' => 'Autumn Season Polo Launch for Europe.',
            ]
        );
        BuyerOrderItem::query()->firstOrCreate(
            ['buyer_order_id' => $bo1->id, 'product_id' => $prodPolo->id, 'product_variant_id' => $varPoloNavyM?->id],
            [
                'quantity' => 1200,
                'unit_price' => 18.50,
                'item_total' => 22200.00,
                'remarks' => 'European export spec',
            ]
        );

        $bo2 = BuyerOrder::query()->firstOrCreate(
            ['order_number' => 'BO-2026-ZARA-002'],
            [
                'buyer_id' => $buyerZara->id,
                'order_date' => '2026-08-05',
                'delivery_date' => '2026-09-20',
                'total_quantity' => 800,
                'total_amount' => 11200.00,
                'status' => 'approved',
                'created_by' => $admin?->id,
                'remarks' => 'Fast-track replenishment order.',
            ]
        );
        BuyerOrderItem::query()->firstOrCreate(
            ['buyer_order_id' => $bo2->id, 'product_id' => $prodTee->id, 'product_variant_id' => $varTee?->id],
            [
                'quantity' => 800,
                'unit_price' => 14.00,
                'item_total' => 11200.00,
            ]
        );

        $bo3 = BuyerOrder::query()->firstOrCreate(
            ['order_number' => 'BO-2026-TGT-003'],
            [
                'buyer_id' => $buyerTarget->id,
                'order_date' => '2026-08-10',
                'delivery_date' => '2026-10-01',
                'total_quantity' => 2500,
                'total_amount' => 43000.00,
                'status' => 'draft',
                'created_by' => $admin?->id,
                'remarks' => 'Bulk seasonal order review.',
            ]
        );
        BuyerOrderItem::query()->firstOrCreate(
            ['buyer_order_id' => $bo3->id, 'product_id' => $prodPolo->id, 'product_variant_id' => $varPoloNavyM?->id],
            [
                'quantity' => 2500,
                'unit_price' => 17.20,
                'item_total' => 43000.00,
            ]
        );

        // 3. Demand Forecasts
        DemandForecast::query()->firstOrCreate(
            ['product_id' => $prodPolo->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
            [
                'product_variant_id' => $varPoloNavyM?->id,
                'period_type' => 'monthly',
                'method' => 'historical_average',
                'forecast_quantity' => 1500,
                'forecast_date' => '2026-08-01',
                'confidence_score' => 94.50,
                'status' => 'active',
                'lookback_periods' => 3,
                'created_by' => $admin?->id,
                'notes' => 'Generated from 3-month rolling sales average.',
            ]
        );

        DemandForecast::query()->firstOrCreate(
            ['product_id' => $prodTee->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
            [
                'product_variant_id' => $varTee?->id,
                'period_type' => 'monthly',
                'method' => 'manual',
                'forecast_quantity' => 1000,
                'forecast_date' => '2026-08-01',
                'confidence_score' => 88.00,
                'status' => 'active',
                'lookback_periods' => 3,
                'created_by' => $admin?->id,
                'notes' => 'Manual executive baseline estimate.',
            ]
        );

        // 4. Supply Plans
        $sp1 = SupplyPlan::query()->firstOrCreate(
            ['product_id' => $prodPolo->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
            [
                'product_variant_id' => $varPoloNavyM?->id,
                'period_type' => 'monthly',
                'confirmed_order_quantity' => 1200,
                'forecast_quantity' => 1500,
                'required_quantity' => 1500,
                'available_quantity' => 300,
                'planned_production_quantity' => 1200,
                'status' => 'calculated',
                'created_by' => $admin?->id,
                'notes' => 'Optimized against available stock capacity.',
            ]
        );

        // 5. Material Requirements Planning (MRP)
        $mrp1 = MrpRun::query()->firstOrCreate(
            ['run_number' => 'MRP-2026-08-001'],
            [
                'planning_date' => '2026-08-02',
                'total_gross_quantity' => 336.00,
                'total_net_quantity' => 0.00,
                'inventory_data_available' => true,
                'status' => 'calculated',
                'created_by' => $admin?->id,
                'notes' => 'August Polo execution BOM explosion.',
            ]
        );

        MaterialRequirement::query()->firstOrCreate(
            ['mrp_run_id' => $mrp1->id, 'material_id' => $matCotton->id],
            [
                'unit_id' => $unitKg->id,
                'gross_quantity' => 336.00,
                'available_quantity' => 2500.00,
                'net_quantity' => 0.00,
                'status' => 'covered',
            ]
        );
        MaterialRequirement::query()->firstOrCreate(
            ['mrp_run_id' => $mrp1->id, 'material_id' => $matButtons->id],
            [
                'unit_id' => $unitPcs->id,
                'gross_quantity' => 3600.00,
                'available_quantity' => 15000.00,
                'net_quantity' => 0.00,
                'status' => 'covered',
            ]
        );

        // 6. Procurement: Requisition -> Purchase Order -> Goods Receipt
        $pr1 = PurchaseRequisition::query()->firstOrCreate(
            ['requisition_number' => 'PR-2026-001'],
            [
                'requested_by' => $admin?->id,
                'request_date' => '2026-08-02',
                'required_date' => '2026-08-12',
                'priority' => 'high',
                'source' => 'MRP-Auto',
                'status' => 'converted_to_po',
                'remarks' => 'Additional yarn replenishment for Q3 reserve.',
            ]
        );
        $prItem1 = PurchaseRequisitionItem::query()->firstOrCreate(
            ['purchase_requisition_id' => $pr1->id, 'material_id' => $matCotton->id],
            ['unit_id' => $unitKg->id, 'quantity' => 1000, 'remarks' => '100% Cotton Pique 220 GSM']
        );

        $po1 = PurchaseOrder::query()->firstOrCreate(
            ['purchase_order_number' => 'PO-2026-PAC-001'],
            [
                'supplier_id' => $supPacific->id,
                'po_date' => '2026-08-03',
                'expected_delivery_date' => '2026-08-10',
                'currency' => 'USD',
                'subtotal' => 4500.00,
                'tax_total' => 225.00,
                'discount_total' => 0.00,
                'total_amount' => 4725.00,
                'status' => 'fully_received',
                'created_by' => $admin?->id,
                'remarks' => 'Contract rate applied per Master Agreement.',
            ]
        );
        $poItem1 = PurchaseOrderItem::query()->firstOrCreate(
            ['purchase_order_id' => $po1->id, 'material_id' => $matCotton->id],
            [
                'purchase_requisition_item_id' => $prItem1->id,
                'unit_id' => $unitKg->id,
                'quantity' => 1000,
                'received_quantity' => 1000,
                'unit_price' => 4.50,
                'line_total' => 4500.00,
                'line_number' => 1,
            ]
        );

        $grn1 = GoodsReceipt::query()->firstOrCreate(
            ['receipt_number' => 'GRN-2026-001'],
            [
                'purchase_order_id' => $po1->id,
                'supplier_id' => $supPacific->id,
                'warehouse_id' => $whRaw->id,
                'warehouse_location_id' => $locRawA1?->id,
                'receipt_date' => '2026-08-08',
                'status' => 'posted',
                'received_by' => $admin?->id,
                'posted_at' => '2026-08-08 14:30:00',
                'remarks' => '100% QC Passed. Passed shade & shrinkage test.',
            ]
        );
        GoodsReceiptItem::query()->firstOrCreate(
            ['goods_receipt_id' => $grn1->id, 'purchase_order_item_id' => $poItem1->id],
            [
                'material_id' => $matCotton->id,
                'unit_id' => $unitKg->id,
                'ordered_quantity' => 1000,
                'received_quantity' => 1000,
                'accepted_quantity' => 1000,
                'rejected_quantity' => 0,
                'line_number' => 1,
                'remarks' => 'Batch #COT-26-889',
            ]
        );

        // 7. Production: Plan -> Order -> Consumption -> Progress -> Finished Goods
        $pp1 = ProductionPlan::query()->firstOrCreate(
            ['plan_number' => 'PP-2026-POLO-001'],
            [
                'product_id' => $prodPolo->id,
                'product_variant_id' => $varPoloNavyM?->id,
                'supply_plan_id' => $sp1->id,
                'buyer_order_id' => $bo1->id,
                'planned_quantity' => 1200,
                'planned_start_date' => '2026-08-09',
                'planned_end_date' => '2026-08-20',
                'priority' => 'high',
                'status' => 'completed',
                'created_by' => $admin?->id,
                'remarks' => 'Assigned to Sewing Line 04 and Finishing Line 02.',
            ]
        );

        $bomVer = BomVersion::query()->where('status', 'active')->first();
        $pOrder1 = ProductionOrder::query()->firstOrCreate(
            ['order_number' => 'PROD-ORD-2026-001'],
            [
                'production_plan_id' => $pp1->id,
                'buyer_order_id' => $bo1->id,
                'product_id' => $prodPolo->id,
                'product_variant_id' => $varPoloNavyM?->id,
                'bom_version_id' => $bomVer?->id,
                'issue_warehouse_id' => $whRaw->id,
                'issue_warehouse_location_id' => $locRawA1?->id,
                'planned_quantity' => 1200,
                'completed_quantity' => 1200,
                'rejected_quantity' => 0,
                'start_date' => '2026-08-09',
                'expected_completion_date' => '2026-08-20',
                'completed_date' => '2026-08-18',
                'status' => 'completed',
                'created_by' => $admin?->id,
                'completed_by' => $admin?->id,
                'remarks' => 'Completed ahead of schedule with 0% defect rate.',
            ]
        );

        $pOrderItem1 = ProductionOrderItem::query()->firstOrCreate(
            ['production_order_id' => $pOrder1->id, 'material_id' => $matCotton->id],
            [
                'unit_id' => $unitKg->id,
                'bom_quantity' => 0.28,
                'wastage_percentage' => 4.00,
                'required_quantity' => 336.00,
                'consumed_quantity' => 336.00,
            ]
        );

        MaterialConsumption::query()->firstOrCreate(
            ['consumption_number' => 'MAT-CON-2026-001'],
            [
                'production_order_id' => $pOrder1->id,
                'production_order_item_id' => $pOrderItem1->id,
                'material_id' => $matCotton->id,
                'unit_id' => $unitKg->id,
                'quantity' => 336.00,
                'consumption_date' => '2026-08-11',
                'idempotency_key' => 'mat-con-demo-001',
                'recorded_by' => $admin?->id,
                'remarks' => 'Issued to Cutting Section.',
            ]
        );

        ProductionProgress::query()->firstOrCreate(
            ['production_order_id' => $pOrder1->id, 'production_date' => '2026-08-15'],
            [
                'planned_quantity' => 1200,
                'completed_quantity' => 1200,
                'rejected_quantity' => 0,
                'remaining_quantity' => 0,
                'progress_percentage' => 100.00,
                'recorded_by' => $admin?->id,
                'remarks' => 'All pieces passed 100% final needle detection inspection.',
            ]
        );

        FinishedGoods::query()->firstOrCreate(
            ['finished_goods_number' => 'FG-2026-001'],
            [
                'production_order_id' => $pOrder1->id,
                'product_id' => $prodPolo->id,
                'product_variant_id' => $varPoloNavyM?->id,
                'warehouse_id' => $whFG->id,
                'warehouse_location_id' => $locFGBay1?->id,
                'unit_id' => $unitPcs->id,
                'quantity' => 1200,
                'finished_date' => '2026-08-18',
                'recorded_by' => $admin?->id,
                'remarks' => 'Transferred directly to Export Finished Goods Warehouse.',
            ]
        );

        // Seed Finished Goods Inventory
        $this->seedFinishedGoodsInventory($whFG, $locFGBay1, $prodPolo, $varPoloNavyM, $unitPcs, 1200.0, $admin);

        // 8. Sales Orders & Reservations
        $customer = Customer::query()->firstOrCreate(
            ['code' => 'CUST-HM-EU'],
            ['name' => 'H&M European Logistics Hub', 'email' => 'logistics@hm.example', 'status' => 'active']
        );

        $so1 = SalesOrder::query()->firstOrCreate(
            ['sales_order_number' => 'SO-2026-HM-001'],
            [
                'buyer_id' => $buyerHM->id,
                'customer_id' => $customer->id,
                'warehouse_id' => $whFG->id,
                'order_date' => '2026-08-19',
                'required_delivery_date' => '2026-08-28',
                'delivery_address' => 'H&M Distribution Center, Hamburg Port Logistics, Germany',
                'contact_information' => 'shipment@hm.example',
                'ordered_quantity' => 1200,
                'confirmed_quantity' => 1200,
                'delivered_quantity' => 1200,
                'remaining_quantity' => 0,
                'subtotal' => 22200.00,
                'total_amount' => 22200.00,
                'status' => 'confirmed',
                'created_by' => $admin?->id,
                'remarks' => 'Priority ocean shipment booking.',
            ]
        );
        $soItem1 = SalesOrderItem::query()->firstOrCreate(
            ['sales_order_id' => $so1->id, 'product_id' => $prodPolo->id, 'product_variant_id' => $varPoloNavyM?->id],
            [
                'unit_id' => $unitPcs->id,
                'ordered_quantity' => 1200,
                'delivered_quantity' => 1200,
                'unit_price' => 18.50,
                'line_total' => 22200.00,
            ]
        );

        // 9. Deliveries & Shipments
        $del1 = Delivery::query()->firstOrCreate(
            ['delivery_number' => 'DEL-2026-001'],
            [
                'sales_order_id' => $so1->id,
                'warehouse_id' => $whFG->id,
                'delivery_date' => '2026-08-22',
                'expected_delivery_date' => '2026-08-27',
                'dispatched_at' => '2026-08-22 10:00:00',
                'delivered_at' => '2026-08-26 14:00:00',
                'carrier_name' => 'Maersk Line Intermodal',
                'tracking_number' => 'MSK-OCN-9481023',
                'ordered_quantity' => 1200,
                'dispatched_quantity' => 1200,
                'delivered_quantity' => 1200,
                'remaining_quantity' => 0,
                'status' => 'delivered',
                'created_by' => $admin?->id,
                'remarks' => 'Container #MSKU-8821940 sealed and released for export.',
            ]
        );
        DeliveryItem::query()->firstOrCreate(
            ['delivery_id' => $del1->id, 'sales_order_item_id' => $soItem1->id],
            [
                'line_number' => 1,
                'product_id' => $prodPolo->id,
                'product_variant_id' => $varPoloNavyM?->id,
                'unit_id' => $unitPcs->id,
                'delivery_quantity' => 1200,
                'dispatched_quantity' => 1200,
                'delivered_quantity' => 1200,
                'remaining_quantity' => 0,
                'remarks' => 'Full fulfillment.',
            ]
        );

        DeliveryTrackingHistory::query()->firstOrCreate(
            ['delivery_id' => $del1->id, 'new_status' => 'dispatched'],
            [
                'previous_status' => 'ready_for_dispatch',
                'carrier_name' => 'Maersk Line Intermodal',
                'location' => 'Chittagong Port Terminal',
                'tracking_number' => 'MSK-OCN-9481023',
                'remarks' => 'Loaded aboard vessel MSC Gülsün.',
                'changed_by' => $admin?->id,
            ]
        );
        DeliveryTrackingHistory::query()->firstOrCreate(
            ['delivery_id' => $del1->id, 'new_status' => 'delivered'],
            [
                'previous_status' => 'dispatched',
                'carrier_name' => 'Maersk Line Intermodal',
                'location' => 'Port of Hamburg Terminal Burchardkai',
                'tracking_number' => 'MSK-OCN-9481023',
                'remarks' => 'Customs cleared and delivered to H&M Logistics Hub.',
                'changed_by' => $admin?->id,
            ]
        );

        // 10. Financial Invoices & Payments
        $inv1 = Invoice::query()->firstOrCreate(
            ['invoice_number' => 'INV-2026-001'],
            [
                'sales_order_id' => $so1->id,
                'buyer_id' => $buyerHM->id,
                'customer_id' => $customer->id,
                'warehouse_id' => $whFG->id,
                'invoice_date' => '2026-08-23',
                'due_date' => '2026-09-22',
                'subtotal' => 22200.00,
                'tax_amount' => 0.00,
                'discount_amount' => 0.00,
                'total_amount' => 22200.00,
                'paid_amount' => 22200.00,
                'due_amount' => 0.00,
                'status' => 'paid',
                'created_by' => $admin?->id,
                'remarks' => 'Commercial export invoice approved by Buyer Sourcing.',
            ]
        );
        InvoiceItem::query()->firstOrCreate(
            ['invoice_id' => $inv1->id, 'sales_order_item_id' => $soItem1->id],
            [
                'line_number' => 1,
                'product_id' => $prodPolo->id,
                'product_variant_id' => $varPoloNavyM?->id,
                'unit_id' => $unitPcs->id,
                'quantity' => 1200,
                'unit_price' => 18.50,
                'line_total' => 22200.00,
            ]
        );

        Payment::query()->firstOrCreate(
            ['payment_number' => 'PAY-2026-001'],
            [
                'invoice_id' => $inv1->id,
                'buyer_id' => $buyerHM->id,
                'customer_id' => $customer->id,
                'received_by' => $admin?->id,
                'amount' => 22200.00,
                'payment_date' => '2026-08-25',
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TT-SEB-9988231',
                'status' => 'received',
                'remarks' => 'Settled in full via SEB Stockholm SWIFT TT transfer.',
            ]
        );

        // 11. System Alerts & Notifications
        $this->seedAlert('STOCK_SHORTAGE', 'Critical Raw Material Alert', 'Low stock warning for 100% Combed Cotton Pique in WH-RAW-01.', 'warning', $admin);
        $this->seedAlert('ORDER_CONFIRMED', 'Buyer Order Confirmed', 'H&M Autumn Polo Launch order (1,200 pcs) successfully locked for production.', 'info', $admin);
        $this->seedAlert('PAYMENT_RECEIVED', 'Full Invoice Payment Received', 'Received $22,200.00 from H&M Hennes & Mauritz AB for Invoice #INV-2026-001.', 'success', $admin);
        $this->seedAlert('DELIVERY_DISPATCHED', 'Export Shipment Dispatched', 'Maersk Line Ocean vessel departed for Hamburg with Delivery #DEL-2026-001.', 'info', $admin);
    }

    private function seedInitialInventory(Warehouse $wh, ?WarehouseLocation $loc, Material $mat, Unit $unit, float $qty, ?User $admin): void
    {
        $locId = $loc ? $loc->id : 'TOTAL';
        $stockKey = "WH-{$wh->id}-LOC-{$locId}-MAT-{$mat->id}-U-{$unit->id}";
        $balance = InventoryBalance::query()->firstOrCreate(
            ['stock_key' => $stockKey],
            [
                'warehouse_id' => $wh->id,
                'warehouse_location_id' => $loc?->id,
                'item_type' => 'material',
                'material_id' => $mat->id,
                'unit_id' => $unit->id,
                'quantity_on_hand' => $qty,
                'quantity_reserved' => 0,
                'status' => 'active',
            ]
        );

        InventoryTransaction::query()->firstOrCreate(
            ['transaction_number' => 'TX-INIT-' . $mat->code],
            [
                'inventory_balance_id' => $balance->id,
                'transaction_type' => 'STOCK_IN',
                'warehouse_id' => $wh->id,
                'warehouse_location_id' => $loc?->id,
                'material_id' => $mat->id,
                'unit_id' => $unit->id,
                'quantity' => $qty,
                'transaction_date' => '2026-08-01 08:00:00',
                'performed_by' => $admin?->id,
                'remarks' => 'Opening audited warehouse balance.',
            ]
        );
    }

    private function seedFinishedGoodsInventory(Warehouse $wh, ?WarehouseLocation $loc, Product $prod, ?ProductVariant $variant, Unit $unit, float $qty, ?User $admin): void
    {
        $locId = $loc ? $loc->id : 'TOTAL';
        $itemType = $variant ? 'product_variant' : 'product';
        $itemId = $variant ? $variant->id : $prod->id;
        $stockKey = "WH-{$wh->id}-LOC-{$locId}-{$itemType}-{$itemId}-U-{$unit->id}";
        $balance = InventoryBalance::query()->firstOrCreate(
            ['stock_key' => $stockKey],
            [
                'warehouse_id' => $wh->id,
                'warehouse_location_id' => $loc?->id,
                'item_type' => $itemType,
                'product_id' => $variant ? null : $prod->id,
                'product_variant_id' => $variant?->id,
                'unit_id' => $unit->id,
                'quantity_on_hand' => $qty,
                'quantity_reserved' => 0,
                'status' => 'active',
            ]
        );

        InventoryTransaction::query()->firstOrCreate(
            ['transaction_number' => 'TX-FG-INIT-' . $prod->code],
            [
                'inventory_balance_id' => $balance->id,
                'transaction_type' => 'STOCK_IN',
                'warehouse_id' => $wh->id,
                'warehouse_location_id' => $loc?->id,
                'product_id' => $variant ? null : $prod->id,
                'product_variant_id' => $variant?->id,
                'unit_id' => $unit->id,
                'quantity' => $qty,
                'transaction_date' => '2026-08-18 17:00:00',
                'performed_by' => $admin?->id,
                'remarks' => 'Finished goods posted from Production Order #PROD-ORD-2026-001.',
            ]
        );
    }

    private function seedAlert(string $code, string $title, string $description, string $severity, ?User $admin): void
    {
        Alert::query()->firstOrCreate(
            ['alert_key' => 'ALERT-' . $code],
            [
                'rule_code' => $code,
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'role_slug' => 'administrator',
                'occurred_at' => now(),
            ]
        );
    }
}
