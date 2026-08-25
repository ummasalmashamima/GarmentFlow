# GarmentFlow Phase 5 — Planning & Demand Forecasting Design

## Scope and interruption assessment

The exact resumed project contains the verified Phase 1–4 Laravel/PHP/MySQL REST API and React/Vite frontend. No Phase 5 forecast, supply-plan, material-requirement, MRP migration, model, controller, service, request, resource, route, frontend page, or Phase 5 test was present. The only planning-related records are the Phase 4 confirmation handoff `order_planning_inputs` and its existing resource/relation; that handoff remains unchanged and is not an MRP implementation.

This design adds only Phase 5 Planning & Demand Forecasting. It preserves the existing Buyer Order, BOM, Master Data, authorization, audit, API resource, Axios, AppLayout, and protected-route architecture. It does not add procurement, inventory transactions, production, quality, sales, finance, delivery, reporting, or the Phase 6 scope.

## Planning flow

The Phase 5 flow is:

`Confirmed Buyer Orders → Historical-Average Demand Forecast → Supply Plan → Active BOM Explosion → Aggregated Material Requirements → Optional externally supplied Availability/Allocation → Net Requirement`

Only firm Buyer Order statuses are included in demand calculations: `confirmed`, `planning`, `in_production`, `ready`, `shipped`, `delivered`, and `completed`. Draft, submitted, and pending-approval orders are excluded. Buyer Order delivery dates determine the planning-period demand window.

## Forecasting

`DemandForecast` stores Product, an optional Product Variant, period type (`weekly`, `monthly`, or `quarterly`), period start/end, forecast quantity, method, status, forecast date, optional confidence/accuracy fields, lookback period count, creator, and notes. The implemented methods are the transparent `historical_average` baseline and an explicitly entered `manual` method for deterministic planning scenarios. Historical average sums firm order quantities in each of the preceding comparable periods and averages those period totals; manual forecasts use the user-supplied non-negative quantity. No AI/ML claim is made.

Forecast records are created by a service. The API supports preview, creation, listing/filtering, detail, update while draft, and activation. Period boundaries are explicit and validated against the selected period type. Forecast quantities are non-negative, and Product Variant/Product ownership is validated through the existing Master Data records.

## Supply planning

`SupplyPlan` stores Product, optional Product Variant, period, confirmed order quantity, forecast quantity, required quantity, nullable available quantity, planned production quantity, status, creator, and notes. Preview and generation group firm Buyer Order items by Product/Product Variant using delivery date, then add active forecasts for the same Product/Product Variant and exact planning period.

The calculation is:

`Required Quantity = Confirmed Order Quantity + Forecast Quantity`

When an integrating caller supplies actual availability:

`Planned Production Quantity = max(Required Quantity − Available Quantity, 0)`

When availability is unavailable because Inventory is not implemented, the stored availability remains `null` and planned production quantity is a provisional requirement equal to required quantity. The response marks availability as unavailable; it does not invent a stock value or create a production record. Supply plans are generated transactionally and can be recalculated without creating downstream records.

## MRP and material requirements

`MrpRun` represents one reproducible material-requirement calculation over selected Supply Plans. `MaterialRequirement` stores one aggregated row per run/material/unit with gross requirement, nullable available quantity, nullable allocated quantity, nullable net requirement, status, and notes. `MaterialRequirementSource` stores the exact supply-plan, Product, BOM version/item, planned product quantity, BOM quantity, wastage percentage, and source gross quantity used to produce each aggregate row.

For every selected Supply Plan, the service selects the active BOM version for the Product and reuses the existing Phase 3 `BOMCalculationService` primitive. The gross line formula is:

`Material Requirement = BOM Quantity × Planned Product Quantity × (1 + Wastage Percentage ÷ 100)`

Identical materials are aggregated by Material and Unit within one MRP run. No unit conversion is invented; a future conversion service remains outside this phase. If a caller supplies actual availability and allocations by Material, the calculation is:

`Net Requirement = max(Gross Requirement − Available Quantity − Allocated Quantity, 0)`

Without supplied inventory information, available quantity, allocated quantity, and net requirement remain `null`, and the requirement status is `pending_inventory`. The MRP API and data model therefore remain ready for the future Inventory phase without creating stock-in, stock-out, reservation, or transfer workflows.

## Database tables

Phase 5 adds only planning-related tables:

| Table | Responsibility |
| --- | --- |
| `demand_forecasts` | Periodized historical-average forecast records and status/quality metadata. |
| `supply_plans` | Product-period supply calculations combining firm order demand and forecast demand. |
| `mrp_runs` | Reproducible material-requirement calculation header. |
| `material_requirements` | One aggregated material/unit result per MRP run. |
| `material_requirement_sources` | Normalized traceability from aggregated requirement to Supply Plan and BOM line. |

All tables use integer primary keys, timestamps, relevant indexes, foreign keys, decimal quantities, and appropriate uniqueness constraints. No Buyer, Product, Variant, Material, Unit, BOM, or Buyer Order table is duplicated.

## Authorization and audit

The existing Gate-plus-Sanctum middleware and `AuditLogService` are extended with `planning.view` and `planning.manage`. Read and preview routes require `planning.view`; create, update, activation, supply-plan generation, and MRP generation require `planning.manage`. Administrator seeding remains idempotent and receives both permissions. Planning mutations are service-owned, transaction-wrapped, and audited.

## API surface

The protected REST API is grouped under `/api/planning`:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/forecasts` | Search, filter, sort, and paginate forecasts. |
| `POST` | `/forecasts/preview` | Calculate a historical-average forecast without persistence. |
| `POST` | `/forecasts` | Create a draft forecast. |
| `GET` | `/forecasts/{forecast}` | Forecast detail and source-period evidence. |
| `PUT` | `/forecasts/{forecast}` | Update a draft forecast. |
| `POST` | `/forecasts/{forecast}/activate` | Activate a forecast. |
| `GET` | `/supply-plans` | Search, filter, sort, and paginate supply plans. |
| `POST` | `/supply-plans/preview` | Preview confirmed-order plus forecast supply calculations. |
| `POST` | `/supply-plans/generate` | Generate period supply-plan records transactionally. |
| `GET` | `/supply-plans/{supplyPlan}` | Supply-plan detail with source evidence. |
| `POST` | `/supply-plans/{supplyPlan}/recalculate` | Recalculate one plan with optional external availability. |
| `GET` | `/material-requirements` | Search and paginate MRP runs/material requirements. |
| `POST` | `/material-requirements/preview` | Preview BOM explosion and aggregation without persistence. |
| `POST` | `/material-requirements/generate` | Persist one reproducible MRP run and aggregate lines. |
| `GET` | `/material-requirements/{run}` | MRP run detail, lines, and source traceability. |

## Frontend

The protected `/planning` route uses one reusable Planning page inside the existing AppLayout. Tabs provide Demand Forecast, Supply Planning, and Material Requirements views. Forecast and planning detail drawers/modals expose source periods, confirmed-order/forecast quantities, active BOM version, aggregated materials, optional availability, and calculation status. Forms support period selection, Product/Product Variant filters, status/search filters, pagination, preview, loading, error, and success feedback. All API calls remain in one `planningService.js` transport boundary, and all authoritative calculations remain in backend services.

## Verification targets

Verification must cover interrupted-state mapping, migrations, foreign keys/indexes/constraints, authorization, forecast historical-average calculation, forecast validation, firm-order filtering, supply-plan generation, active BOM integration, the deterministic `1.5 × 100 × 1.05 = 157.5` result, material aggregation, optional net requirement calculation, API envelopes, browser workflows, Phase 1–4 regression, PHP syntax, Composer, Pint, frontend lint, production build, and cleanup of temporary records. The final report must state that Phase 6 was not started.
