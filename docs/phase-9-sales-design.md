# GarmentFlow Phase 9 — Sales Management Design

## Authority and current state

The original GarmentFlow Master Instruction remains authoritative. `pasted_content_7.txt` is treated as a Phase 9 implementation addendum only. Inspection of the repository confirmed that Phases 1–8 are present and verified, while Sales Management is absent: there are no Sales migrations, models, services, controllers, requests, resources, policies, routes, tests, frontend page, frontend service, or Phase 9 report. The existing Buyer Order domain is not replaced and will remain the upstream commercial-demand workflow.

Phase 9 adds only Sales Management. It does not implement Delivery, shipment tracking, courier management, delivery tables/controllers/frontend, Finance, invoices, payments, profit, Reports, or Phase 11.

## Sales source of truth and workflow

Sales Orders use existing Buyer, Customer, Product, Product Variant, Unit, Warehouse, and canonical Phase 7 Inventory records. A Sales Order has one commercial party represented by either an existing Buyer or Customer reference, multiple product lines, a warehouse, pricing totals, status, and delivery-preparation quantities. A Sales Order Item stores the product, optional variant, unit, ordered quantity, unit price, line total, and delivery progress.

The workflow is:

> Buyer or Customer → Sales Order Draft → Submitted → Finished-Goods Availability Check → Confirmed → Ready for Delivery → Delivered → Completed.

Draft, Submitted, and Confirmed orders may be cancelled. Draft orders are editable. Submitted orders require the dedicated confirmation permission and are not freely editable. Confirmed, Ready for Delivery, Delivered, Completed, and Cancelled orders are protected from ordinary edits. Phase 9 does not deduct stock when creating or confirming a Sales Order; stock deduction remains a Phase 10 dispatch concern.

## Minimal Sales schema

Three Sales-specific tables are required:

| Table | Purpose | Key controls |
| --- | --- | --- |
| `sales_orders` | Sales header, party, warehouse, delivery-preparation quantities, totals, status, and remarks | Unique sales order number; buyer/customer references; status/date indexes; timestamps; referential integrity |
| `sales_order_items` | Product/variant/unit line items and delivery progress | Product/variant/unit foreign keys; positive ordered quantity; unique line identity per order/variant; calculated line total; delivered and remaining quantities |
| `sales_order_status_histories` | Immutable Sales status changes | Previous/new status; actor; timestamp; remarks; order foreign key and index |

The `sales_orders` header stores `buyer_id` and `customer_id` as nullable alternatives, `sales_order_number`, `order_date`, `required_delivery_date`, `warehouse_id`, `status`, aggregate `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, separate `order_discount_amount` and `order_tax_amount` components, `ordered_quantity`, `confirmed_quantity`, `delivered_quantity`, `remaining_quantity`, optional `delivery_address`, `contact_information`, and remarks. A database check and service validation ensure exactly one active Buyer or Customer party is selected. The fields needed by future Delivery work are retained directly in the Sales model without creating any Delivery structure.

The `sales_order_items` table stores `sales_order_id`, `product_id`, nullable `product_variant_id`, `unit_id`, `ordered_quantity`, `confirmed_quantity`, `delivered_quantity`, `remaining_quantity`, `unit_price`, `discount_amount`, `tax_amount`, `line_total`, and remarks. The service calculates all totals and progress values; the frontend never acts as the business source of truth.

## Calculation rules

The reusable `SalesOrderCalculationService` will calculate each line and aggregate totals in four-decimal precision. The aggregate discount/tax fields include line-level amounts plus the separate header-level `order_discount_amount` and `order_tax_amount` components:

> **Line Total = Ordered Quantity × Unit Price**
>
> **Subtotal = Sum of line totals**
>
> **Order Total = Subtotal + Applicable Tax − Applicable Discount**
>
> **Remaining Quantity = Ordered Quantity − Delivered Quantity**

The primary addendum formula is respected: quantity multiplied by unit price forms the line total, and order total is the sum of line totals plus applicable tax less applicable discount. Per-line discounts and taxes are represented explicitly while aggregate discount/tax fields are normalized by the service. Negative totals and invalid quantities are rejected.

## Finished-goods availability

Before confirmation, `SalesOrderService` calls a new read-only availability method on the existing `InventoryService` rather than querying or mutating inventory directly. For each line, it resolves the canonical stock identity through `InventoryReferenceService`, using the selected warehouse and the product variant when present, then returns required quantity, on-hand quantity, reserved quantity, available quantity, and shortage quantity.

> **Available Stock = Quantity on Hand − Quantity Reserved**
>
> **Shortage = max(Required Quantity − Available Stock, 0)**

Confirmation is rejected when any line is short unless the explicit `sales.override` permission is present. Creating a draft, updating a draft, submitting, and confirming do not create inventory transactions or reduce stock. This leaves actual dispatch stock-out for Phase 10 while giving Delivery a clean order/line progress source.

## Status workflow and audit

`SalesOrderWorkflow` centralizes allowed statuses and transitions. The normal path is `draft → submitted → confirmed → ready_for_delivery → delivered → completed`. Cancellation is allowed from draft, submitted, and confirmed. Terminal statuses are completed and cancelled. Confirmation is a dedicated action because it performs availability validation and sets `confirmed_quantity` to the ordered quantity. Status history and the existing `AuditLogService` record every transition with previous status, new status, actor, timestamp, remarks, and changed values.

The `SalesOrderService` owns creation, draft replacement/update, totals, availability, submit, confirm, cancel, status transition, history, and Delivery preparation data. Controllers remain thin. All state changes use database transactions. No delivery or dispatch operation is implemented in Phase 9.

## Authorization

The existing permission architecture is extended idempotently only with the Sales permissions needed by the Sales domain:

| Permission | Purpose |
| --- | --- |
| `sales.view` | Read Sales Orders, availability, status history, and Sales History. |
| `sales.manage` | Create and update draft Sales Orders and perform allowed non-confirmation actions. |
| `sales.confirm` | Confirm a submitted Sales Order after availability validation. |
| `sales.override` | Explicitly bypass insufficient finished-goods availability for confirmation. |

The Administrator role receives these permissions through the existing `syncWithoutDetaching` seeder convention, and AuthService includes them in newly issued Sanctum token abilities. Backend permission middleware and Form Request authorization remain mandatory; frontend visibility is not treated as security.

## API surface

The protected `/api/sales` route group will follow the established `auth:sanctum`, scoped bindings, permission middleware, JSON Resource, pagination, filtering, and sorting conventions:

| Endpoint | Purpose |
| --- | --- |
| `GET /api/sales/orders` | Searchable, filterable, sortable, paginated Sales Order list. |
| `POST /api/sales/orders` | Create a draft Sales Order with multiple items. |
| `GET /api/sales/orders/{salesOrder}` | Sales Order detail with items, availability-ready quantities, and status history. |
| `PUT /api/sales/orders/{salesOrder}` | Update a draft Sales Order only. |
| `POST /api/sales/orders/{salesOrder}/submit` | Submit a draft. |
| `POST /api/sales/orders/{salesOrder}/confirm` | Availability-checked confirmation. |
| `POST /api/sales/orders/{salesOrder}/cancel` | Cancel an eligible order. |
| `POST /api/sales/orders/{salesOrder}/status` | Perform a validated non-dedicated status transition. |
| `GET /api/sales/orders/{salesOrder}/availability` | Finished-goods availability by line. |
| `GET /api/sales/orders/{salesOrder}/status-history` | Sales status history. |
| `GET /api/sales/history` | Audit-backed Sales History. |

List filters include search, status, buyer, customer, warehouse, order-date range, required-delivery-date range, tracking-preparation fields where applicable, pagination, and an allowlisted sort/direction pair.

## Frontend surface

`/sales` will reuse the existing authenticated AppLayout and Buyer Orders page conventions. `SalesOrderPage.jsx` will provide a Sales Order register, create/edit-draft form with repeatable items, detail modal, backend total preview, availability panel, workflow controls, status history, Sales History, filter controls, pagination, loading/error/success/empty states, and status badges. `salesService.js` will remain a thin Axios boundary.

The sidebar and protected route will add Sales without changing existing routes. The UI will load existing Buyer, Customer, Product, Product Variant, Unit, and Warehouse options through the generic master-data service. Phase 10 is prepared by exposing sales order number, buyer/customer, delivery address/contact, warehouse, items, ordered/confirmed/delivered/remaining quantities, and confirmed status in the Sales resource.

## Verification plan

Permanent tests will cover draft creation with multiple items, backend total calculation, draft update, submit, availability and shortage, confirmation authorization and override, cancellation, invalid transitions, status-history/audit rows, API validation, list filters, no inventory mutation on draft/confirmation, and preserved Sales-to-Delivery preparation quantities. Final checks will include migration status, database schema/constraints, focused Sales API tests, full Laravel regression, PHP syntax, Pint, Composer validation, frontend lint/build, authenticated browser smoke, authorization, and an explicit check that no Phase 10 or Phase 11 artifacts were introduced.
