# GarmentFlow Phase 11 Finance Management Design

## Boundary and existing-state findings

The verified repository contains no application-owned Finance tables, models, services, controllers, routes, requests, resources, policies, pages, or Finance tests. Existing Procurement stores Purchase Order monetary fields and Goods Receipt acceptance quantities; existing Sales stores order totals and delivery progress; existing Inventory and Production store movements and outputs but do not persist a separate historical finished-goods cost ledger. Finance therefore adds only invoices, invoice items, and payments, derives Accounts Payable from existing Purchase Orders, and reports profit with an explicit cost-completeness flag rather than fabricated costs.

## Finance-specific data structures

The `invoices` table stores invoice number, one eligible Sales Order, buyer or customer party, invoice and due dates, status, totals, payment rollups, creator, issue timestamp, remarks, timestamps, and soft deletes. `invoice_items` stores invoice lines linked back to Sales Order Items and existing product, variant, and unit identities, quantity, unit price, discount, tax, line total, and line number. `payments` stores payment number, invoice, party, payment date, amount, extensible payment method, reference number, idempotency key, status, remarks, receiving user, and timestamps. No duplicate supplier, purchase order, goods receipt, inventory, or ledger tables are created.

## Invoice rules

Invoices may be created only for eligible delivered or completed Sales Orders and a Sales Order may have at most one invoice in this scope. Invoice lines derive from the Sales Order’s delivered quantity when positive, otherwise confirmed quantity, and retain the Sales Order pricing. Backend invoice calculation owns subtotal, discount, tax, total, paid, and due amounts. Draft invoices may be issued or cancelled. Issued invoices may become partially paid, paid, overdue, or cancelled subject to guarded transitions. Payment is not allowed against draft, cancelled, or fully paid invoices.

## Payment rules

Payments execute inside a database transaction while locking the Invoice. Amounts must be positive and cannot exceed the current due amount. Partial payments update the Invoice to `partially_paid`; a payment that closes the due amount updates it to `paid`. Payment number, idempotency key, and invoice-scoped reference number checks prevent duplicate payment submission. Payment cancellation is auditable and reverses the payment rollup without creating a separate financial ledger.

## Receivables and payables

Accounts Receivable aggregates non-draft, non-cancelled invoices into total invoiced, total paid, total outstanding, overdue amount, and partially paid invoice counts, with buyer/customer party breakdowns. Accounts Payable is derived directly from existing Purchase Orders in approved, supplier-sent, receipt-progress, or closed states. Because no payable settlement entity exists in the verified architecture, payable outstanding equals the eligible Purchase Order total and the API exposes this limitation rather than pretending supplier payments exist.

## Profit

Profit uses non-cancelled invoice net sales and existing master-data cost fields. For each invoice line, cost resolves from `ProductVariant.cost_price` when present, otherwise `Product.standard_cost`. If a line has no usable cost, the response sets `cost_data_complete` false, reports the count and identities of unpriced lines, and does not claim a fully reliable gross profit or margin. No cost is invented from quantity, price, or arbitrary defaults. Where all costs are available, the API exposes gross sales, COGS, gross profit, and profit margin.

## Backend and API surface

Controllers remain thin and delegate to Finance services. The protected `/api/finance` route group will provide invoice list/create/show/update/status, payment list/create/show/status/history, receivables, payables, profit summary, and Finance history endpoints. Requests, resources, policies, and seeded `finance.view`, `finance.manage`, `finance.pay`, and `finance.override` permissions follow the established Laravel/Sanctum/Gate architecture. All important invoice and payment operations write to the shared AuditLog service.

## Frontend surface

A protected `/finance` page will reuse the existing GarmentFlow layout and Axios client. Tabs cover Invoice List, Create Invoice, Invoice Details, Payment List, Record Payment, Payment History, Receivables, Payables, and Profit Summary. Search, status/party/date filters, pagination, backend-calculated totals, status badges, validation, loading, empty, error, and success states are included. No Phase 12 or advanced dashboard screen is added.

## Verification plan

Automated coverage will test invoice eligibility and calculations, issue/cancel transitions, partial and full payment, overpayment and duplicate prevention, receivables, derived payables, cost-complete and cost-incomplete profit results, Finance authorization, validation, and audit history. Full Laravel regressions, migrations, syntax, Pint, Composer, frontend lint/build, and browser smoke will run after implementation. Any live smoke records will use exact markers and be removed with guarded cleanup.
