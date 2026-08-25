# GarmentFlow Phase 3 — Product Engineering & BOM Completion Report

## Verification status

**Phase 3 is complete and verified.** The implementation continues from the verified Phase 1 authentication foundation and Phase 2 Master Data modules. The original GarmentFlow Master Instruction remained authoritative, and the supplied Phase 3 addendum was applied only to the Product Engineering and Bill of Materials scope.

**Phase 4 was not started.** No MRP, demand forecasting, supply planning, procurement, inventory workflow, production, quality, sales, finance, delivery, reporting, or dashboard KPI functionality was implemented.

## Delivered capability

GarmentFlow now supports maintainable product engineering BOM management for existing Phase 2 products, materials, and units. A BOM has one product definition, versioned engineering revisions, and material lines. Each line stores its material, unit, quantity, wastage percentage, line number, and notes.

The seeded reference graph contains one active BOM for `TEE-CLASSIC`, one active version, and one material line for `FAB-COT-001`. It is intentionally seeded through existing Phase 2 records rather than duplicating products, materials, units, categories, or variants.

## Files created

### Backend database

| File | Purpose |
| --- | --- |
| `backend/database/migrations/2026_08_23_060000_create_bom_headers_table.php` | Creates normalized BOM headers with product reference, unique code, product uniqueness, lifecycle status, timestamps, and soft deletion. |
| `backend/database/migrations/2026_08_23_060010_create_bom_versions_table.php` | Creates BOM revisions with version numbers, effective dates, lifecycle status, unique version numbers per header, timestamps, and soft deletion. |
| `backend/database/migrations/2026_08_23_060020_create_bom_items_table.php` | Creates material lines with material/unit references, decimal quantities, wastage, line ordering, duplicate-line constraints, timestamps, and indexes. |
| `backend/database/migrations/2026_08_23_060030_add_bom_check_constraints.php` | Adds MySQL checks for positive version/line numbers, positive quantities, and wastage from 0 through 100; safely skips database-specific checks under SQLite tests. |

### Backend models, authorization, services, controllers, requests, and resources

| Area | Files |
| --- | --- |
| Models | `backend/app/Models/BomHeader.php`, `BomVersion.php`, `BomItem.php` |
| Policy | `backend/app/Policies/BomPolicy.php` |
| Services | `backend/app/Services/BOM/BOMService.php`, `BOMCalculationService.php` |
| Controllers | `backend/app/Http/Controllers/BOM/BOMController.php`, `BOMVersionController.php`, `BOMItemController.php`, `BOMCalculationController.php` |
| Form Requests | `backend/app/Requests/BOM/BOMHeaderRequest.php`, `BOMVersionRequest.php`, `BOMItemRequest.php`, `BOMQueryRequest.php`, `BOMCalculateRequest.php`, `BOMActivationRequest.php` |
| API Resources | `backend/app/Resources/BOM/BomResource.php`, `BomVersionResource.php`, `BomItemResource.php` |
| Seed/Test files | `backend/database/seeders/BOMSeeder.php`, `backend/tests/Feature/BOMApiTest.php`, `backend/tests/Unit/BOMCalculationServiceTest.php` |

### Frontend and documentation

| File | Purpose |
| --- | --- |
| `frontend/src/services/bomService.js` | One Axios transport boundary for BOM headers, versions, items, lifecycle actions, and calculations. |
| `frontend/src/pages/BOM/BOMPage.jsx` | Reusable register/detail management page covering BOMs, versions, lines, relation selectors, status actions, and calculation preview. |
| `docs/phase-3-bom-design.md` | Phase 3 design record. |
| `docs/phase-3-bom-ui-verification-notes.md` | Browser smoke-test evidence and cleanup notes. |
| `docs/phase-3-bom-report.md` | This final completion report. |

## Files modified

| File | Change |
| --- | --- |
| `backend/app/Models/Product.php` | Added `boms()` relationship. |
| `backend/app/Models/Material.php` | Added `bomItems()` relationship. |
| `backend/app/Models/Unit.php` | Added `bomItems()` relationship. |
| `backend/app/Services/MasterData/MasterDataRegistry.php` | Added BOM dependencies to Product, Material, and Unit dependency metadata so existing Master Data deletion remains dependency-aware. |
| `backend/app/Providers/AppServiceProvider.php` | Registered `bom.view` and `bom.manage` gates. |
| `backend/app/Services/Auth/AuthService.php` | Included BOM abilities in token ability derivation when the authenticated user has the corresponding permissions. |
| `backend/database/seeders/AuthorizationSeeder.php` | Added idempotent `bom.view` and `bom.manage` permissions to the administrator role. |
| `backend/database/seeders/DatabaseSeeder.php` | Added `BOMSeeder` after Phase 2 Master Data seeding. |
| `backend/routes/api.php` | Added protected nested BOM, version, item, activation, deactivation, and calculation routes. |
| `frontend/src/routes/AppRoutes.jsx` | Added the protected `/boms` page route. |
| `frontend/src/layouts/AppLayout.jsx` | Added one BOM Engineering entry to the existing authenticated sidebar. |
| `frontend/src/index.css` | Added BOM-specific styles while reusing the Phase 2 design system for tables, forms, modals, status badges, feedback, and pagination. |

## Database changes and relationships

The normalized BOM schema consists of three new tables and does not duplicate any Phase 2 master-data table.

| Table | Key design |
| --- | --- |
| `bom_headers` | `product_id` references `products.id` restrictively; `code` is unique; `product_id` is unique so one logical BOM header exists per product; `status` supports `draft`, `active`, and `inactive`; soft deletion preserves the header record. |
| `bom_versions` | `bom_header_id` references `bom_headers.id`; `(bom_header_id, version_number)` is unique; effective date range and status are stored; soft deletion preserves revision history. |
| `bom_items` | `bom_version_id` references `bom_versions.id` with cascade on hard parent removal; `material_id` references `materials.id`; `unit_id` references `units.id`; `(bom_version_id, material_id)` prevents duplicate materials; `(bom_version_id, line_number)` prevents duplicate lines. |

The Eloquent graph is Product → BOM headers → BOM versions → BOM items → Material and Unit. Product, Material, and Unit expose reverse BOM relationships. The seeded and directly audited graph confirmed one product, one active BOM, one active version, one material line, and correct material/unit relations.

The final MySQL audit confirmed the three BOM tables, five BOM foreign keys, eight unique indexes including primary-key indexes, zero duplicate active versions per header, and the two BOM permissions. The four data-integrity checks are enforced by MySQL and covered by application validation for SQLite test compatibility.

## API surface

All BOM routes are authenticated with Sanctum and use the existing dual permission contract: the token must have the ability and the user must pass the corresponding Gate permission. Read operations use `bom.view`; mutation operations use `bom.manage`.

| Method | Endpoint | Behavior |
| --- | --- | --- |
| `GET` | `/api/boms` | Searchable, status-filterable, sortable, paginated BOM register. |
| `POST` | `/api/boms` | Creates a draft BOM and its initial draft version transactionally. |
| `GET` | `/api/boms/{bom}` | Returns BOM detail with Product, versions, items, Materials, and Units. |
| `PUT` | `/api/boms/{bom}` | Updates BOM header metadata. |
| `DELETE` | `/api/boms/{bom}` | Soft-deletes the header and deactivates active versions. |
| `POST` | `/api/boms/{bom}/activate` | Activates a selected or latest eligible version and its header. |
| `POST` | `/api/boms/{bom}/deactivate` | Deactivates the BOM and any active versions. |
| `GET` | `/api/boms/{bom}/versions` | Lists versions with item counts. |
| `POST` | `/api/boms/{bom}/versions` | Creates the next draft version. |
| `GET` | `/api/boms/{bom}/versions/{version}` | Returns version detail with nested material lines. |
| `PUT` | `/api/boms/{bom}/versions/{version}` | Updates draft or inactive version metadata. |
| `POST` | `/api/boms/{bom}/versions/{version}/activate` | Activates one version and deactivates any other active version. |
| `POST` | `/api/boms/{bom}/versions/{version}/deactivate` | Deactivates a version and its header when no active version remains. |
| `GET` | `/api/boms/{bom}/versions/{version}/items` | Lists material lines. |
| `POST` | `/api/boms/{bom}/versions/{version}/items` | Adds a material line. |
| `PUT` | `/api/boms/{bom}/versions/{version}/items/{item}` | Updates a material line. |
| `DELETE` | `/api/boms/{bom}/versions/{version}/items/{item}` | Removes a draft/inactive material line. |
| `POST` | `/api/boms/{bom}/versions/{version}/calculate` | Previews material requirements for an order quantity. |

Nested route model binding and service ownership checks prevent a version or item from being manipulated through the wrong parent BOM.

## Business logic and calculation service

`BOMService` contains all transactional BOM business operations. Creating a header also creates version 1 in one transaction. Version numbers are automatically incremented from existing revisions. A version must contain at least one line before activation. Active versions are read-only; users create a new draft version before changing material lines. A version can contain each material only once, and line numbers are also unique.

Activation is transactional and guarantees one active version under a BOM header by deactivating any other active version before activating the selected revision. Header and version deactivation are explicit state transitions. Header deletion is a soft-delete/deactivation operation. Item removal is a hard delete with an audit record so a removed draft line can be re-added without losing the historical event.

`BOMCalculationService` owns the reusable formula:

> Material requirement = BOM quantity × Order quantity × (1 + Wastage percentage / 100)

The required example was verified both in the unit test and live API/UI flows:

> 1.5 × 100 × 1.05 = 157.5

The calculation response includes BOM/product/version summaries, order quantity, each material line, wastage factor, calculated requirement, unit, and total line count. No controller contains calculation logic.

## Audit behavior

The existing Phase 2 `AuditLogService` is reused. BOM events record the authenticated user, module, model type, record ID, action, old values, new values, and timestamp through the existing `audit_logs` table.

Audited events include BOM creation, update, activation, deactivation, soft deletion; version creation, update, activation, and deactivation; and item creation, update, and removal. Other active versions automatically deactivated during activation are audited individually.

## Frontend implementation

The BOM UI reuses the existing Phase 1 authenticated `AppLayout`, protected routing, Axios interceptor, Master Data relation-option APIs, modal/form styles, tables, buttons, status badges, loading/error/success feedback, and responsive layout.

The `/boms` page provides a searchable and filterable BOM register with sorting and pagination. The detail modal provides product summary, revision history, version selection, material-line management, lifecycle actions, and calculation preview. Product, Material, and Unit selectors are populated from existing protected Master Data option APIs. Active revisions expose calculation and deactivation but do not expose line-edit controls; draft and inactive revisions expose add/edit/remove line controls.

The browser-verified flow was: open protected BOM Engineering; view the seeded BOM; calculate order quantity 100 and see 157.5 kg; create version 2; select relation-backed Material and Unit options; add a 1.5 quantity line with 5% wastage; activate version 2; deactivate version 2; and log out through the preserved Phase 1 header. The temporary browser-created version was removed afterward and seeded version 1 was restored as active.

## Validation and authorization

Validation covers existing active Product, Material, and Unit references; unique Product/BOM and BOM code rules; date formats and date ordering; positive quantities; wastage range `0..100`; positive line numbers; duplicate material and duplicate line protection; order quantity greater than zero; parent-child ownership; and active-version edit restrictions.

Authorization adds only the two required BOM permissions. The administrator seeder attaches `bom.view` and `bom.manage` idempotently. Login token abilities are derived from the user’s permissions, and the existing permission middleware checks both Sanctum token ability and Gate authorization. Feature tests verified that a dashboard-only token receives `403` on BOM access.

## Verification performed

| Check | Result |
| --- | --- |
| Migrations | Passed; all BOM migrations applied to live MySQL. |
| Idempotent seeds | Passed; `DatabaseSeeder` ran twice without duplicate errors, and `BOMSeeder` was rerun after cleanup. |
| Schema audit | Passed; tables, foreign keys, unique indexes, check constraints, seeded graph, and active-version exclusivity verified. |
| PHP syntax | Passed across application, routes, configuration, database, and tests. |
| Composer | `composer validate --no-check-publish` passed. |
| Laravel Pint | Passed across 111 PHP files. |
| Laravel tests | Passed: 14 tests, 111 assertions. Focused BOM/Auth/Master Data run passed: 12 tests, 109 assertions. |
| Calculation unit tests | Passed: formula example and non-positive order validation. |
| API feature tests | Passed: CRUD, item add/update/remove, versions, activation/deactivation, audit events, authorization, validation, duplicate material, and read-only active versions. |
| Frontend lint | Passed with 0 warnings and 0 errors. |
| Frontend production build | Passed; Vite transformed 96 modules. |
| Live HTTP API | Passed: 42 checks with 0 failures, including Phase 1 health/login/me/logout/token revocation, unauthorized BOM access, all 13 Phase 2 list resources, relation options, seeded and temporary calculations, CRUD, validation, version exclusivity, deactivation, and cleanup. |
| Browser smoke test | Passed: login, protected workspace, BOM navigation, register/detail, calculation preview, version creation, relation-backed item creation, activation, deactivation, and logout. |
| Maintainability audit | Passed; one shared service layer, one API transport boundary, thin controllers, no duplicate BOM services/controllers/pages/components, and no Phase 4 surfaces. |

During implementation, one calculation test bootstrap issue and one test fixture primary-key setup issue were corrected. One frontend derived-array lint warning was removed. These were verification/test integration defects, not unresolved production behavior defects.

## Cleanup and remaining issues

Temporary live API Product/BOM records were force-removed after testing, along with their verification audit rows. The seeded BOM was restored to active version 1. Temporary scripts were deleted, temporary Laravel/Vite servers were stopped, and runtime secrets/dependencies/build output are excluded from the source archive.

The current Phase 3 scope intentionally does not expose a separate pivot-attribute editor for Supplier–Material because that relationship belongs to Phase 2 Master Data and is not part of BOM management. The BOM module uses existing Product, Material, and Unit data and does not implement unit conversion, inventory availability, MRP, or downstream planning; those belong to later phases.

No unresolved Phase 3 defects remain. **Phase 4 has not started.**

## References

No external sources were used. This report is based on the authoritative GarmentFlow Master Instruction, the supplied Phase 3 addendum, the current repository source tree, migration/test output, live HTTP verification, and browser smoke-test evidence recorded in `phase-3-bom-ui-verification-notes.md`.
