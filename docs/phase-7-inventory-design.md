# GarmentFlow Phase 7 — Inventory & Warehouse Design

## Authority and Current Boundary

The original GarmentFlow Master Instruction remains authoritative. `pasted_content_3.txt` is treated only as the Phase 7 addendum. Inspection of the current project found verified Phase 1–6 implementation through Procurement, with no Phase 7 inventory migrations, models, services, controllers, routes, frontend page, or permanent inventory tests beyond the pre-existing Warehouse and Warehouse Location master-data models. Phase 7 will therefore add only the missing inventory domain and the minimum shared Phase 6 integration point.

Phase 7 does not start Phase 8 and does not implement Production, Quality Control, Sales, Delivery, Finance, complete Reports, advanced dashboard analytics, or any new master-data tables.

## Inventory Source of Truth

`inventory_balances` is the controlled operational balance table. Every stock-changing operation locks and updates the relevant balance row inside a database transaction and creates a corresponding immutable `inventory_transactions` record in the same transaction. No controller or frontend component may update quantities directly.

The balance table stores `quantity_on_hand` and `quantity_reserved`; `available_quantity` is derived as `quantity_on_hand - quantity_reserved`. The implementation does not create a separate competing stock ledger. A unique service-generated `stock_key` identifies warehouse, optional location, item identity, and unit, avoiding nullable-composite-key duplication while preserving explicit foreign keys to existing Material, Product, Product Variant, Unit, Warehouse, and Warehouse Location records.

Item identity is one of `material`, `product`, or `product_variant`. The service validates that exactly one item reference is populated and that the item is active. Variant balances may also retain the parent product reference for response and reporting context. A material balance represents raw-material stock; product and product-variant balances represent finished-goods stock.

## Transaction Invariants

Every movement records a unique transaction number, item identity, warehouse, optional location, unit, positive quantity, movement type, optional source reference, actor, transaction date, and remarks. Supported types are `STOCK_IN`, `STOCK_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, `ADJUSTMENT_IN`, and `ADJUSTMENT_OUT`.

Stock In increases on-hand quantity. Stock Out requires `quantity <= available_quantity` and decreases on-hand quantity. Adjustments use an explicit direction and reason. No operation permits negative on-hand or available stock. Historical transaction rows are not deleted by application workflows.

## Goods Receipt Integration

Posting an accepted Phase 6 Goods Receipt calls the centralized Inventory Service before the Procurement service completes posting. For each Goods Receipt item, only `accepted_quantity` is stocked in. Rejected quantity never increases available stock. A unique idempotency key per Goods Receipt item prevents duplicate stock-in if the integration is retried. The existing Goods Receipt, Purchase Order, status-history, and audit records remain the source documents; inventory transactions reference the Goods Receipt item.

The existing Phase 6 Goods Receipt allows an optional warehouse location. Phase 7 preserves that contract: when a location is supplied, the stock balance is location-specific; when omitted, the balance remains warehouse-level with a null location. Any supplied location must belong to the selected warehouse.

## Transfer Atomicity

A transfer creates one `stock_transfers` header, one or more `stock_transfer_items`, and paired `TRANSFER_OUT` plus `TRANSFER_IN` transaction rows within one database transaction. The source balance is locked first, availability is checked, and the destination balance is updated only if the source decrement and both transaction records can succeed. Source and destination warehouse/location combinations must differ and must reference active, compatible locations.

If any validation or persistence step fails, the transaction rolls back so neither warehouse changes.

## Adjustments and Authorization

Stock adjustments require an explicit reason, positive quantity, item, warehouse, unit, actor, and timestamp. They are available only to users with `inventory.adjust`; standard stock-in, stock-out, and transfer operations require `inventory.manage`. Read-only inventory and history endpoints require `inventory.view`. The Administrator role receives all three permissions through the idempotent authorization seeder.

## API and UI Surface

The protected `/api/inventory` route group provides inventory list/detail, stock-in, stock-out, transfer, adjustment, transaction history, warehouse stock, location stock, and stock summary endpoints. List/history queries support search, warehouse/location/item/type/date filters, pagination, and sorting.

The protected `/inventory` React page uses the established GarmentFlow shell and Axios service pattern. It provides dashboard summary cards, Inventory, Stock In, Stock Out, Transfer, Adjustment, and History tabs, warehouse/location/item filters, forms, status feedback, validation display, loading/empty/error states, and stock-detail dialogs. It deliberately does not expose an Inventory module outside Phase 7 or a full Reports module.

## Verification Plan

Permanent tests will cover stock in, stock out, insufficient stock, negative-stock prevention, atomic transfers, adjustment authorization and reasons, warehouse/location validation, transaction history, API resources, Goods Receipt accepted-quantity integration, rejected-quantity exclusion, duplicate Goods Receipt posting prevention, and preservation of Phase 1–6 workflows. The live audit will confirm the new tables, foreign keys, unique keys, check constraints, migration order, permissions, transaction/balance consistency, and cleanup of temporary test records.
