# GarmentFlow Final Full-System Audit Browser Evidence

## Login and authenticated shell

At `http://127.0.0.1:5173/`, the unauthenticated request redirected to `/login`. The seeded non-personal `test@example.com` account logged in successfully. The authenticated shell loaded at `/` with the shared sidebar, centralized Log out action, all five dashboard cards, Reports, and Alerts navigation. The browser-visible footer still says `Foundation environment`, which is documentation/UI drift only.

## Reports smoke

The `/reports` page loaded through the authenticated shell. It exposed all ten report views, date/status/search controls, Reset, Download CSV, Print/PDF, and pagination controls. With the current live data it showed honest zero totals and a no-matching-records state. Switching from Sales to Purchase changed the summary labels to Purchase Order Count, Ordered Quantity, and Received Quantity, confirming backend-driven report selection.

## Dashboard smoke

The dashboard catalog opened Executive and Supply Chain successfully. Both pages rendered backend-backed KPI cards, date controls, trend/status/detail/insight panels, and honest empty states against the current live records. Executive showed zero Sales Orders, Sales Value, Outstanding Receivables, Open Deliveries, and Production in Progress; Supply Chain showed zero required/available supply, planned production, shortfalls, available stock, and open Purchase Orders.

The Production dashboard rendered Production Orders, planned/completed/rejected quantity, completion-rate unavailable when no denominator exists, delayed orders, and empty trend/status/detail/insight states. The Procurement dashboard rendered Purchase Orders, purchase value, posted receipts, delayed orders, received goods value, and equivalent honest empty states.

The Warehouse dashboard rendered tracked balances, on-hand/reserved/available quantities, movements, and empty trend/status/detail/insight states. The `/alerts` page rendered the centralized rule-based signal register with severity and read-state filters, Refresh rules, private per-account read-state messaging, and zero active alerts because the current live data has no alert conditions.

## Core workflow page smoke

Master Data opened successfully and exposed the 13 shared reference registers. Buyer Orders opened successfully with the real seeded draft `BO-20260101-0001`, its 1,000 quantity and 12,000.00 amount, status filter, buyer/date filters, sorting controls, and non-mutating action controls. No test data was created during this audit.

## Planning smoke

Planning opened successfully with Demand Forecast, Supply Planning, and Material Requirements tabs. Demand Forecast showed its protected search/status/period/product controls and honest zero-record state. Supply Planning showed confirmed-demand plus active-forecast inputs, period dates, optional availability, Preview/Generate actions, filters, and zero supply plans. No generation action was submitted.

## Procurement and inventory smoke

Procurement loaded its Purchase Requisitions, Purchase Orders, Goods Receipts, and Procurement History tabs with protected search/status/priority controls and an honest no-record state. Inventory loaded stock-in/out/transfer/adjust controls, Inventory/Transactions/Transfers/Adjustments tabs, seeded warehouse/location options, canonical balance KPIs, filters, and an honest zero-balance state. No mutation action was submitted.

## Production and sales smoke

Production opened with plan/order/progress/material-consumption/finished-goods/history tabs, quantity/status/date/search controls, and an honest zero-production state. Sales Orders opened with Sales Orders, Finished Goods Availability, and Sales History tabs; party/warehouse/status/date filters and a no-order state rendered successfully. No action was submitted.

## Delivery and finance smoke

Deliveries opened with Delivery Register and Delivery History, search/status/warehouse/date filters, and the explicit message that inventory changes only on dispatch; current live data showed zero deliveries. Finance opened with Invoices, Payments, Receivables, Payables, Profit Summary, and Finance History tabs, invoice search/status filters, and zero invoices. No invoice/payment or shipment mutation was submitted.

## Logout smoke

From Finance, the centralized Log out action redirected to `/login`. Browser back navigation remained at the login page, with no protected Finance content rendered. Backend `AuthApiTest` separately verifies the current Sanctum token is deleted and the former bearer token cannot reach `/api/auth/me`.

## Post-remediation smoke check — 2026-08-24 05:48

Opened `http://127.0.0.1:5173/login`, filled the existing seeded account `test@example.com` / `password`, and submitted the form. The app navigated to `/` and rendered the authenticated GarmentFlow workspace with all permission-aware navigation entries: Overview, Dashboards, Master Data, BOM Engineering, Buyer Orders, Planning, Procurement, Inventory, Production, Sales Orders, Deliveries, Finance, Reports, and Alerts. The page showed the authenticated test user and the `Phase 12` badge. The sidebar still displays the previously classified low-severity copy drift `Foundation environment`; this is not a functional failure and was intentionally not changed under the audit rule to fix only genuine Critical/High issues.

## Post-remediation report selector — 2026-08-24 05:48

Opened `/reports` while authenticated. All ten report tabs, filters, export/print controls, and pagination rendered. The default Sales report showed zero totals and an honest empty state. Selecting Purchase changed the KPI labels to Purchase Order Count, Total Amount, Ordered Quantity, and Received Quantity and retained the empty state without errors.

## Post-remediation production dashboard and logout — 2026-08-24 05:49

Opened `/dashboards/production` while authenticated. The page rendered the Production control view, all six KPI cards, empty trend/status/detail/insight panels, and `Completion rate Unavailable` for a zero-denominator dataset. No SQL or relationship error occurred, confirming the previously logged aggregate-ordering and stale warehouse-relation failures are no longer present in the active browser path. Clicking the centralized `Log out` action returned to `/login` with the sign-in form visible.
