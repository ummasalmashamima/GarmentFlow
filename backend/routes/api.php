<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BOM\BOMCalculationController;
use App\Http\Controllers\BOM\BOMController;
use App\Http\Controllers\BOM\BOMItemController;
use App\Http\Controllers\BOM\BOMVersionController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Delivery\DeliveryController;
use App\Http\Controllers\Delivery\DeliveryHistoryController;
use App\Http\Controllers\Finance\FinanceHistoryController;
use App\Http\Controllers\Finance\FinanceSummaryController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\MasterData\MasterDataController;
use App\Http\Controllers\Orders\BuyerOrderController;
use App\Http\Controllers\Orders\BuyerOrderItemController;
use App\Http\Controllers\Planning\DemandForecastController;
use App\Http\Controllers\Planning\MaterialRequirementController;
use App\Http\Controllers\Planning\SupplyPlanController;
use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\ProcurementHistoryController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequisitionController;
use App\Http\Controllers\Production\FinishedGoodsController;
use App\Http\Controllers\Production\MaterialConsumptionController;
use App\Http\Controllers\Production\ProductionHistoryController;
use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\ProductionPlanController;
use App\Http\Controllers\Production\ProductionProgressController;
use App\Http\Controllers\Reporting\AlertController;
use App\Http\Controllers\Reporting\ReportsController;
use App\Http\Controllers\Sales\SalesHistoryController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Services\MasterData\MasterDataRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json([
    'status' => 'ok',
    'service' => 'garmentflow-api',
]));

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/access-check', static fn () => response()->json([
            'authorized' => true,
        ]))->middleware('permission:dashboard.view');
    });
});

Route::prefix('boms')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/', [BOMController::class, 'index'])->middleware('permission:bom.view');
    Route::post('/', [BOMController::class, 'store'])->middleware('permission:bom.manage');
    Route::get('/{bom}', [BOMController::class, 'show'])
        ->middleware('permission:bom.view')
        ->whereNumber('bom');
    Route::put('/{bom}', [BOMController::class, 'update'])
        ->middleware('permission:bom.manage')
        ->whereNumber('bom');
    Route::delete('/{bom}', [BOMController::class, 'destroy'])
        ->middleware('permission:bom.manage')
        ->whereNumber('bom');
    Route::post('/{bom}/activate', [BOMController::class, 'activate'])
        ->middleware('permission:bom.manage')
        ->whereNumber('bom');
    Route::post('/{bom}/deactivate', [BOMController::class, 'deactivate'])
        ->middleware('permission:bom.manage')
        ->whereNumber('bom');

    Route::get('/{bom}/versions', [BOMVersionController::class, 'index'])
        ->middleware('permission:bom.view')
        ->whereNumber('bom');
    Route::post('/{bom}/versions', [BOMVersionController::class, 'store'])
        ->middleware('permission:bom.manage')
        ->whereNumber('bom');
    Route::get('/{bom}/versions/{version}', [BOMVersionController::class, 'show'])
        ->middleware('permission:bom.view')
        ->whereNumber(['bom', 'version']);
    Route::put('/{bom}/versions/{version}', [BOMVersionController::class, 'update'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version']);
    Route::post('/{bom}/versions/{version}/activate', [BOMVersionController::class, 'activate'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version']);
    Route::post('/{bom}/versions/{version}/deactivate', [BOMVersionController::class, 'deactivate'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version']);

    Route::get('/{bom}/versions/{version}/items', [BOMItemController::class, 'index'])
        ->middleware('permission:bom.view')
        ->whereNumber(['bom', 'version']);
    Route::post('/{bom}/versions/{version}/items', [BOMItemController::class, 'store'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version']);
    Route::put('/{bom}/versions/{version}/items/{item}', [BOMItemController::class, 'update'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version', 'item']);
    Route::delete('/{bom}/versions/{version}/items/{item}', [BOMItemController::class, 'destroy'])
        ->middleware('permission:bom.manage')
        ->whereNumber(['bom', 'version', 'item']);
    Route::post('/{bom}/versions/{version}/calculate', [BOMCalculationController::class, 'calculate'])
        ->middleware('permission:bom.view')
        ->whereNumber(['bom', 'version']);
});

Route::prefix('buyer-orders')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/', [BuyerOrderController::class, 'index'])->middleware('permission:buyer-order.view');
    Route::post('/preview', [BuyerOrderController::class, 'preview'])->middleware('permission:buyer-order.view');
    Route::post('/', [BuyerOrderController::class, 'store'])->middleware('permission:buyer-order.manage');
    Route::get('/{order}', [BuyerOrderController::class, 'show'])
        ->middleware('permission:buyer-order.view')
        ->whereNumber('order');
    Route::put('/{order}', [BuyerOrderController::class, 'update'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber('order');
    Route::delete('/{order}', [BuyerOrderController::class, 'destroy'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber('order');
    Route::post('/{order}/submit', [BuyerOrderController::class, 'submit'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber('order');
    Route::post('/{order}/approve', [BuyerOrderController::class, 'approve'])
        ->middleware('permission:buyer-order.approve')
        ->whereNumber('order');
    Route::post('/{order}/reject', [BuyerOrderController::class, 'reject'])
        ->middleware('permission:buyer-order.approve')
        ->whereNumber('order');
    Route::post('/{order}/confirm', [BuyerOrderController::class, 'confirm'])
        ->middleware('permission:buyer-order.confirm')
        ->whereNumber('order');
    Route::post('/{order}/status', [BuyerOrderController::class, 'transition'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber('order');
    Route::get('/{order}/history', [BuyerOrderController::class, 'history'])
        ->middleware('permission:buyer-order.view')
        ->whereNumber('order');
    Route::get('/{order}/items', [BuyerOrderItemController::class, 'index'])
        ->middleware('permission:buyer-order.view')
        ->whereNumber('order');
    Route::post('/{order}/items', [BuyerOrderItemController::class, 'store'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber('order');
    Route::put('/{order}/items/{item}', [BuyerOrderItemController::class, 'update'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber(['order', 'item']);
    Route::delete('/{order}/items/{item}', [BuyerOrderItemController::class, 'destroy'])
        ->middleware('permission:buyer-order.manage')
        ->whereNumber(['order', 'item']);
});

Route::prefix('planning')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/forecasts', [DemandForecastController::class, 'index'])->middleware('permission:planning.view');
    Route::post('/forecasts/preview', [DemandForecastController::class, 'preview'])->middleware('permission:planning.view');
    Route::post('/forecasts', [DemandForecastController::class, 'store'])->middleware('permission:planning.manage');
    Route::get('/forecasts/{forecast}', [DemandForecastController::class, 'show'])
        ->middleware('permission:planning.view')
        ->whereNumber('forecast');
    Route::put('/forecasts/{forecast}', [DemandForecastController::class, 'update'])
        ->middleware('permission:planning.manage')
        ->whereNumber('forecast');
    Route::post('/forecasts/{forecast}/activate', [DemandForecastController::class, 'activate'])
        ->middleware('permission:planning.manage')
        ->whereNumber('forecast');

    Route::get('/supply-plans', [SupplyPlanController::class, 'index'])->middleware('permission:planning.view');
    Route::post('/supply-plans/preview', [SupplyPlanController::class, 'preview'])->middleware('permission:planning.view');
    Route::post('/supply-plans/generate', [SupplyPlanController::class, 'generate'])->middleware('permission:planning.manage');
    Route::get('/supply-plans/{supplyPlan}', [SupplyPlanController::class, 'show'])
        ->middleware('permission:planning.view')
        ->whereNumber('supplyPlan');
    Route::post('/supply-plans/{supplyPlan}/recalculate', [SupplyPlanController::class, 'recalculate'])
        ->middleware('permission:planning.manage')
        ->whereNumber('supplyPlan');

    Route::get('/material-requirements', [MaterialRequirementController::class, 'index'])->middleware('permission:planning.view');
    Route::post('/material-requirements/preview', [MaterialRequirementController::class, 'preview'])->middleware('permission:planning.view');
    Route::post('/material-requirements/generate', [MaterialRequirementController::class, 'generate'])->middleware('permission:planning.manage');
    Route::get('/material-requirements/{run}', [MaterialRequirementController::class, 'show'])
        ->middleware('permission:planning.view')
        ->whereNumber('run');
});

Route::prefix('procurement')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/requisitions', [PurchaseRequisitionController::class, 'index'])->middleware('permission:procurement.view');
    Route::post('/requisitions', [PurchaseRequisitionController::class, 'store'])->middleware('permission:procurement.manage');
    Route::get('/requisitions/{requisition}', [PurchaseRequisitionController::class, 'show'])
        ->middleware('permission:procurement.view')
        ->whereNumber('requisition');
    Route::put('/requisitions/{requisition}', [PurchaseRequisitionController::class, 'update'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('requisition');
    Route::post('/requisitions/{requisition}/submit', [PurchaseRequisitionController::class, 'submit'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('requisition');
    Route::post('/requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])
        ->middleware('permission:procurement.approve')
        ->whereNumber('requisition');
    Route::post('/requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject'])
        ->middleware('permission:procurement.approve')
        ->whereNumber('requisition');
    Route::post('/requisitions/{requisition}/convert', [PurchaseRequisitionController::class, 'convert'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('requisition');
    Route::get('/requisitions/{requisition}/history', [PurchaseRequisitionController::class, 'history'])
        ->middleware('permission:procurement.view')
        ->whereNumber('requisition');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:procurement.view');
    Route::post('/purchase-orders/preview', [PurchaseOrderController::class, 'preview'])->middleware('permission:procurement.view');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:procurement.manage');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->middleware('permission:procurement.view')
        ->whereNumber('purchaseOrder');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('purchaseOrder');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('purchaseOrder');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:procurement.approve')
        ->whereNumber('purchaseOrder');
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('purchaseOrder');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('purchaseOrder');
    Route::post('/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('purchaseOrder');
    Route::get('/purchase-orders/{purchaseOrder}/history', [PurchaseOrderController::class, 'history'])
        ->middleware('permission:procurement.view')
        ->whereNumber('purchaseOrder');

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:procurement.view');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:procurement.manage');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
        ->middleware('permission:procurement.view')
        ->whereNumber('goodsReceipt');
    Route::post('/goods-receipts/{goodsReceipt}/receive', [GoodsReceiptController::class, 'receive'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('goodsReceipt');
    Route::post('/goods-receipts/{goodsReceipt}/accept', [GoodsReceiptController::class, 'accept'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('goodsReceipt');
    Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])
        ->middleware('permission:procurement.manage')
        ->whereNumber('goodsReceipt');
    Route::get('/goods-receipts/{goodsReceipt}/history', [GoodsReceiptController::class, 'history'])
        ->middleware('permission:procurement.view')
        ->whereNumber('goodsReceipt');

    Route::get('/history', [ProcurementHistoryController::class, 'index'])->middleware('permission:procurement.view');
});

Route::prefix('inventory')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
    Route::get('/summary', [InventoryController::class, 'summary'])->middleware('permission:inventory.view');
    Route::get('/history', [InventoryController::class, 'history'])->middleware('permission:inventory.view');
    Route::post('/stock-in', [InventoryController::class, 'stockIn'])->middleware('permission:inventory.manage');
    Route::post('/stock-out', [InventoryController::class, 'stockOut'])->middleware('permission:inventory.manage');
    Route::get('/warehouse/{warehouse}/stock', [InventoryController::class, 'warehouseStock'])
        ->middleware('permission:inventory.view')
        ->whereNumber('warehouse');
    Route::get('/location/{warehouseLocation}/stock', [InventoryController::class, 'locationStock'])
        ->middleware('permission:inventory.view')
        ->whereNumber('warehouseLocation');

    Route::get('/transfers', [StockTransferController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('/transfers', [StockTransferController::class, 'store'])->middleware('permission:inventory.manage');
    Route::get('/transfers/{stockTransfer}', [StockTransferController::class, 'show'])
        ->middleware('permission:inventory.view')
        ->whereNumber('stockTransfer');
    Route::get('/transfers/{stockTransfer}/history', [StockTransferController::class, 'history'])
        ->middleware('permission:inventory.view')
        ->whereNumber('stockTransfer');

    Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:inventory.adjust');
    Route::get('/adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show'])
        ->middleware('permission:inventory.view')
        ->whereNumber('stockAdjustment');
    Route::get('/adjustments/{stockAdjustment}/history', [StockAdjustmentController::class, 'history'])
        ->middleware('permission:inventory.view')
        ->whereNumber('stockAdjustment');

    Route::get('/{inventoryBalance}', [InventoryController::class, 'show'])
        ->middleware('permission:inventory.view')
        ->whereNumber('inventoryBalance');
});

Route::prefix('production')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/plans', [ProductionPlanController::class, 'index'])->middleware('permission:production.view');
    Route::post('/plans', [ProductionPlanController::class, 'store'])->middleware('permission:production.manage');
    Route::get('/plans/{productionPlan}', [ProductionPlanController::class, 'show'])
        ->middleware('permission:production.view')
        ->whereNumber('productionPlan');
    Route::put('/plans/{productionPlan}', [ProductionPlanController::class, 'update'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionPlan');
    Route::post('/plans/{productionPlan}/approve', [ProductionPlanController::class, 'approve'])
        ->middleware('permission:production.approve')
        ->whereNumber('productionPlan');
    Route::post('/plans/{productionPlan}/status', [ProductionPlanController::class, 'transition'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionPlan');

    Route::get('/orders', [ProductionOrderController::class, 'index'])->middleware('permission:production.view');
    Route::post('/orders', [ProductionOrderController::class, 'store'])->middleware('permission:production.manage');
    Route::get('/orders/{productionOrder}', [ProductionOrderController::class, 'show'])
        ->middleware('permission:production.view')
        ->whereNumber('productionOrder');
    Route::get('/orders/{productionOrder}/availability', [ProductionOrderController::class, 'availability'])
        ->middleware('permission:production.view')
        ->whereNumber('productionOrder');
    Route::post('/orders/{productionOrder}/start', [ProductionOrderController::class, 'start'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionOrder');
    Route::post('/orders/{productionOrder}/status', [ProductionOrderController::class, 'status'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionOrder');
    Route::post('/orders/{productionOrder}/consume', [ProductionOrderController::class, 'consume'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionOrder');
    Route::post('/orders/{productionOrder}/progress', [ProductionOrderController::class, 'progress'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionOrder');
    Route::post('/orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])
        ->middleware('permission:production.manage')
        ->whereNumber('productionOrder');

    Route::get('/consumptions', [MaterialConsumptionController::class, 'index'])->middleware('permission:production.view');
    Route::get('/progress', [ProductionProgressController::class, 'index'])->middleware('permission:production.view');
    Route::get('/finished-goods', [FinishedGoodsController::class, 'index'])->middleware('permission:production.view');
    Route::get('/finished-goods/{finishedGoods}', [FinishedGoodsController::class, 'show'])
        ->middleware('permission:production.view')
        ->whereNumber('finishedGoods');
    Route::get('/history', [ProductionHistoryController::class, 'index'])->middleware('permission:production.view');
});

Route::prefix('sales')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/orders', [SalesOrderController::class, 'index'])->middleware('permission:sales.view');
    Route::post('/orders/preview', [SalesOrderController::class, 'preview'])->middleware('permission:sales.view');
    Route::post('/orders', [SalesOrderController::class, 'store'])->middleware('permission:sales.manage');
    Route::get('/orders/{salesOrder}', [SalesOrderController::class, 'show'])
        ->middleware('permission:sales.view')
        ->whereNumber('salesOrder');
    Route::put('/orders/{salesOrder}', [SalesOrderController::class, 'update'])
        ->middleware('permission:sales.manage')
        ->whereNumber('salesOrder');
    Route::post('/orders/{salesOrder}/submit', [SalesOrderController::class, 'submit'])
        ->middleware('permission:sales.manage')
        ->whereNumber('salesOrder');
    Route::post('/orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])
        ->middleware('permission:sales.confirm')
        ->whereNumber('salesOrder');
    Route::post('/orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
        ->middleware('permission:sales.manage')
        ->whereNumber('salesOrder');
    Route::post('/orders/{salesOrder}/status', [SalesOrderController::class, 'status'])
        ->middleware('permission:sales.manage')
        ->whereNumber('salesOrder');
    Route::get('/orders/{salesOrder}/availability', [SalesOrderController::class, 'availability'])
        ->middleware('permission:sales.view')
        ->whereNumber('salesOrder');
    Route::get('/orders/{salesOrder}/status-history', [SalesOrderController::class, 'statusHistory'])
        ->middleware('permission:sales.view')
        ->whereNumber('salesOrder');
    Route::get('/history', [SalesHistoryController::class, 'index'])->middleware('permission:sales.view');
});

Route::prefix('deliveries')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/', [DeliveryController::class, 'index'])->middleware('permission:delivery.view');
    Route::post('/', [DeliveryController::class, 'store'])->middleware('permission:delivery.manage');
    Route::get('/history', [DeliveryHistoryController::class, 'index'])->middleware('permission:delivery.view');
    Route::get('/{delivery}', [DeliveryController::class, 'show'])
        ->middleware('permission:delivery.view')
        ->whereNumber('delivery');
    Route::put('/{delivery}', [DeliveryController::class, 'update'])
        ->middleware('permission:delivery.manage')
        ->whereNumber('delivery');
    Route::post('/{delivery}/dispatch', [DeliveryController::class, 'dispatch'])
        ->middleware('permission:delivery.dispatch')
        ->whereNumber('delivery');
    Route::post('/{delivery}/status', [DeliveryController::class, 'status'])
        ->middleware('permission:delivery.manage')
        ->whereNumber('delivery');
    Route::post('/{delivery}/tracking', [DeliveryController::class, 'tracking'])
        ->middleware('permission:delivery.manage')
        ->whereNumber('delivery');
    Route::post('/{delivery}/complete', [DeliveryController::class, 'complete'])
        ->middleware('permission:delivery.manage')
        ->whereNumber('delivery');
    Route::get('/{delivery}/history', [DeliveryController::class, 'history'])
        ->middleware('permission:delivery.view')
        ->whereNumber('delivery');
    Route::get('/{delivery}/tracking-history', [DeliveryController::class, 'trackingHistory'])
        ->middleware('permission:delivery.view')
        ->whereNumber('delivery');
});

Route::prefix('finance')->middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:finance.view');
    Route::get('/invoices/eligible-sales-orders', [InvoiceController::class, 'eligibleSalesOrders'])->middleware('permission:finance.view');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:finance.manage');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:finance.view')
        ->whereNumber('invoice');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
        ->middleware('permission:finance.manage')
        ->whereNumber('invoice');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->middleware('permission:finance.manage')
        ->whereNumber('invoice');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
        ->middleware('permission:finance.manage')
        ->whereNumber('invoice');
    Route::post('/invoices/{invoice}/status', [InvoiceController::class, 'status'])
        ->middleware('permission:finance.manage')
        ->whereNumber('invoice');
    Route::get('/invoices/{invoice}/history', [InvoiceController::class, 'history'])
        ->middleware('permission:finance.view')
        ->whereNumber('invoice');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:finance.view');
    Route::get('/payments/history', [PaymentController::class, 'index'])->middleware('permission:finance.view');
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:finance.pay');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])
        ->middleware('permission:finance.view')
        ->whereNumber('payment');
    Route::post('/payments/{payment}/status', [PaymentController::class, 'status'])
        ->middleware('permission:finance.pay')
        ->whereNumber('payment');
    Route::get('/payments/{payment}/history', [PaymentController::class, 'history'])
        ->middleware('permission:finance.view')
        ->whereNumber('payment');

    Route::get('/receivables', [FinanceSummaryController::class, 'receivables'])->middleware('permission:finance.view');
    Route::get('/payables', [FinanceSummaryController::class, 'payables'])->middleware('permission:finance.view');
    Route::get('/profit', [FinanceSummaryController::class, 'profit'])->middleware('permission:finance.view');
    Route::get('/history', [FinanceHistoryController::class, 'index'])->middleware('permission:finance.view');
});

Route::prefix('master-data')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/{resource}/options', [MasterDataController::class, 'options'])
        ->middleware('permission:master-data.view')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()));

    Route::get('/{resource}', [MasterDataController::class, 'index'])
        ->middleware('permission:master-data.view')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()));

    Route::post('/{resource}', [MasterDataController::class, 'store'])
        ->middleware('permission:master-data.manage')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()));

    Route::get('/{resource}/{id}', [MasterDataController::class, 'show'])
        ->middleware('permission:master-data.view')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()))
        ->whereNumber('id');

    Route::put('/{resource}/{id}', [MasterDataController::class, 'update'])
        ->middleware('permission:master-data.manage')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()))
        ->whereNumber('id');

    Route::delete('/{resource}/{id}', [MasterDataController::class, 'destroy'])
        ->middleware('permission:master-data.manage')
        ->whereIn('resource', array_keys(MasterDataRegistry::definitions()))
        ->whereNumber('id');
});

Route::prefix('reports')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/{report}', [ReportsController::class, 'index'])
        ->middleware('permission:reports.view')
        ->whereIn('report', ['sales', 'purchase', 'stock', 'profit', 'production', 'payment', 'delivery', 'inventory-movement', 'supplier-performance', 'buyer-customer']);
    Route::get('/{report}/export', [ReportsController::class, 'export'])
        ->middleware('permission:reports.export')
        ->whereIn('report', ['sales', 'purchase', 'stock', 'profit', 'production', 'payment', 'delivery', 'inventory-movement', 'supplier-performance', 'buyer-customer']);
});

Route::prefix('dashboards')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/{dashboard}', [DashboardController::class, 'show'])
        ->middleware('permission:dashboard.view')
        ->whereIn('dashboard', ['executive', 'supply-chain', 'production', 'procurement', 'warehouse']);
});

Route::prefix('alerts')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/', [AlertController::class, 'index'])->middleware('permission:alerts.view');
    Route::post('/refresh', [AlertController::class, 'refresh'])->middleware('permission:alerts.manage');
    Route::put('/{alert}/state', [AlertController::class, 'state'])
        ->middleware('permission:alerts.manage')
        ->whereNumber('alert');
});
