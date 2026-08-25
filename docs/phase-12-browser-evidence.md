# Phase 12 Browser Evidence

## 2026-08-24 initial session refresh

The local GarmentFlow frontend loaded successfully at `http://127.0.0.1:5173/`. The pre-existing browser session was authenticated but carried the prior Phase 11 token, so the overview showed the Phase 12 shell with no dashboard cards and no new Reports or Alerts links. The centralized **Log out** action succeeded, redirected to `/login`, and cleared the stale client session. The fresh login form is visible and ready for Phase 12 authentication after live AuthorizationSeeder refresh.

## Fresh Phase 12 administrator session

After live AuthorizationSeeder refresh, the existing test administrator signed in successfully. The shared layout displays the **Phase 12** badge, all five dashboard cards (Executive, Supply Chain, Production, Procurement, Warehouse), and the new **Reports** and **Alerts** navigation entries. This confirms frontend permission-payload visibility for the administrator session.

## Reports and alerts pages

The authenticated browser loaded `/alerts` and rendered the centralized signal register with severity/read-state filters, a rule refresh action, and a correct zero-alert empty state against current live data. `/reports` loaded with all ten required report selectors, date/status/search filters, summary cards, paginated table controls, Download CSV, and Print/PDF actions. With no matching live sales records in the current environment, the page correctly showed zero totals and an explicit empty state rather than fabricated values.

## Dashboard smoke

The Executive and Supply Chain dashboard routes loaded successfully in the authenticated browser. Both rendered live KPI cards, date filters, trend/status panels, operational detail panels, and transparent BI sections. With no matching live transactions in the current environment, they correctly showed zero or unavailable values and explicit empty states rather than hard-coded statistics.

## Production dashboard defect and correction

The first live Production dashboard load exposed a real SQL defect: a grouped status helper mutated the shared Eloquent builder, causing a later aggregate to order by a nonexistent `count` column. The service was corrected to clone builders inside grouped series helpers. Focused API tests passed afterward, and the browser reload now renders Production KPIs, completion-rate unavailable semantics, trend/status panels, operational detail, and rule-based insight states successfully.

## Procurement and Warehouse dashboards

The Procurement and Warehouse dashboard routes loaded successfully. Procurement presented Purchase Order value, posted receipts, delayed-order, and received-goods KPIs. Warehouse presented tracked balances, on-hand/reserved/available quantities, movement count, and movement chart structure. Both used live zero-data states in the current environment and did not display placeholder contract text or invented values.

## Report selector interaction

The Reports page selector was exercised in-browser by switching from Sales to Purchase. The API-backed view updated its report label, summary-card labels, and table state without a page reload, while retaining the filtered-data empty-state behavior.

## Centralized logout and back-navigation

From the authenticated Reports page, the shared **Log out** action redirected to `/login`. Browser back navigation remained at the login page and did not reveal the protected Reports content, confirming the frontend protected-route boundary after logout. Backend logout revocation remains covered by the existing authentication implementation and regression tests.
