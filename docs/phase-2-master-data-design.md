# GarmentFlow Phase 2 — Master Data Design

This design treats the original GarmentFlow Master Instruction as authoritative and the received addendum as supplemental context. It extends the verified Phase 1 Laravel/React foundation without replacing authentication, layout, routing, or existing API behavior. Phase 3 domains are explicitly out of scope.

## Scope

Phase 2 covers Buyers, Customers, Suppliers, Categories, Products, Product Variants, Sizes, Colors, Materials, Material Categories, Units, Warehouses, and Warehouse Locations. The supplier-material association required by the addendum is modeled as a normalized pivot table and exposed through the supplier/material relationships. Audit logging is added because the Phase 2 requirements explicitly require audit logging for important master-data changes.

## Database Design

| Table | Key relationships and constraints |
| --- | --- |
| `buyers` | Unique code; contact fields; active/inactive status; soft deletes |
| `customers` | Unique code; contact and address fields; active/inactive status; soft deletes |
| `suppliers` | Unique code; contact fields; active/inactive status; soft deletes |
| `material_categories` | Unique code; active/inactive status; soft deletes |
| `units` | Unique code; symbol and decimal precision; active/inactive status; soft deletes |
| `categories` | Unique code; optional self-referencing parent; active/inactive status; soft deletes |
| `sizes` | Unique code; sort order; active/inactive status; soft deletes |
| `colors` | Unique code; optional hex code; active/inactive status; soft deletes |
| `warehouses` | Unique code; address; active/inactive status; soft deletes |
| `materials` | Unique code; material-category and unit foreign keys; active/inactive status; soft deletes |
| `products` | Unique code; category and unit foreign keys; active/inactive status; soft deletes |
| `product_variants` | Unique SKU; product, size, and color foreign keys; prices; active/inactive status; soft deletes |
| `warehouse_locations` | Unique per-warehouse code; warehouse foreign key; active/inactive status; soft deletes |
| `supplier_materials` | Unique supplier/material pair; supplier and material foreign keys; price, lead time, MOQ, preferred flag, status |
| `audit_logs` | Nullable user foreign key, module, record identity, action, old/new JSON values, indexed lookup fields |

Foreign keys use restrictive behavior where deleting a referenced record would break master-data integrity. Master-data records are soft-deleted or deactivated rather than physically removed when dependent records exist.

## Backend Architecture

A single `MasterDataRegistry` defines each module’s model, fields, searchable columns, sortable columns, relations, validation rules, and protected dependency relations. `MasterDataService` owns list filtering/search/pagination/sorting, transaction-wrapped create/update/delete/deactivate behavior, relation loading, and audit-log creation. `MasterDataController` remains thin and delegates to the service. `MasterDataRequest` obtains module-specific validation from the registry. `MasterDataResource` serializes the stable API shape from the same registry. `MasterDataPolicy` is registered through Laravel Gate, and route middleware requires both the corresponding user permission and Sanctum token ability.

The API uses the consistent REST surface `GET/POST /api/master-data/{resource}`, `GET/PUT/DELETE /api/master-data/{resource}/{id}` with pagination metadata and JSON validation/error responses. Phase 1 routes remain unchanged.

## Frontend Architecture

A single metadata-driven `masterDataModules` definition supplies labels, API resource names, columns, fields, filters, and option dependencies. One reusable `MasterDataPage` supports list, search, status filtering, pagination, create, edit, detail, delete/deactivate, loading, error, and success states for all thirteen modules. It reuses the Phase 1 `AppLayout`, Axios instance, authentication state, and existing visual language. The route table adds `/master-data` and `/master-data/:resource`; it does not duplicate per-module pages or API clients.

## Verification Boundary

Implementation and verification will cover only Phase 2 Master Data. No order, planning, procurement workflow, inventory transaction, production, quality, sales, delivery, finance, alert, approval, or dashboard KPI features will be started.
