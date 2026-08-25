# GarmentFlow Phase 6 Procurement Management Design

## Scope and boundary

Phase 6 adds Procurement Management only. It reuses the verified Supplier, Material, Unit, Warehouse, User, Role, Permission, Audit Log, and Phase 5 Material Requirement structures. It does not duplicate master-data tables and does not implement Inventory transactions, Production, Quality Control, Sales, Finance, Delivery, Reports, or the Phase 7 domain.

The implemented procurement chain is:

```text
Material Requirement → Purchase Requisition → PR Approval → Purchase Order
→ PO Approval → Supplier → Goods Receipt → Warehouse integration point
```

Goods Receipt records capture accepted and rejected quantities but do not post stock movements. Accepted quantities remain procurement data until a future Inventory module exists.

## Procurement tables

The design uses six primary document/item tables plus two workflow support tables. `purchase_requisitions` and `purchase_requisition_items` capture requested materials, quantities, source MRP lines, and partial conversion quantities. `purchase_orders` and `purchase_order_items` capture supplier commitments, prices, terms, line totals, header tax/discount, receipt progress, and PR-item traceability. `goods_receipts` and `goods_receipt_items` capture warehouse receipts and acceptance/rejection outcomes. `purchase_approvals` records PR and PO approval requests/reviews, while `procurement_status_histories` records every document status transition.

No Supplier, Material, Unit, Warehouse, or Warehouse Location table is created. Supplier-material pricing and lead-time relationships continue to use the existing `supplier_materials` pivot.

## Status transitions

Purchase Requisition transitions are `draft → submitted → approved → converted_to_po`, with `submitted → rejected` as the rejection branch. Purchase Order transitions are `draft → submitted → approved → sent_to_supplier → partially_received → fully_received → closed`; cancellation is allowed from draft, submitted, approved, and sent-to-supplier states, but not after receipt has begun. Goods Receipt transitions are `draft → received → accepted → posted`.

Every transition is service-owned, validated against the current status, executed transactionally where it changes approval or receipt state, written to `procurement_status_histories`, and mirrored in the existing `audit_logs` architecture.

## Calculation and quantity rules

Purchase Order line total is:

```text
line_total = quantity × unit_price
```

Purchase Order totals are:

```text
subtotal = sum(line_total)
total_amount = subtotal + tax_total - discount_total
```

All quantities and monetary values are validated as non-negative with document quantities strictly positive. Requisition conversion is partial: each requisition item tracks `converted_quantity`, and a new PO may convert only the remaining quantity. Only an approved PR can be converted.

Goods Receipt validates:

```text
received_quantity = accepted_quantity + rejected_quantity
```

The received quantity cannot exceed the PO item’s remaining quantity. Multiple receipts are supported. PO receipt progress is recalculated from accepted plus rejected quantities: `partially_received` while some quantity remains, `fully_received` when all ordered quantities are received, and `closed` only through an explicit valid transition after full receipt.

## API and UI architecture

Controllers remain thin and delegate to Procurement services. Form Requests authorize and validate payloads. API Resources serialize document, item, approval, history, supplier, material, unit, and warehouse relationships. Protected Procurement routes use Sanctum plus `procurement.view`, `procurement.manage`, and `procurement.approve` permissions.

The frontend adds one protected Procurement page and one Axios service boundary. The page uses the established application shell and transactional-page conventions: tabs for requisitions, purchase orders, goods receipts, and history; search/filter/pagination; modal forms; status badges; loading/error/success feedback; detail views; approval actions; partial receipt support; and no inventory-posting UI.

## Transaction boundaries

The following operations use database transactions: PR create/update/submit/approve/reject, PR-to-PO conversion, PO create/submit/approve/cancel/status transitions, goods receipt create/receive/accept/post, and receipt-progress recalculation. Every mutation records an audit entry and, where applicable, a status-history or approval record.
