# GarmentFlow Phase 10 — Delivery & Shipment Tracking Design

## Scope

Phase 10 adds the delivery execution and shipment tracking layer on top of the verified Phase 9 Sales Orders and Phase 7 Inventory architecture. It creates only delivery-specific structures and reuses existing Sales Orders, Sales Order Items, Buyers, Customers, Products, Product Variants, Units, Warehouses, Users, Audit Logs, and InventoryService. Phase 11 Finance, invoices, payments, profit, and reporting remain outside scope.

## Delivery data model

The implementation will add only three tables:

- `deliveries`: delivery header linked to a confirmed Sales Order, optional Buyer/Customer through the Sales Order, dispatch warehouse, status, delivery number, dates, address/contact, tracking summary, remarks, and cumulative quantities.
- `delivery_items`: delivery lines linked to a Delivery and Sales Order Item, with product/variant/unit identity, requested and dispatched quantities, delivered/remaining quantities, and line remarks.
- `delivery_tracking_histories`: immutable tracking/status events with previous status, new status, carrier/tracking fields, actor, timestamp, and remarks.

The Delivery model owns many Delivery Items and Tracking History rows. Delivery Items reference the source Sales Order Item and canonical product/variant/unit identity. Sales Order receives only the necessary reverse Delivery relationship; its existing ordered, confirmed, delivered, and remaining quantity fields remain the source of truth for commercial progress.

## Workflow and invariants

Delivery statuses are centralized in `DeliveryWorkflow`:

`created → ready_for_shipment → shipped → in_transit → out_for_delivery → delivered → completed`

The workflow also permits `cancelled`, `failed`, and `returned` through controlled transitions. Only a confirmed Sales Order can create a Delivery. A Delivery may be partially dispatched and partially delivered, but the sum of Delivery quantities across all non-cancelled deliveries cannot exceed each Sales Order Item's remaining quantity.

Completed and cancelled Sales Orders cannot receive new Deliveries. A Delivery in a terminal state cannot be edited or dispatched again. Dispatch is the only operation that deducts finished goods from Inventory. Delivery creation and status/tracking updates do not mutate stock.

## Sales progress integration

Delivery creation validates the source Sales Order status and loads confirmed Sales Order Items. Dispatch locks the source Sales Order and relevant Delivery Items, calculates the cumulative dispatched quantity across Deliveries, and updates each Sales Order Item's delivered quantity and remaining quantity. It then recalculates the Sales Order delivered and remaining quantities. When the full confirmed quantity is delivered, the Sales Order transitions through its existing Sales workflow to `delivered`; a final completion action transitions it to `completed`. Partial dispatch leaves the Sales Order open with a reduced remaining quantity.

## Inventory integration

Delivery dispatch calls only `InventoryService::stockOut()` for each Delivery Item. It passes canonical product/variant/unit/warehouse identity, a reference to `DeliveryItem`, a deterministic idempotency key such as `delivery-item:{id}:dispatch`, and a dispatch remark. Existing InventoryService logic provides stock identity validation, negative-stock prevention, transaction creation, inventory balance updates, audit logs, and duplicate idempotency handling. No new inventory table, balance mechanism, or transaction type is added.

The DeliveryService wraps delivery item dispatch and Sales progress updates in one database transaction. If any line lacks available stock, the entire dispatch is rolled back. Repeating dispatch for the same Delivery is rejected before mutation; repeating a line-level request with the same idempotency key cannot create a second stock-out.

## Authorization

The Phase 10 authorization catalog adds separate `delivery.view`, `delivery.manage`, `delivery.dispatch`, and `delivery.override` abilities. The Administrator receives these permissions idempotently. `delivery.override` is reserved for explicit future exception handling and is not used to bypass ordinary quantity or negative-stock controls in the initial workflow. Routes require Sanctum authentication, permission middleware, and registered DeliveryPolicy gates.

## API shape

The protected `/api/deliveries` route group will expose:

- `GET /api/deliveries` for searchable, filterable, paginated delivery lists.
- `POST /api/deliveries` to create a Delivery only from a confirmed Sales Order.
- `GET /api/deliveries/{delivery}` for detail, items, source Sales Order, dispatch state, and tracking history.
- `PUT /api/deliveries/{delivery}` for pre-dispatch editable delivery fields.
- `POST /api/deliveries/{delivery}/dispatch` for atomic InventoryService stock-out and Sales progress update.
- `POST /api/deliveries/{delivery}/status` for valid non-dispatch delivery status transitions.
- `POST /api/deliveries/{delivery}/tracking` for shipment tracking/status events.
- `POST /api/deliveries/{delivery}/complete` for final completion after delivery.
- `GET /api/deliveries/{delivery}/history` for Delivery audit history.
- `GET /api/deliveries/{delivery}/tracking-history` for immutable tracking events.
- `GET /api/delivery-history` for searchable, paginated audit-backed Delivery History.

## Frontend

The protected `/deliveries` workspace will reuse the Sales/Production table, filter, form, modal, status badge, pagination, loading, error, and success patterns. It will provide Delivery List, Create Delivery, Delivery Details, Finished Goods/dispatch controls, Shipment Tracking, Delivery Status Update, Delivery History, and Tracking History. The UI will show source Sales Order linkage, line quantities, partial-delivery remaining quantities, dispatch state, tracking number/carrier, status badges, and explicit validation errors.

## Verification plan

Verification will cover migration order and constraints, Delivery creation from confirmed Sales Orders, rejection from non-confirmed/cancelled/completed orders, partial delivery quantity enforcement, atomic dispatch, canonical InventoryService stock-out, transaction and audit creation, duplicate-dispatch prevention, tracking history, valid/invalid status transitions, final Sales delivery progress, authorization, PHP syntax, Pint, Composer, frontend lint/build, browser smoke, and the full Phase 1–9 regression suite. Temporary live smoke records will be cleaned by exact identifiers after verification.
