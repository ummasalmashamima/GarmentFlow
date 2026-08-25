# GarmentFlow Phase 12 Design: Reports, Dashboards, Alerts, and Rule-Based BI

**Status:** Design approved for implementation after repository audit  
**Author:** Manus AI  
**Scope:** Final Phase 12 only. Verified Phases 1–11 remain authoritative and are not rebuilt.

## 1. Design principles

Phase 12 is a read-oriented reporting and business-intelligence layer over the existing transactional model. It will not introduce report snapshots, KPI tables, materialized summaries, or a second inventory ledger. Report and dashboard values are computed from the existing Sales, Buyer Orders, Planning, Procurement, Inventory, Production, Delivery, and Finance records at request time, with bounded pagination and grouped aggregate queries. Existing domain services remain the source of business rules; Phase 12 services compose and present those results without changing Phase 1–11 workflows.

All financial values are derived only from existing invoice, payment, Purchase Order, ProductVariant, and Product cost fields. Profit and margin remain withheld when the existing ProfitSummaryService reports incomplete usable cost data. Accounts Payable remains explicitly derived from eligible Purchase Orders and receipt progress because the application has no supplier-payment ledger. No forecast, alert, or insight is labelled AI/ML; every rule is exposed with a plain-language reason and source entity.

## 2. Repository and schema audit

| Domain | Existing source of truth | Phase 12 reuse | Important semantics |
|---|---|---|---|
| Sales | `sales_orders`, `sales_order_items`, status history, `SalesOrderService` | Sales, buyer/customer, and executive reports; order-volume series | Confirmed/active order statuses are domain-defined; cancelled orders are excluded from financial/fulfilment totals where appropriate. `total_amount`, ordered/confirmed/delivered/remaining quantities are persisted by the sales workflow. |
| Buyer Orders | `buyer_orders`, items, `BuyerOrderService` | Demand/customer and executive context | Planning uses only confirmed buyer-order demand. |
| Planning | Forecast, supply-plan, material-requirement tables and `SupplyPlanningService`/`ForecastService` | Supply-chain dashboard, demand-vs-supply series, planning reports | `planningKeys()` is the canonical merge of firm demand and active forecasts. |
| Procurement | `purchase_orders`, items, goods receipts/items, approvals, histories, `PurchaseOrderService` | Purchase, supplier-performance, procurement/AP metrics; delayed-PO rules | PO has `po_date`, `expected_delivery_date`, supplier, totals, status; items have quantity, unit price, received quantity; receipts have receipt date, status, warehouse, supplier. |
| Inventory | `inventory_balances`, `inventory_transactions`, transfers, adjustments, `InventoryService` | Stock, movement, warehouse dashboards; available-stock rules | `InventoryService` is canonical for balances, available quantity, and movement history. There is no reorder level, safety stock, or minimum-stock field in existing schemas. |
| Production | Plans, orders, progress, consumption, finished goods and `ProductionOrderService` | Production report/dashboard; delay and rejection rules | Production orders have planned/completed/rejected quantities, expected completion, status, product/variant, warehouse, and plan. |
| Delivery | Deliveries, items, tracking histories and `DeliveryService` | Delivery report/dashboard; delayed-delivery rules | Dispatch is the only stock deduction path. Status and required delivery date are used for transparent delay rules. |
| Finance | Invoices/items/payments; `AccountsReceivableService`, `AccountsPayableService`, `ProfitSummaryService`, `FinanceHistoryService` | Payment, profit, executive reports; overdue/partial invoice rules | AR is based on non-draft/non-cancelled invoices; payments are idempotent; profit requires complete usable costs. AP is PO-derived only. |
| Master data | Buyers, customers, suppliers, products, variants, materials, categories, units, warehouses, locations | Filter labels and dimensions | Existing foreign keys and soft-delete semantics are preserved. |

A schema search found no `reorder`, `safety_stock`, `minimum_stock`, or threshold column. Therefore a low-stock alert is not emitted from a fabricated threshold. The alert engine will emit stock-availability and demand-coverage alerts only when a real active demand requirement exists in planning or a confirmed sales/order requirement exists. Supplier `lead_time_days` and `minimum_order_quantity` are used only as explanatory procurement metadata where present.

## 3. New persistence decision

A narrowly scoped `alerts` table is justified because the Phase 12 requirement includes user-visible read/unread state, severity, related entity, time, and role relevance. Dynamic rules alone cannot reliably persist an individual user's read state. The table will contain only alert instances and state metadata; it will not duplicate transactional facts.

Planned fields are: `id`, `alert_key` (idempotent rule/entity fingerprint), `rule_code`, `severity`, `title`, `description`, nullable `related_type`/`related_id`, nullable `role_slug`, nullable `permission_slug`, nullable `occurred_at`, `resolved_at`, `created_at`, and `updated_at`. A unique key on `alert_key` prevents duplicate rule/entity alerts. Read state is stored in a separate narrow `alert_reads` table keyed by `alert_id` and `user_id`, because one alert may be relevant to many users and a shared `is_read` flag would incorrectly leak one user's state to another. No other Phase 12 tables are planned.

Alert instances are upserted by an authenticated read/generate request from current live data. The engine can mark an alert resolved when its rule condition is no longer true, while preserving history. List queries join the current user's read rows and filter relevance through the user's roles and permission slugs. No background worker, cron, webhook, external API, or notification integration is introduced.

## 4. Permission and dashboard access model

The existing authorization contract is preserved: every protected endpoint uses `auth:sanctum`, a Phase 12 permission middleware, and the existing Gate; both the Sanctum token ability and Laravel Gate must pass. `AuthService`'s token ability allowlist will include every new Phase 12 permission. `AuthorizationSeeder` remains idempotent and continues granting the full set to Administrator.

Permissions:

| Permission | Purpose |
|---|---|
| `reports.view` | View report data and print-friendly report pages. |
| `reports.export` | Download filtered CSV exports. |
| `alerts.view` | View relevant centralized alerts and BI explanations. |
| `alerts.manage` | Mark alerts read/unread and trigger rule refresh. |
| `dashboard.executive.view` | Executive dashboard with sales, finance, delivery, and production overview. |
| `dashboard.supply-chain.view` | Supply/demand, stock coverage, procurement, and receipt overview. |
| `dashboard.production.view` | Production orders, progress, rejection, consumption, and finished-goods overview. |
| `dashboard.procurement.view` | Purchase Orders, receipt progress, supplier performance, and AP-derived overview. |
| `dashboard.warehouse.view` | Inventory balances, movements, warehouse/location, and dispatch/receipt overview. |

Administrator receives all Phase 12 permissions. Existing `dashboard.view` remains the workspace-level access gate. Dashboard keys additionally require their specific permission, and the frontend hides unauthorized cards/links using the existing authenticated user permission payload. Backend authorization is authoritative. Reports and alerts are separate from module mutation permissions and do not grant any write workflow authority.

## 5. Backend contract

A thin `ReportsController` delegates to a single `ReportsService` that validates supported report keys and composes bounded list queries and summary aggregates. Each report returns `{report, filters, summary, rows, meta}`. The same normalized filters and query builder are used for JSON and CSV, so exports cannot silently ignore a selected filter. CSV exports are streamed with `text/csv` and a safe report filename. Excel and server PDF dependencies are not added; the frontend provides a print-friendly layout suitable for browser Save as PDF.

The ten report keys are: `sales`, `purchase`, `stock`, `profit`, `production`, `payment`, `delivery`, `inventory-movement`, `supplier-performance`, and `buyer-customer`. Common filters include date range, status, party/supplier, product/variant, category, warehouse, search, sort, direction, and page size; unsupported filters are ignored or rejected by the request contract rather than interpolated into SQL. Report-specific dimensions are documented in the request rules and response metadata.

A `DashboardController` delegates to `DashboardService` and serves exactly five keys: `executive`, `supply-chain`, `production`, `procurement`, and `warehouse`. Each response contains a generated-at timestamp, KPI cards, small date-grouped chart series, top-N/status tables, and a bounded `insights` list whose source and rule text are transparent. The service uses aggregate queries and existing domain services; it does not load unbounded transaction collections.

An `AlertController` delegates to `AlertService` for relevant alert listing, refresh, and read/unread state. The API exposes severity, title, description, rule code, related entity, occurred time, read state, and resolution state. Refresh recomputes active rule conditions against live data and is idempotent. Alert routes never permit a caller to change severity, description, rule code, related entity, or relevance scope.

## 6. Transparent alert rules

| Rule code | Condition using real data | Relevance |
|---|---|---|
| `invoice_overdue` | Non-draft/non-cancelled invoice has a due date before today and positive outstanding balance | `finance.view` / executive-relevant roles |
| `invoice_partially_paid` | Issued/eligible invoice has positive paid amount below total | `finance.view` |
| `purchase_order_delayed` | Non-terminal PO has expected delivery date before today and receipt progress is incomplete | `procurement.view` |
| `delivery_delayed` | Active delivery has a required delivery date before today and is not terminal | `delivery.view` |
| `production_delayed` | Non-terminal production order has expected completion before today and remaining quantity is positive | `production.view` |
| `demand_without_available_stock` | Confirmed/active demand has required quantity greater than available finished-goods or material stock computed by existing planning/inventory data | `planning.view` / `inventory.view` |
| `supplier_receipt_rejection` | Real goods receipt has rejected quantity greater than zero | `procurement.view` |

No generic low-stock alert is created without a threshold. All rules have a deterministic fingerprint based on rule code and related entity/date condition, so repeated refreshes do not duplicate alerts.

## 7. Frontend contract

The existing React Router, Axios client, AuthContext, ProtectedRoute, AppLayout, and shared `index.css` remain the only frontend architecture. `/reports` is a protected route with a report selector, common/date/domain filters, summary cards, paginated table, loading/empty/error states, CSV export, and a print action. `/alerts` is a protected route with severity/read filters, refresh, read/unread controls, related-entity links where safe, and empty/error states. Dashboard home and `/dashboards/:dashboardKey` use the five backend dashboard contracts and render responsive KPI cards, CSS/SVG chart series, status tables, and transparent insights without adding a chart dependency.

Navigation entries are rendered only when the authenticated permission payload permits them. Logout remains centralized in `AuthContext`; Phase 12 verifies backend Sanctum revocation, client token removal, redirect, and protected-route/back-navigation behavior. No page creates its own logout or token store.

## 8. Verification acceptance criteria

Focused API tests will cover all ten report keys, filter/sort/pagination and summary totals, CSV filter fidelity, dashboard authorization and payloads, each alert rule, idempotent refresh, per-user read state, permission denial, and no fabricated stock/profit values. Existing full Laravel tests are rerun after Phase 12.

Static checks include migration status, PHP syntax, Pint, Composer validation, frontend lint, and production build. Browser smoke covers login/session, report filters and CSV, print layout, all five dashboards, alert refresh/read state, unauthorized visibility, and centralized logout with browser back navigation. Controlled smoke fixtures are uniquely marked and removed only after reference checks. Temporary scripts, logs, build output, and only processes started by Phase 12 are cleaned. Phase 12 is declared verified only after all checks pass; no later phase is started.
