# Phase 5 Planning & Demand Forecasting — Browser Verification Notes

**Project:** GarmentFlow  
**Frontend origin:** `http://127.0.0.1:5173`  
**API origin:** `http://127.0.0.1:8121/api`  
**Verified role:** seeded Administrator (`test@example.com`)  
**Verification date:** 23 August 2026

## Verification scope

This browser pass covered the protected Planning route and its three Phase 5 workflows: Demand Forecast, Supply Planning, and Material Requirements. It also checked preserved Buyer Orders and BOM Engineering navigation and executed the existing Phase 3 BOM calculation. The browser was logged out at the end of the pass.

## Authentication and navigation

The existing GarmentFlow login page loaded at the configured frontend origin. The seeded administrator credentials authenticated successfully and returned the protected workspace. The sidebar retained Overview, Dashboards, Master Data, BOM Engineering, and Buyer Orders, with one new Planning entry. Opening Planning rendered the Phase 5 heading, explanatory copy, three tabs, forecast filters, and the register without a visible page or console error.

## Demand Forecast workflow

The Demand Forecast form rendered Product and Product Variant selectors, period type/start/end fields, Manual and Historical average methods, forecast quantity, lookback, confidence, forecast date, notes, preview, and save controls. The seeded `TEE-CLASSIC` Product and `TEE-CLASSIC-M-NAVY` Variant were selectable through the shared Master Data options API.

The Manual method correctly enabled the required quantity field. Entering quantity `40` and selecting Preview calculation returned a visible server result with quantity `40`, method `manual`, and three comparable historical periods. Saving returned `Demand forecast created as a draft.` The first register refresh exposed a genuine frontend transport defect: the saved draft existed in the API/database but the register displayed zero rows because the collection response envelope was being unwrapped as an array. The shared `planningService.js` collection methods were corrected to preserve Laravel `data` and `meta`; the register then displayed the saved draft with Product, Variant, August 2026 period, quantity `40`, method `manual`, and `draft` status.

Opening the row displayed the persisted detail and historical evidence. Activating it returned `Demand forecast activated for supply planning.` and changed both detail and register status to `active`.

## Supply Planning workflow

The Supply Planning tab rendered Product and Variant inputs, weekly/monthly/quarterly period controls, optional Available quantity, Preview plan, Generate plan, filters, and an empty register. Selecting `TEE-CLASSIC` exposed its matching Variant option. With Available quantity `20`, the first preview returned zero active forecast demand even though the active forecast matched the August Product period. This exposed a genuine backend aggregation defect: an all-variant Product plan was filtering out Variant-specific active forecasts.

The Supply Planning calculation was corrected so Product-level plans include active forecasts across Variants while Variant-specific plans remain filtered to the selected Variant. The fresh preview then returned Active forecast `40`, Required `40`, Availability `20`, and Planned `20`, with zero confirmed orders in the cleaned verification database. Generating the plan returned `Supply plan generated from confirmed demand and active forecasts.` and persisted one calculated row. Its detail modal showed Confirmed order `0`, Forecast `40`, Required `40`, Available `20`, Planned production `20`, and the Recalculate availability action.

## Material Requirements workflow

The Material Requirements tab rendered the planning date, Supply Plan selection list, Preview requirements, Generate MRP run, run filters, and register. Selecting the generated Supply Plan and previewing produced Gross materials `31.5`, Net materials `Unknown`, and one aggregated `FAB-COT-001 · kg` line at `31.5`. The UI explicitly stated that availability was not supplied and net requirements remained unknown.

Generating the run returned `Material requirements generated from active BOMs and selected Supply Plans.` The register displayed `MRP-20260823-0001` with one material line, gross quantity `31.5`, unknown net quantity, no supplied inventory, and calculated status. The detail modal displayed the same values and the aggregated material line with `pending inventory` status.

## Preserved Phase 1–4 regression

Buyer Orders loaded after the Planning workflow. The seeded reference order `BO-20260101-0001` remained present as `draft`, with total quantity `1,000`, total amount `12,000.00`, buyer `Northstar Apparel Group`, and its existing Edit/Open/Delete controls. BOM Engineering loaded with the seeded active `BOM-TEE-CLASSIC`, active version `v1`, and one `Organic cotton jersey` line at `1.5 kg` and `5%` wastage. Running the existing calculation at order quantity `100` returned `157.5 kg`, preserving `1.5 × 100 × 1.05 = 157.5`.

## Logout and final state

Logging out from the authenticated shell returned the browser to the login page. All temporary browser-created forecast, supply-plan, and MRP records were removed afterward, and the canonical seeded database state was restored.

## Verification conclusion

The Phase 5 Planning UI is browser-verified across forecast creation/preview/activation, supply preview/generation/detail, BOM-driven MRP preview/generation/detail, protected navigation, preserved Phase 1–4 routes, and logout. The two defects found during the pass—the paginated collection envelope handling and Product-level active-forecast aggregation—were fixed and reverified. No Phase 6 feature was started.
