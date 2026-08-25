# GarmentFlow Phase 4 — Buyer Order Management Design

## Scope

Phase 4 adds Buyer Order Management on top of the verified Phase 1 authentication and authorization foundation, Phase 2 Master Data, and Phase 3 Product Engineering/BOM. It does not implement Phase 5 or any later demand forecasting, supply planning, MRP calculation, procurement, inventory, production, quality, sales, finance, delivery, reporting, or dashboard KPI workflows.

The implementation will reuse the existing Laravel/PHP/MySQL REST architecture, Eloquent models, Form Requests, API Resources, Gate-plus-Sanctum permission middleware, service layer, React/Vite/Axios transport, protected `AppLayout`, tables, forms, modals, status badges, and the existing `AuditLogService`.

## Data Model

Five order-related tables are required. Existing Buyer, Product, Product Variant, User, BOM, and Unit records are referenced and never duplicated.

| Table | Purpose | Important columns and constraints |
| --- | --- | --- |
| `buyer_orders` | Order header and workflow state | `buyer_id`, unique `order_number`, `order_date`, `delivery_date`, `status`, `total_quantity`, `total_amount`, `remarks`, `created_by`, timestamps, soft deletion; buyer/user foreign keys and useful status/date indexes. |
| `buyer_order_items` | Product variant order lines | `buyer_order_id`, `product_id`, `product_variant_id`, positive quantity, non-negative unit price, calculated item total, timestamps; product/variant foreign keys and unique `(buyer_order_id, product_variant_id)`. |
| `order_approvals` | Approval request and decision trail | order reference, requested/reviewed users, approval status (`pending`, `approved`, `rejected`), remarks, requested/reviewed timestamps, indexes. |
| `order_status_histories` | Ordered workflow transition history | order reference, previous status, new status, changed user, remarks, timestamps; index by order/time. |
| `order_planning_inputs` | Confirmed-order handoff prepared for future Planning/MRP | unique order reference, readiness status, prepared user/time, confirmed quantity and notes; this is only a handoff record and performs no MRP calculation. |

Order headers and their children preserve historical records through soft deletion or status history. Items and child workflow records use cascading hard-delete rules only for controlled parent removal; normal application flows do not hard-delete submitted or confirmed orders.

## Workflow

The allowed business status set follows the authoritative workflow:

`draft → submitted → pending_approval → confirmed → planning → in_production → ready → shipped → delivered → completed`.

A draft can be edited. The submit operation records a `draft → submitted` history event, creates a pending approval, and moves the order to `pending_approval` in the same transaction so the approval gate is explicit. Approval changes `pending_approval → submitted`, records the approval decision, and permits confirmation. Rejection changes `pending_approval → draft`, records the rejected approval, and requires resubmission.

Confirmation is a dedicated authorized operation. It validates the buyer, every line, product/variant ownership, positive quantities, delivery date, and totals; moves `submitted → confirmed`; creates exactly one ready `order_planning_inputs` handoff record; records status history; and writes audit events in one transaction. It does not calculate MRP, reserve inventory, create purchase requisitions, or create purchase orders.

After confirmation, a reusable status-transition operation supports only the next valid downstream status. Completed orders cannot be edited. Invalid transitions and attempts to use generic status changes for approval/confirmation are rejected with clear validation errors.

## Totals and Calculation

`BuyerOrderService` owns total calculation and all order mutations. Each line total is `quantity × unit_price`; the order total is the sum of line totals, and total quantity is the sum of line quantities. Values are rounded to four decimal places for API responses and stored in decimal database columns. A preview endpoint uses the same service method before persistence so React never contains authoritative calculation logic.

## Backend Architecture

The backend adds an `Orders` domain namespace without changing existing domains:

- `BuyerOrderController` handles list, create, detail, draft update, delete, preview, submit, approve, reject, confirm, generic status transition, and history.
- `BuyerOrderItemController` handles nested item list, add, update, and remove for editable drafts.
- `BuyerOrderService` owns transactions, validation of cross-model ownership, totals, order numbers, workflow transitions, approval records, planning handoffs, and audit/status history.
- Shared Form Requests validate header, nested item, query, preview, approval, and transition payloads.
- Shared API Resources serialize order headers, items, approvals, planning handoffs, status history, and totals consistently.
- `BuyerOrderPolicy` provides `buyer-order.view`, `buyer-order.manage`, `buyer-order.approve`, and `buyer-order.confirm` checks using the existing user permission helper.
- The existing `AuditLogService` is reused for order create/update/submit/approve/reject/confirm/status/delete events. `order_status_histories` stores the domain-specific transition record required for the history view.

## Authorization

The administrator seeder adds four idempotent permissions: `buyer-order.view`, `buyer-order.manage`, `buyer-order.approve`, and `buyer-order.confirm`. AuthService includes these abilities in Sanctum tokens when the user has the permission. Read routes require `buyer-order.view`; draft/item mutations and submission require `buyer-order.manage`; approval/rejection requires `buyer-order.approve`; confirmation requires `buyer-order.confirm`.

## API Surface

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/buyer-orders` | Search, filter, sort, and paginate order list. |
| `POST` | `/api/buyer-orders` | Create a draft order with initial item lines transactionally. |
| `POST` | `/api/buyer-orders/preview` | Calculate draft totals without persistence. |
| `GET` | `/api/buyer-orders/{order}` | Detail with items, approval, planning input, and status history. |
| `PUT` | `/api/buyer-orders/{order}` | Update an editable draft header. |
| `DELETE` | `/api/buyer-orders/{order}` | Soft-delete an editable draft only. |
| `POST` | `/api/buyer-orders/{order}/submit` | Submit draft for approval. |
| `POST` | `/api/buyer-orders/{order}/approve` | Approve a pending order. |
| `POST` | `/api/buyer-orders/{order}/reject` | Reject a pending order back to draft. |
| `POST` | `/api/buyer-orders/{order}/confirm` | Confirm an approved order and create planning input. |
| `POST` | `/api/buyer-orders/{order}/status` | Advance one valid downstream status. |
| `GET` | `/api/buyer-orders/{order}/history` | Return status and audit history entries. |
| `GET` | `/api/buyer-orders/{order}/items` | List order items. |
| `POST` | `/api/buyer-orders/{order}/items` | Add a line to an editable draft. |
| `PUT` | `/api/buyer-orders/{order}/items/{item}` | Update a line in an editable draft. |
| `DELETE` | `/api/buyer-orders/{order}/items/{item}` | Remove a line from an editable draft. |

Query filters include search, status, buyer, order-date range, delivery-date range, pagination, and allowlisted sorting. Nested route binding and service checks prevent an item from being changed through the wrong order.

## Frontend Flow

The protected `/buyer-orders` route adds one sidebar entry to the existing `AppLayout`. `BuyerOrderPage` is a reusable register/detail experience with loading, error, success, validation, search, filters, pagination, sorting, create/edit draft modal, dynamic item lines, backend total preview, order details, approval actions, status progression, planning-input visibility, and status/audit history.

The create flow is: select Buyer → add Product and Product Variant lines → enter quantity and unit price → set delivery date → request backend preview → save draft. Draft detail exposes edit and item controls. Pending approval detail exposes approve/reject. Approved submitted detail exposes confirm. Confirmed and later statuses expose only their next valid transition. Completed orders are read-only.

Existing protected Master Data option endpoints populate Buyer, Product, and Product Variant selectors. No duplicate master-data transport or layout components are added.

## Verification Plan

Verification covers migrations and schema constraints, idempotent seed/permission setup, Eloquent relationships, exact total calculation, cross-model ownership validation, draft CRUD, item CRUD, submit/approve/reject/confirm workflow, invalid transition rejection, approval authorization, planning-input creation, status history, audit logging, query filters/pagination, Phase 1–3 regression, frontend lint/build, browser create/edit/preview/submit/approve/confirm/history/status flows, and cleanup of temporary test records.
