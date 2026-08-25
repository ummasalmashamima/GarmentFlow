<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Finance\InvoiceWorkflow;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_calculation_issue_partial_full_payment_duplicate_prevention_and_receivables_are_supported(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['finance.view', 'finance.manage', 'finance.pay']);
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->financeFixtures();
        $orderId = $this->deliveredOrder($user, $buyer->id, null, $product, $variant, $unit, $warehouse, 'SO-FIN-0001', 2, 10, 1, 2);

        $create = $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $orderId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
            'remarks' => 'Finance integration invoice.',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.status', InvoiceWorkflow::DRAFT)
            ->assertJsonPath('data.subtotal', '20.0000')
            ->assertJsonPath('data.discount_amount', '1.0000')
            ->assertJsonPath('data.tax_amount', '2.0000')
            ->assertJsonPath('data.total_amount', '21.0000')
            ->assertJsonPath('data.paid_amount', '0.0000')
            ->assertJsonPath('data.due_amount', '21.0000')
            ->assertJsonCount(1, 'data.items');
        $invoiceId = $create->json('data.id');
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'sales_order_id' => $orderId, 'status' => InvoiceWorkflow::DRAFT]);
        $this->assertDatabaseCount('payments', 0);

        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/issue", ['remarks' => 'Invoice issued for collection.'])
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceWorkflow::ISSUED);

        $paymentPayload = [
            'invoice_id' => $invoiceId,
            'payment_date' => '2026-08-21',
            'amount' => 5,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'FIN-REF-001',
            'idempotency_key' => 'finance-test-payment-001',
            'remarks' => 'Partial collection.',
        ];
        $this->withToken($token)->postJson('/api/finance/payments', $paymentPayload)
            ->assertCreated()
            ->assertJsonPath('data.amount', '5.0000')
            ->assertJsonPath('data.status', Payment::RECEIVED);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => InvoiceWorkflow::PARTIALLY_PAID, 'paid_amount' => '5.0000', 'due_amount' => '16.0000']);

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/finance/payments', $paymentPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
        $this->assertDatabaseCount('payments', 1);

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/finance/payments', [
            ...$paymentPayload,
            'amount' => 16,
            'reference_number' => 'FIN-REF-002',
            'idempotency_key' => 'finance-test-payment-002',
            'remarks' => 'Final collection.',
        ])->assertCreated();
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => InvoiceWorkflow::PAID, 'paid_amount' => '21.0000', 'due_amount' => '0.0000']);

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/finance/payments', [
            ...$paymentPayload,
            'amount' => 1,
            'reference_number' => 'FIN-REF-003',
            'idempotency_key' => 'finance-test-payment-003',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertDatabaseCount('payments', 2);

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson("/api/finance/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceWorkflow::PAID)
            ->assertJsonPath('data.paid_amount', '21.0000')
            ->assertJsonPath('data.due_amount', '0.0000')
            ->assertJsonCount(2, 'data.payments');
        $this->withToken($token)->getJson('/api/finance/receivables')
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', '21.0000')
            ->assertJsonPath('data.total_paid', '21.0000')
            ->assertJsonPath('data.total_outstanding', '0.0000')
            ->assertJsonPath('data.partially_paid_invoice_count', 0);
        $this->withToken($token)->getJson('/api/finance/invoices/'.$invoiceId.'/history')
            ->assertOk()
            ->assertJsonCount(6, 'data.audit_logs');
        $this->withToken($token)->getJson('/api/finance/history?module=payments&per_page=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->assertGreaterThanOrEqual(6, AuditLog::query()->where('module', 'invoices')->where('record_id', $invoiceId)->count());
    }

    public function test_invoice_eligibility_invalid_quantities_and_status_transitions_are_enforced(): void
    {
        $user = $this->administrator();
        $token = $this->token($user, ['finance.view', 'finance.manage']);
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->financeFixtures();
        $confirmedId = $this->deliveredOrder($user, $buyer->id, null, $product, $variant, $unit, $warehouse, 'SO-FIN-0002', 1, 10, 0, 0, 'confirmed');
        $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $confirmedId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
        ])->assertUnprocessable()->assertJsonValidationErrors(['sales_order_id']);

        $cancelledId = $this->deliveredOrder($user, $buyer->id, null, $product, $variant, $unit, $warehouse, 'SO-FIN-0003', 2, 10, 0, 0, 'cancelled');
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $cancelledId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
        ])->assertUnprocessable()->assertJsonValidationErrors(['sales_order_id']);

        $eligibleId = $this->deliveredOrder($user, $buyer->id, null, $product, $variant, $unit, $warehouse, 'SO-FIN-0004', 2, 10, 0, 0);
        $create = $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $eligibleId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
            'items' => [['sales_order_item_id' => SalesOrderItem::query()->where('sales_order_id', $eligibleId)->value('id'), 'quantity' => 3]],
        ]);
        $create->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);

        $valid = $this->withToken($token)->postJson('/api/finance/invoices', [
            'sales_order_id' => $eligibleId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
        ])->assertCreated();
        $invoiceId = $valid->json('data.id');
        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/status", ['status' => 'paid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/issue", [])->assertOk();
        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/cancel", ['remarks' => 'No longer required.'])
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceWorkflow::CANCELLED);
        $this->withToken($token)->postJson("/api/finance/invoices/{$invoiceId}/issue", [])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_derived_payables_profit_cost_limitation_and_authorization_are_enforced(): void
    {
        $user = $this->administrator();
        $viewToken = $this->token($user, ['finance.view']);
        $manageToken = $this->token($user, ['finance.view', 'finance.manage']);
        $dashboardToken = $this->token($user, ['dashboard.view']);
        [$buyer, , $product, $variant, $unit, $warehouse] = $this->financeFixtures();
        $orderId = $this->deliveredOrder($user, $buyer->id, null, $product, $variant, $unit, $warehouse, 'SO-FIN-0005', 2, 10, 0, 0);
        $invoiceId = $this->withToken($manageToken)->postJson('/api/finance/invoices', [
            'sales_order_id' => $orderId,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
        ])->assertCreated()->json('data.id');

        app('auth')->forgetGuards();
        $this->withToken($dashboardToken)->getJson('/api/finance/invoices')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson('/api/finance/invoices', [])->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->getJson('/api/finance/invoices')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson('/api/finance/payments', [
            'invoice_id' => $invoiceId,
            'payment_date' => '2026-08-21',
            'amount' => 20,
            'payment_method' => 'cash',
            'idempotency_key' => 'finance-test-payment-005',
        ])->assertForbidden();

        $supplier = Supplier::query()->firstOrFail();
        $material = Material::query()->firstOrFail();
        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_order_number' => 'PO-FIN-0001',
            'supplier_id' => $supplier->id,
            'po_date' => '2026-08-01',
            'expected_delivery_date' => '2026-08-20',
            'currency' => 'BDT',
            'payment_terms' => '30 days',
            'shipping_terms' => 'FOB',
            'subtotal' => 100,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 100,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_requisition_item_id' => null,
            'material_id' => $material->id,
            'unit_id' => $material->unit_id,
            'quantity' => 10,
            'unit_price' => 10,
            'line_total' => 100,
            'received_quantity' => 5,
            'line_number' => 1,
        ]);
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->getJson('/api/finance/payables')
            ->assertOk()
            ->assertJsonPath('data.total_payable', '100.0000')
            ->assertJsonPath('data.goods_received_value', '50.0000')
            ->assertJsonPath('data.outstanding_payable', '100.0000')
            ->assertJsonPath('data.purchase_order_count', 1);

        $category = Category::query()->firstOrFail();
        $noCostProduct = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'FIN-NOCOST-001',
            'name' => 'Finance no-cost product',
            'product_type' => 'finished_good',
            'standard_cost' => 0,
            'standard_price' => 10,
            'status' => 'active',
        ]);
        $noCostVariant = ProductVariant::query()->create([
            'product_id' => $noCostProduct->id,
            'size_id' => $variant->size_id,
            'color_id' => $variant->color_id,
            'sku' => 'FIN-NOCOST-V1',
            'variant_name' => 'Finance no-cost variant',
            'cost_price' => null,
            'selling_price' => 10,
            'status' => 'active',
        ]);
        $noCostOrder = $this->deliveredOrder($user, $buyer->id, null, $noCostProduct, $noCostVariant, $unit, $warehouse, 'SO-FIN-0006', 1, 10, 0, 0);
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/finance/invoices', [
            'sales_order_id' => $noCostOrder,
            'invoice_date' => '2026-08-20',
            'due_date' => '2099-12-31',
        ])->assertCreated();
        $noCostInvoice = Invoice::query()->where('sales_order_id', $noCostOrder)->value('id');
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson("/api/finance/invoices/{$noCostInvoice}/issue", [])->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->getJson('/api/finance/profit')
            ->assertOk()
            ->assertJsonPath('data.cost_data_complete', false)
            ->assertJsonPath('data.gross_profit', null)
            ->assertJsonPath('data.profit_margin', null)
            ->assertJsonPath('data.unpriced_line_count', 1);
    }

    /** @return array{0: Buyer, 1: Customer, 2: Product, 3: ProductVariant, 4: Unit, 5: Warehouse} */
    private function financeFixtures(): array
    {
        $buyer = Buyer::query()->where('code', 'BUY-001')->firstOrFail();
        $customer = Customer::query()->where('code', 'CUS-001')->firstOrFail();
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail();
        $variant->forceFill(['cost_price' => 4.25])->save();
        $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'DHK-01')->firstOrFail();

        return [$buyer, $customer, $product, $variant, $unit, $warehouse];
    }

    private function deliveredOrder(User $user, int $buyerId, ?int $customerId, Product $product, ProductVariant $variant, Unit $unit, Warehouse $warehouse, string $number, int $quantity, float $unitPrice, float $discount, float $tax, string $status = 'delivered'): int
    {
        $gross = $quantity * $unitPrice;
        $total = $gross - $discount + $tax;
        $order = SalesOrder::query()->create([
            'sales_order_number' => $number,
            'buyer_id' => $buyerId,
            'customer_id' => $customerId,
            'order_date' => '2026-08-01',
            'required_delivery_date' => '2026-08-20',
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'subtotal' => $gross,
            'order_discount_amount' => 0,
            'order_tax_amount' => 0,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'ordered_quantity' => $quantity,
            'confirmed_quantity' => $quantity,
            'delivered_quantity' => $status === 'delivered' ? $quantity : 0,
            'remaining_quantity' => $status === 'delivered' ? 0 : $quantity,
            'confirmed_at' => '2026-08-02 09:00:00',
            'created_by' => $user->id,
        ]);
        SalesOrderItem::query()->create([
            'sales_order_id' => $order->id,
            'line_number' => 1,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'unit_id' => $unit->id,
            'ordered_quantity' => $quantity,
            'confirmed_quantity' => $quantity,
            'delivered_quantity' => $status === 'delivered' ? $quantity : 0,
            'remaining_quantity' => $status === 'delivered' ? 0 : $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'line_total' => $gross,
        ]);

        return $order->id;
    }

    private function token(User $user, array $abilities): string
    {
        return $user->createToken('finance-test-'.uniqid('', true), $abilities)->plainTextToken;
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
