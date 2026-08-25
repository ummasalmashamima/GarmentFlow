# GarmentFlow Phase 3 — Product Engineering and BOM Design

## Scope

Phase 3 adds only Product Engineering and Bill of Materials capabilities on top of the verified Phase 1 authentication foundation and Phase 2 Product, Material, and Unit master data. It does not add MRP, demand forecasting, supply planning, procurement, inventory workflows, production, quality, sales, finance, delivery, reports, or dashboard KPI logic.

The implementation will use the existing Laravel/PHP/MySQL REST architecture, Eloquent models, Form Requests, API Resources, policies, service layer, Sanctum token abilities, React/Vite/Axios frontend, protected routes, shared layout, and existing `AuditLogService`.

## Data Model

Three BOM-specific tables are required because BOM versioning is a first-class requirement. There is no duplicate Product, Product Variant, Material, Unit, Category, or Supplier table.

| Table | Purpose | Important columns and constraints |
| --- | --- | --- |
| `bom_headers` | One engineering BOM definition for one product | `product_id` FK to `products`, unique product, unique code, name, description, `status` (`draft`, `active`, `inactive`), timestamps, soft delete |
| `bom_versions` | Immutable-ish engineering revisions under a header | `bom_header_id` FK, positive `version_number`, effective date range, status (`draft`, `active`, `inactive`), notes, unique `(bom_header_id, version_number)`, timestamps, soft delete |
| `bom_items` | Material lines belonging to a version | `bom_version_id` FK, `material_id` FK, `unit_id` FK, positive quantity, wastage percentage `0..100`, line number, notes, timestamps, unique `(bom_version_id, material_id)` and `(bom_version_id, line_number)` |

The foreign-key policy prevents broken references. Version items cascade when a version is hard-removed by the database, while product, material, and unit references use restrictive deletion. Application operations use soft deletion or status deactivation to preserve engineering history. The existing `audit_logs` table records BOM history, so a second audit/history system is not introduced.

Phase 2 models are extended with `Product::boms()`, `Material::bomItems()`, and `Unit::bomItems()`. New models are `BomHeader`, `BomVersion`, and `BomItem`, with relationships in both directions and casts for quantities, percentages, dates, and statuses.

## Business Rules

A BOM header must reference an existing active Product and has one logical definition per product. A new BOM starts in `draft` status with version 1 in `draft` status. A BOM version may contain multiple material lines, but a material may occur at most once within the same version. Each line requires an active Material, an active Unit, a quantity greater than zero, and a wastage percentage from 0 through 100.

Active BOM versions are controlled by a transaction in the BOM service. Activating a version locks the header, requires at least one item, marks the selected version and header `active`, and marks any other active version under that header `inactive`. This guarantees one active version for a product without relying on a MySQL partial unique index. Active versions are not edited directly; users create a new draft version for changes. Deactivation marks the header and active versions inactive. Deleting a header is a soft-delete/deactivation operation that keeps historical records and audit entries.

## Calculation Contract

`BOMCalculationService` owns all material requirement calculations. Controllers never calculate quantities.

> Material requirement = BOM quantity × order quantity × wastage factor
>
> Wastage factor = 1 + (wastage percentage / 100)

The calculation endpoint accepts a positive `order_quantity` and returns the selected BOM version, order quantity, each material line, wastage factor, calculated requirement, unit summary, and line count. Decimal output is rounded to four places, matching the Phase 2 product/material precision convention. For BOM quantity `1.5`, order quantity `100`, and wastage `5%`, the result is `157.5`.

## Backend Architecture

The backend uses a dedicated `BOM` domain namespace without changing existing domain structure:

- `BOMController` handles BOM header list, create, detail, update, soft delete, activate, and deactivate requests.
- `BOMVersionController` handles version list, create, detail, update, activation, and deactivation.
- `BOMItemController` handles item list, create, update, and remove.
- `BOMCalculationController` delegates preview requests to `BOMCalculationService`.
- `BOMService` owns transactions, version state transitions, relation integrity, editability checks, and audit events.
- `BOMCalculationService` owns the reusable formula and response data.
- Shared Form Requests validate headers, versions, items, and calculation input.
- Shared API Resources serialize headers, versions, items, and calculation results consistently.
- `BOMPolicy` and `bom.view` / `bom.manage` permissions reuse the existing dual Gate-plus-Sanctum middleware contract.

## REST API Surface

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/boms` | Searchable, sortable, paginated BOM headers |
| `POST` | `/api/boms` | Create a draft BOM and initial draft version |
| `GET` | `/api/boms/{bom}` | BOM detail with product, versions, and items |
| `PUT` | `/api/boms/{bom}` | Update BOM header metadata |
| `DELETE` | `/api/boms/{bom}` | Soft-delete/deactivate a BOM |
| `POST` | `/api/boms/{bom}/activate` | Activate the header’s selected active version |
| `POST` | `/api/boms/{bom}/deactivate` | Deactivate header and active versions |
| `GET` | `/api/boms/{bom}/versions` | List versions |
| `POST` | `/api/boms/{bom}/versions` | Create a draft version |
| `GET` | `/api/boms/{bom}/versions/{version}` | Version detail with items |
| `PUT` | `/api/boms/{bom}/versions/{version}` | Update draft/inactive version metadata |
| `POST` | `/api/boms/{bom}/versions/{version}/activate` | Activate one version transactionally |
| `POST` | `/api/boms/{bom}/versions/{version}/deactivate` | Deactivate one version |
| `GET` | `/api/boms/{bom}/versions/{version}/items` | List material lines |
| `POST` | `/api/boms/{bom}/versions/{version}/items` | Add a material line |
| `PUT` | `/api/boms/{bom}/versions/{version}/items/{item}` | Update a material line |
| `DELETE` | `/api/boms/{bom}/versions/{version}/items/{item}` | Remove a material line |
| `POST` | `/api/boms/{bom}/versions/{version}/calculate` | Preview material requirements |

Read endpoints require `bom.view`; mutations require `bom.manage`. All endpoints are authenticated and use the existing resource allowlisting only where applicable.

## Frontend Flow

The protected `/boms` page is a single reusable BOM management surface inside the existing `AppLayout`. It provides a paginated/searchable register, create/edit forms, detail view, version list, version creation/editing, material line management, activation/deactivation actions, calculation preview, loading/error/success feedback, validation display, and responsive tables/modals. Product, Material, and Unit selects reuse the existing `/master-data/{resource}/options` endpoints rather than duplicating master-data APIs.

The UI flow is: select Product → create draft BOM → add or select a draft version → add Material → enter quantity and Unit → enter wastage percentage → calculate preview → activate the version/BOM. Active versions display as read-only engineering history; users create another version for changes.

## Verification Plan

Verification covers migrations and schema constraints, idempotent seed/permissions, Eloquent relationships, calculation unit tests, validation tests, authorization tests, CRUD and version state APIs, duplicate-material protection, active-version exclusivity, soft deletion/deactivation, frontend lint/build, browser create/version/item/calculate/activate/deactivate flows, and Phase 1/2 regression. Phase 4 domains remain untouched.
