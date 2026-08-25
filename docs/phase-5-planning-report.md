# GarmentFlow Phase 5 — Planning & Demand Forecasting Report

**Project:** GarmentFlow  
**Phase:** 5 — Planning & Demand Forecasting  
**Status:** **Verified and complete**  
**Author:** Manus AI  
**Verification date:** 23 August 2026

## Executive conclusion

Phase 5 — Planning & Demand Forecasting has been completed from the interrupted project state and verified without restarting or overwriting completed work. The implementation adds demand forecasting, supply planning, BOM-integrated material requirements planning, material aggregation, optional net-requirement calculation, traceable source rows, protected APIs, frontend workflows, validation, authorization, tests, and audit logging.

The verified Phase 1–4 functionality remains intact. The seeded Buyer Order `BO-20260101-0001` remains a draft, the seeded active BOM remains at version `v1`, and the existing BOM calculation still returns `157.5` for `1.5 × 100 × 1.05`. No Phase 6 feature was started.

## Interrupted-state inspection

The project was inspected before resumption. The Laravel backend, React/Vite frontend, live MySQL migration ledger, route table, source tree, and test inventory showed that **no Phase 5 implementation had been completed before the interruption**. There were no forecast, supply-plan, MRP, or material-requirement migrations, tables, models, controllers, services, routes, frontend pages, or Phase 5 tests. The only planning-related record was the already verified Phase 4 `order_planning_inputs` confirmation handoff.

| Area | State before resumption | Result after inspection |
|---|---|---|
| Demand Forecast | No implementation | Implemented and verified |
| Supply Planning | No implementation | Implemented and verified |
| MRP/material requirements | No implementation | Implemented and verified |
| Phase 5 migrations | No migration entries or tables | Seven migrations applied |
| Phase 5 APIs | No routes | Fifteen protected routes |
| Phase 5 frontend | No page or transport service | Planning page and Axios service added |
| Phase 5 tests | No tests | Four feature tests with 72 focused assertions |
| Incomplete/broken interrupted files | None found | No interrupted Phase 5 file had to be recovered |
| Phase 6 domains | Absent | Remain absent |

## Design and architectural decisions

Phase 5 follows the existing Laravel domain architecture. Controllers remain thin, business rules are centralized in services, request validation is implemented through Form Requests, API output uses Resources, authorization uses Gates/policies plus the existing token-ability middleware, and all multi-step persistence uses database transactions. Existing Product, Product Variant, Material, Unit, BOM, Buyer Order, user, audit, and Master Data services are reused rather than duplicated.

The forecast implementation deliberately supports two transparent methods: `manual` and `historical_average`. It makes no AI/ML claim. Historical averages use comparable periods and the canonical firm-demand statuses from Buyer Order workflow. Supply planning combines confirmed/downstream firm demand with active forecasts. MRP uses the active BOM calculation primitive, applies BOM wastage, aggregates identical Material/Unit pairs, and optionally computes net requirements only when availability values are explicitly supplied.

## Files created

### Backend database migrations

| File | Purpose |
|---|---|
| `backend/database/migrations/2026_08_23_080000_create_demand_forecasts_table.php` | Demand Forecast records, periods, methods, quantities, quality metadata, and calculation snapshots |
| `backend/database/migrations/2026_08_23_080010_create_supply_plans_table.php` | Product-period Supply Plan records and demand/availability/production quantities |
| `backend/database/migrations/2026_08_23_080020_create_mrp_runs_table.php` | Reproducible MRP run headers |
| `backend/database/migrations/2026_08_23_080030_create_material_requirements_table.php` | Aggregated MRP Material/Unit requirement rows |
| `backend/database/migrations/2026_08_23_080040_create_material_requirement_sources_table.php` | Traceability from aggregated requirements to Supply Plans and BOM lines |
| `backend/database/migrations/2026_08_23_080050_add_planning_check_constraints.php` | MySQL quantity, confidence, accuracy, and lookback constraints |
| `backend/database/migrations/2026_08_23_080060_add_material_unit_to_requirement_sources.php` | Forward corrective migration adding direct source Material and Unit foreign keys |

### Backend models, policy, requests, resources, controllers, and services

| Area | Files |
|---|---|
| Models | `DemandForecast.php`, `SupplyPlan.php`, `MrpRun.php`, `MaterialRequirement.php`, `MaterialRequirementSource.php` |
| Policy | `backend/app/Policies/PlanningPolicy.php` |
| Requests | `backend/app/Requests/Planning/PlanningRules.php`, `DemandForecastRequest.php`, `DemandForecastPreviewRequest.php`, `DemandForecastQueryRequest.php`, `DemandForecastActivateRequest.php`, `SupplyPlanRequest.php`, `SupplyPlanPreviewRequest.php`, `SupplyPlanGenerateRequest.php`, `SupplyPlanQueryRequest.php`, `SupplyPlanRecalculateRequest.php`, `MaterialRequirementRequest.php`, `MaterialRequirementPreviewRequest.php`, and `MaterialRequirementQueryRequest.php` |
| Resources | `DemandForecastResource.php`, `SupplyPlanResource.php`, `MrpRunResource.php`, `MaterialRequirementResource.php`, and `MaterialRequirementSourceResource.php` |
| Controllers | `DemandForecastController.php`, `SupplyPlanController.php`, and `MaterialRequirementController.php` |
| Services | `PlanningPeriodService.php`, `ForecastCalculationService.php`, `ForecastService.php`, `SupplyPlanningCalculationService.php`, `SupplyPlanningService.php`, `MaterialRequirementCalculationService.php`, and `MaterialRequirementService.php` |
| Tests | `backend/tests/Feature/PlanningApiTest.php` |

### Frontend and documentation

| File | Purpose |
|---|---|
| `frontend/src/services/planningService.js` | Single Axios transport boundary for forecast, supply, and MRP APIs |
| `frontend/src/pages/Planning/PlanningPage.jsx` | Protected three-tab Planning workflow page |
| `docs/phase-5-planning-design.md` | Phase 5 design and bounded planning/MRP boundary |
| `docs/phase-5-planning-browser-verification-notes.md` | Final browser evidence and regression notes |
| `docs/phase-5-planning-report.md` | This completion report |

## Existing files modified

Phase 5 extended, but did not replace, the verified architecture in the following files:

| File | Modification |
|---|---|
| `backend/app/Models/Product.php` | Forecast, Supply Plan, and MRP source reverse relations |
| `backend/app/Models/ProductVariant.php` | Forecast, Supply Plan, and MRP source reverse relations |
| `backend/app/Models/Material.php` | MRP requirement and source reverse relations |
| `backend/app/Models/Unit.php` | MRP requirement and source reverse relations |
| `backend/app/Models/BomVersion.php` | MRP source reverse relation |
| `backend/app/Models/BomItem.php` | MRP source reverse relation |
| `backend/app/Models/User.php` | Forecast, Supply Plan, and MRP creator relations |
| `backend/app/Services/Orders/BuyerOrderWorkflow.php` | Reusable Phase 5 firm-demand status set |
| `backend/app/Providers/AppServiceProvider.php` | Planning Gates |
| `backend/app/Services/Auth/AuthService.php` | Planning Sanctum ability derivation |
| `backend/database/seeders/AuthorizationSeeder.php` | `planning.view` and `planning.manage` permissions |
| `backend/routes/api.php` | Protected Planning route group |
| `frontend/src/routes/AppRoutes.jsx` | Protected `/planning` route |
| `frontend/src/layouts/AppLayout.jsx` | Planning sidebar entry |
| `frontend/src/index.css` | Planning tabs, cards, tables, status, modal, and responsive styles |

`DatabaseSeeder.php` was intentionally not extended with synthetic Phase 5 planning data. The Planning tables are restored empty after verification, while the existing Phase 1–4 seed graph remains canonical and idempotent.

## Database changes and verification

All seven Phase 5 migrations are applied to live MySQL. The schema contains five normalized Phase 5 tables: `demand_forecasts`, `supply_plans`, `mrp_runs`, `material_requirements`, and `material_requirement_sources`.

The final live audit verified all expected foreign keys, including Product, Product Variant, User, MRP run, Material, Unit, Supply Plan, BOM Version, and BOM Item references. It also verified four named unique indexes and nineteen MySQL check constraints for non-negative quantities, valid confidence/accuracy ranges, and positive forecast lookback periods.

| Database audit | Result |
|---|---:|
| Phase 5 tables exist | PASS |
| Expected foreign-key checks | 18/18 PASS |
| Named unique indexes | 4/4 PASS |
| Check constraints | 19/19 PASS |
| Temporary forecast rows after cleanup | 0 |
| Temporary Supply Plan rows after cleanup | 0 |
| Temporary MRP/requirement/source rows after cleanup | 0 |
| Temporary Buyer Order prefixes | 0 |
| Seeded Buyer Order remains draft | PASS |
| Phase 3 BOM graph remains active | PASS |
| Administrator planning permissions | PASS |

The final Eloquent graph audit confirmed Buyer-to-Order, Order-to-Item/Product/Variant, approval, status-history, and planning-input relations. It also confirmed Product-to-BOM, active BOM Version-to-BOM Item, Material, and Unit relations. The direct source-level Material and Unit traceability added by the corrective migration is now persisted and serialized by the MRP API.

## APIs

All Phase 5 routes are under `/api/planning`, require Sanctum authentication, and require the appropriate planning permission through the existing custom middleware. The route table contains fifteen endpoints.

| Domain | Endpoints |
|---|---|
| Demand Forecast | `GET /forecasts`, `POST /forecasts/preview`, `POST /forecasts`, `GET /forecasts/{forecast}`, `PUT /forecasts/{forecast}`, `POST /forecasts/{forecast}/activate` |
| Supply Planning | `GET /supply-plans`, `POST /supply-plans/preview`, `POST /supply-plans/generate`, `GET /supply-plans/{supplyPlan}`, `POST /supply-plans/{supplyPlan}/recalculate` |
| Material Requirements | `GET /material-requirements`, `POST /material-requirements/preview`, `POST /material-requirements/generate`, `GET /material-requirements/{run}` |

The APIs return Laravel resource envelopes and preserve collection `data`, `links`, and `meta` fields for frontend pagination. Create endpoints use HTTP 201 where a new persisted Forecast or MRP run is created; batch Supply Plan generation returns the generated collection.

## Business logic

### Demand Forecast

`PlanningPeriodService` normalizes weekly, monthly, and quarterly periods and validates aligned boundaries. `ForecastCalculationService` calculates either a non-negative manual quantity or a transparent historical average over comparable firm-demand periods. `ForecastService` owns Product/Variant validation, uniqueness, draft creation/update rules, activation, calculation snapshots, pagination, transactions, and audit logging.

Historical-average demand reads only the canonical firm-demand statuses: `confirmed`, `planning`, `in_production`, `ready`, `shipped`, `delivered`, and `completed`. Forecast records are unique by Product, optional Product Variant, period type, and period start. Only draft forecasts can be edited or activated.

### Supply Planning

`SupplyPlanningCalculationService` validates active Product and Product Variant ownership, sums firm Buyer Order quantity by delivery period, sums active forecast quantity, and calculates:

> **Required quantity = confirmed firm-order quantity + active forecast quantity**
>
> **Planned production quantity = required quantity** when availability is unknown; otherwise `max(required quantity − available quantity, 0)`.

`SupplyPlanningService` provides preview, generation/upsert, pagination, detail, optional availability recalculation, transactions, and audit logging. Product-level plans include active forecasts across Product Variants; Variant-specific plans remain filtered to the selected Variant. No production record or inventory transaction is created.

### BOM integration and MRP

`MaterialRequirementCalculationService` loads each selected Supply Plan and its Product's active BOM Version. It reuses the Phase 3 `BOMCalculationService`, including the wastage formula:

> **Gross material quantity = planned product quantity × BOM quantity × (1 + wastage percentage / 100)**

Identical Material/Unit pairs are aggregated across selected Supply Plans, and each aggregate retains source rows containing Supply Plan, Product, Product Variant, BOM Version, BOM Item, Material, Unit, planned product quantity, BOM quantity, wastage, and gross quantity.

Inventory values are optional inputs only. If a material availability row is absent, its available and net quantities remain unknown and its status is `pending_inventory`. When supplied, net quantity is calculated as:

> **Net requirement = max(gross quantity − available quantity − allocated quantity, 0)**

`MaterialRequirementService` creates reproducible MRP runs transactionally, assigns run numbers, persists aggregate and source rows, loads traceability in detail responses, and records audit events. It does not implement inventory balances, procurement, production, or MRP execution beyond the bounded calculation and handoff in Phase 5.

## Authorization and validation

Two canonical permissions were added: `planning.view` for reading registers, details, and previews, and `planning.manage` for creating, updating, activating forecasts, generating/recalculating Supply Plans, and generating MRP runs. Administrator roles receive both permissions through the existing idempotent `AuthorizationSeeder`. Sanctum token ability derivation and Gate registration were extended through the existing Phase 1 architecture.

Form Requests validate period type and date boundaries, active Product/Product Variant relationships are revalidated in services, manual forecasts require a quantity, quantities cannot be negative, quality scores are bounded from 0 to 100, availability cannot be negative, selected MRP Supply Plan IDs must be distinct and existing, and all multi-step writes use transactions.

## Frontend

The protected `/planning` page uses one shared React page with three tabs. Demand Forecast supports historical-average/manual selection, server preview, draft creation, register filtering, detail evidence, and activation. Supply Planning supports Product/Variant and period selection, optional availability, server preview, generation, register/detail views, and availability recalculation. Material Requirements supports planning-date selection, Supply Plan selection, BOM-driven preview, MRP generation, run filtering, and aggregated material detail.

The page reuses the existing AppLayout, sidebar, form fields, tables, status pills, modal patterns, loading states, feedback messages, pagination, and Axios service architecture. No duplicate authentication, layout, or transport layer was introduced.

## Problems found and fixed during resumption

| Problem | Resolution |
|---|---|
| The first live migration attempt exceeded MySQL's identifier-length limit on generated unique index names | Named Phase 5 indexes explicitly with compact stable names; removed only the unrecorded incomplete table from the failed attempt; reran successfully |
| Direct `material_id` and `unit_id` fields were missing from the initial source migration while models/services expected direct traceability | Added forward migration `080060`, source model relations/fillable fields, source payload fields, detail eager loads, resource fields, and regression assertions |
| Planning collection responses were unwrapped as arrays in the frontend, hiding Laravel pagination metadata and making a saved forecast register appear empty | Updated `planningService.js` list methods to preserve collection envelopes |
| Product-level Supply Planning excluded active Variant forecasts | Updated the calculation query so all-variant plans aggregate active forecasts across Variants and added a regression assertion |
| Partial MRP availability could be mistaken for zero stock | Missing material availability remains unknown; net quantity is calculated only for explicitly supplied availability |

All fixes were limited to Phase 5 surfaces or supporting Phase 5 integration points. Verified Phase 1–4 business behavior was not rewritten.

## Verification results

| Check | Result |
|---|---:|
| Focused Phase 5 feature tests | 4 passed, 72 assertions |
| Final full Laravel suite, including Phase 1–4 regressions | 24 passed, 259 assertions |
| PHP syntax check across backend application, migrations, routes, and tests | PASS |
| `composer validate --no-check-publish` | PASS |
| Laravel Pint style test | PASS, 190 files checked |
| `php artisan migrate:status` | PASS, all migrations applied |
| `php artisan route:list --path=api` | PASS, 15 Phase 5 routes present |
| Frontend `npm run lint` | PASS, 0 warnings and 0 errors |
| Frontend `npm run build` | PASS, 100 Vite modules transformed |
| Live API workflow | PASS: authentication, unauthorized access, options, forecast, supply, MRP, BOM regression, filters, logout/revocation |
| Live MySQL schema/Eloquent audit | PASS: all checks true |
| Browser Planning workflow | PASS: protected navigation, forecast preview/save/activate, supply preview/generate/detail, MRP preview/generate/detail, logout |
| Buyer Orders browser regression | PASS: seeded reference order remains draft |
| BOM browser regression | PASS: active v1 and 157.5 kg calculation |
| Temporary live-data cleanup | PASS |
| DatabaseSeeder run twice | PASS, idempotent |

The complete browser evidence is documented in [`phase-5-planning-browser-verification-notes.md`](phase-5-planning-browser-verification-notes.md).

## Already completed before interruption versus newly completed after resumption

Before resumption, Phase 5 had no completed or partial implementation surface. Phase 4 and its planning-input handoff were already complete and were preserved.

After resumption, all approved Phase 5 surfaces were completed: schema, migrations, models, relationships, forecast calculations and persistence, Supply Planning calculations and persistence, BOM/MRP explosion, material aggregation, optional net requirements, source traceability, APIs, authorization, frontend page, validation, tests, live API verification, browser verification, database audit, cleanup, and final quality gate.

## Remaining issues and explicit boundary

The following are intentionally outside this Phase 5 implementation: inventory balance integration, procurement, purchase orders, production scheduling/execution, shop-floor operations, quality, sales, finance, delivery, reporting domains, and Phase 6 behavior. Availability is accepted only as an explicit calculation input and is not persisted as an inventory system. Forecasting is transparent manual/historical-average logic and is not an AI/ML forecasting platform.

**Phase 6 was not started.** No Phase 6 tables, routes, controllers, services, frontend pages, or domain models were created.

## Final status

**Phase 5 — Planning & Demand Forecasting is complete and verified.** The verified source and documentation were cleaned of temporary verification scripts and runtime artifacts. The delivery archive is `/home/ubuntu/GarmentFlow-phase5-planning-demand-forecasting.zip`; its integrity check passed, and its manifest excludes backend dependencies, frontend dependencies, frontend build output, environment files, logs, Git metadata, and framework runtime caches while retaining the verified source and Phase 5 documentation.

## References

[1]: phase-5-planning-design.md "GarmentFlow Phase 5 Planning Design"
[2]: phase-5-planning-browser-verification-notes.md "GarmentFlow Phase 5 Browser Verification Notes"
