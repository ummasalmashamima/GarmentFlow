# GarmentFlow Phase 8 — Production Management Design

## Authority and inspected baseline

The original GarmentFlow Master Instruction remains authoritative. `pasted_content_4.txt` is used only as a Phase 8 addendum/reference. Inspection confirmed the verified Laravel 13/PHP 8.3/MySQL backend and React 19/Vite frontend currently contain Phases 1–7, including active BOMs, Supply Plans, Procurement/Goods Receipts, and the canonical `InventoryService`. The Production page directory remains a placeholder and no production migrations, models, services, controllers, requests, resources, routes, permissions, tests, or frontend service/page exist.

Phase 8 will add only Production Management. It will not rebuild earlier phases, add duplicate product/BOM/inventory structures, or start Phase 9. Quality Control, Sales, Finance, Delivery, full Reports, and advanced dashboard analytics remain outside this phase.

## Production source of truth and workflow

Production Plans reference existing Supply Plans and existing Product/Product Variant records. Production Orders are created from approved or scheduled Production Plans and snapshot the active BOM version into production-order material lines. The snapshot makes the production requirement stable while retaining the active BOM source reference; it does not duplicate BOM calculation logic.

The operational workflow is:

> Confirmed Buyer Order → Supply Plan → Production Plan → Production Order → Material Availability Check → Production In Progress → Material Consumption → Production Progress → Completion Preparation → Finished Goods → Phase 7 Inventory Stock In.

A Production Plan owns one product/variant demand key and a planned quantity. A Production Order owns one plan and one product/variant, with `production_order_items` representing BOM-derived material requirements. `production_progress` records cumulative progress snapshots for traceable history. `material_consumptions` records each raw-material issue. `finished_goods` records completed output and the linked Phase 7 inventory transaction.

Production History is served from the existing immutable `audit_logs` infrastructure filtered to the production module; no duplicate production-history table is introduced.

## Minimal production tables

Seven migrations create six production-specific tables plus MySQL-only/resumable checks.

| Table | Purpose | Key controls |
| --- | --- | --- |
| `production_plans` | Production demand plan | Unique plan number; product/variant; optional Supply Plan and Buyer Order references; dates; priority; status; creator |
| `production_orders` | Executable production order and current progress state | Unique order number; plan/product/variant/BOM references; dates; issue warehouse/location; status; cumulative progress fields; creator |
| `production_order_items` | BOM-derived material requirement snapshot | Production order and BOM item references; material/unit; required and consumed quantities; unique line per BOM item |
| `production_progress` | Cumulative progress event history | Planned/completed/rejected/remaining quantities; progress percentage; production date; actor; immutable event rows |
| `material_consumptions` | Raw-material issue records | Production order/item/material/unit; quantity; inventory transaction link; unique idempotency key; actor/date |
| `finished_goods` | Completed product output and stock-in traceability | Production order/product/variant/unit; quantity; warehouse/location; inventory transaction link; unique idempotency key; actor/date |

The existing Product, Product Variant, Unit, Material, BOM, Supply Plan, Buyer Order, Warehouse, Warehouse Location, and Inventory models are reused through foreign keys and Eloquent relationships. There is no second inventory system and no production-specific product or BOM copy.

## BOM and availability calculations

Production order creation delegates to the existing `BOMCalculationService`. For each active BOM line:

> Material Requirement = BOM Quantity × Production Quantity × (1 + Wastage Percentage / 100)

The resulting required quantity is saved as the production-order material requirement snapshot. For example, 1.5 KG × 100 × 1.05 = 157.5 KG.

Before starting an order, the production service compares each remaining required material quantity with Phase 7 available inventory at the selected issue warehouse and optional location:

> Remaining Requirement = Required Quantity − Consumed Quantity
>
> Shortage Quantity = max(Remaining Requirement − Available Quantity, 0)
>
> Available Quantity = Phase 7 quantity_on_hand − quantity_reserved

The availability API returns required, consumed, remaining, available, and shortage quantities for every order item. Production start is rejected when any shortage exists unless the actor has the explicit `production.override` permission. No stock is reserved by Phase 8; the Phase 7 reserved quantity remains the authoritative reservation field.

## Material consumption and finished goods

Material consumption is atomic with the Phase 7 `InventoryService::stockOut()` call. The production service creates the consumption record and the inventory movement inside one database transaction. Every issue creates an inventory `STOCK_OUT` transaction whose source references the `MaterialConsumption` record. A required idempotency key prevents duplicate retries. Consumption cannot exceed the remaining BOM requirement without `production.override`, and Phase 7 available-stock checks still prevent negative inventory.

Completion is atomic with finished-goods creation and Phase 7 `InventoryService::stockIn()`. The output is represented as the order’s active product variant when present, otherwise the product, with the product’s master unit. The resulting inventory transaction is a `STOCK_IN` source-referenced to the Production Order/Finished Goods record. A unique idempotency key prevents duplicate output posting. Completed production is not freely editable; completed orders reject further consumption or progress edits.

## Status workflows

Production Plan statuses are `draft`, `approved`, `scheduled`, `in_progress`, `completed`, and `cancelled`. Allowed transitions are:

| Current | Allowed next status | Permission |
| --- | --- | --- |
| `draft` | `approved`, `cancelled` | `production.approve` for approval; `production.manage` for cancellation |
| `approved` | `scheduled`, `cancelled` | `production.manage` |
| `scheduled` | `in_progress`, `cancelled` | Start/order service; `production.manage` |
| `in_progress` | `completed`, `cancelled` | Completion or cancellation; `production.manage` |
| `completed`, `cancelled` | None | Immutable terminal states |

Production Order statuses are `scheduled`, `in_progress`, `completed`, and `cancelled`. An order is created only from an approved or scheduled plan; starting it requires material availability; completion requires sufficient progress/output preparation and posts finished goods. Each important transition is recorded through `AuditLogService`.

Progress is cumulative. The percentage is calculated in the service as:

> Progress % = Completed Quantity ÷ Planned Quantity × 100

Completed quantity cannot exceed planned quantity without `production.override`. Rejected quantity is retained as a placeholder for later quality integration and contributes to completion readiness but is not treated as accepted finished-goods stock.

## API and authorization surface

All production endpoints use the established `auth:sanctum`, scoped bindings, `permission:*` middleware, Form Requests, thin controllers, and API Resources. The minimum permissions are `production.view`, `production.manage`, `production.approve`, and `production.override`. Administrator authorization seeding and Sanctum ability issuance remain idempotent.

The protected `/api/production` surface covers plans, plan approval/status, orders, order status/start, material availability, consumption, progress, finished goods, and production history. Lists support search, status/product/date filters, pagination, and sorting.

## Frontend surface

`/production` will use the existing GarmentFlow shell and Axios service boundary. The page will provide Production Plans, Production Orders, order detail, material availability, progress, material consumption, finished goods, and Production History views. It will reuse master-data and planning option endpoints, show loading/error/success/validation states, expose status/product/date/search/pagination filters, and avoid adding a separate Reports module.

## Verification plan

Permanent feature tests will cover plan creation and approval, invalid transitions, production-order creation from an approved plan, active-BOM calculation including the 157.5 KG example, material availability/shortage and override authorization, start, atomic/idempotent material consumption with inventory decrease, negative-stock prevention, progress formula and authorization, completion, finished-goods inventory stock-in, duplicate output prevention, production history/audit records, permissions, migrations, and Phase 1–7 regression. Final checks will include MySQL migration status, PHP syntax, Pint, Composer validation, full Laravel tests, frontend lint/build, live API smoke, browser smoke, cleanup, and explicit confirmation that Phase 9 was not started.
