# Phase 7 Browser Evidence (working notes)

At `http://127.0.0.1:5173/inventory`, the authenticated `test@example.com` Administrator session rendered the Phase 7 Inventory Control Center with one sidebar Inventory entry, summary cards, Inventory/Transactions/Transfers/Adjustments tabs, warehouse/location/item filters, and Stock In/Out/Transfer/Adjust action buttons.

The prepared browser Stock In used the seeded `DHK-01` warehouse, `A-01-01` location, `FAB-COT-001` material, `KG` unit, and quantity `12`. The browser displayed `Stock in posted successfully.` and the table showed exactly one balance row with on hand `12`, reserved `0`, and available `12` for stock key `1|1|material:1|2`.

A genuine UI defect was observed after posting: the four summary cards remained at zero while the table reflected twelve. The page refresh logic was updated to reload the summary after movements. A subsequent browser view still showed stale zero summary cards, so the summary endpoint/state wiring requires direct diagnostic verification before final completion. No Phase 8 work has been started.

Direct browser-console API diagnostics using the actual `garmentflow_access_token` returned HTTP 200 with `balance_count: 1`, `quantity_on_hand: 12`, `quantity_available: 12`, and `quantity_reserved: 0`. An earlier diagnostic using an incorrect guessed storage key returned 401 and did not affect application state. The backend summary endpoint is therefore correct; the visible zero cards were retained state from before the refresh patch and should be rechecked after a page reload.

After reloading the page, the summary cards correctly displayed **1 controlled balance**, **On hand 12**, **Reserved 0**, and **Available 12**. The Inventory table showed `DHK-01 / A-01-01 / FAB-COT-001 / KG` with on hand `12`, reserved `0`, and available `12`. The Transactions tab showed exactly one `INV-20260823-0001` `STOCK_IN` ledger row for `12 KG`.

A live database evidence query confirmed the canonical balance row `id=3`, stock key `1|1|material:1|2`, warehouse `1`, location `1`, material `1`, unit `2`, quantity_on_hand `12.0000`, quantity_reserved `0.0000`. It found exactly one matching Stock In transaction (`id=6`, quantity `12.0000`, no duplicate row) and two audit rows: `inventory-balances / quantity_changed` for balance `3`, and `inventory-transactions / stock_in` for transaction `6`. The temporary browser record remains pending cleanup until all final browser checks are complete.

The live browser smoke then opened the **Transactions** tab, which rendered one Stock In history row for `INV-20260823-0001`, `12 KG`, and retained summary totals of 1 balance / 12 on hand / 12 available. The **Transfers** and **Adjustments** tabs also rendered their source/destination and direction/warehouse filters with clean empty states after the API smoke data had been cleaned. No runtime error was shown during these tab transitions.

The Stock Transfer form opened successfully from the authenticated workspace and exposed source warehouse/location, destination warehouse/location, item type, item, unit, quantity, transfer date, remarks, and a clearly labeled atomic-submit action. It was closed without submission, so no additional live transfer data was created.

Phase 1–2 regression smoke passed in the same authenticated session: the Overview page rendered the existing control-view cards and app shell, and `/master-data` rendered the existing 13 master-data registers including Products, Product Variants, Materials, Units, Warehouses, and Warehouse Locations. The sidebar retained all prior entries and the new Inventory entry.

Phase 3–4 regression smoke passed: `/boms` rendered the seeded active `BOM-TEE-CLASSIC` with its existing search, status filter, sorting, and edit/open controls; `/buyer-orders` rendered the seeded draft order with its existing search, status/buyer/date filters, sorting, and workflow controls. No Phase 7 change disrupted these pages.

Phase 5–6 regression smoke passed: `/planning` rendered the existing Demand Forecast, Supply Planning, and Material Requirements tabs with filters and seeded product options; `/procurement` rendered the existing Purchase Requisitions, Purchase Orders, Goods Receipts, and Procurement History tabs with search/status/priority controls. No procurement UI error appeared after Goods Receipt inventory integration.
