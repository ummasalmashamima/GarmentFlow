# Phase 11 Finance Browser Evidence

## Initial authenticated smoke

- Opened the existing authenticated GarmentFlow workspace at `http://127.0.0.1:5173/`.
- Confirmed the sidebar exposes **Finance** and the shared environment badge displays **Phase 11**.
- First Finance navigation used a stale token and displayed the expected authorization error. Logged out and signed in again with the seeded administrator test account after running `AuthorizationSeeder`.
- Fresh Finance navigation succeeded without an authorization error and loaded the invoice register with the expected empty state.
- Invoice register showed the **Create Invoice** action, status filter, search field, pagination controls, and the message that only delivered or completed Sales Orders are eligible.
- Receivables tab loaded the backend summary with zeroed metrics and an empty party breakdown.
- Payables tab loaded the Procurement-derived summary with zeroed metrics and the documented limitation that no supplier payment ledger exists.

## Browser screenshots captured before controlled mutation

- `127_0_0_1_2026-08-24_04-21-57_5827.webp` — authenticated workspace with Finance navigation.
- `127_0_0_1_2026-08-24_04-22-04_5445.webp` — Finance page with stale-token authorization message.
- `127_0_0_1_2026-08-24_04-22-31_3837.webp` — fresh authorized Finance invoice register.
- `127_0_0_1_2026-08-24_04-22-42_5598.webp` — Receivables summary.
- `127_0_0_1_2026-08-24_04-22-50_5240.webp` — Payables summary and limitation.

## Additional read-only tab checks

The Profit Summary tab loaded successfully and displayed backend-calculated Gross Sales, COGS, Gross Profit, Profit Margin, cost completeness, and unpriced-line metrics. With no invoice data present, Gross Profit was zero and margin was shown as unavailable. The Finance History tab loaded its search, module, and action filters, pagination controls, and empty audit state.

| Screenshot | Observation |
| --- | --- |
| `127_0_0_1_2026-08-24_04-23-07_9308.webp` | Profit Summary with cost-completeness metrics |
| `127_0_0_1_2026-08-24_04-23-15_1108.webp` | Finance History filters and empty state |

## Invoice creation UI check

Returned to the Invoices tab and opened the Create Invoice modal without submitting it. The modal displayed the eligible Sales Order selector, invoice date, due date, remarks, Create draft invoice action, and the explicit backend-calculation and eligibility guidance. The live database contained no eligible delivered/completed Sales Orders, so the selector correctly showed no options and the submit action remained disabled.

| Screenshot | Observation |
| --- | --- |
| `127_0_0_1_2026-08-24_04-23-36_8339.webp` | Invoice register tab |
| `127_0_0_1_2026-08-24_04-23-45_3360.webp` | Create Invoice modal with no eligible source orders |

## Authorized live Finance workflow

After the uniquely marked completed Sales Order was prepared, the Finance UI listed it as the sole eligible invoice source. The invoice was created from the UI and showed backend-calculated values of subtotal `30.00`, discount `2.00`, tax `3.00`, total `31.00`, and delivered quantity `3`, linked to `SO-PHASE11-SMOKE-20260824-0426`.

The UI issued the invoice successfully. A first payment of `10.00` with reference `PHASE11-PAY-1-20260824-0426` changed the invoice to **partially paid**, with paid `10.00` and due `21.00`. A second payment of `21.00` with reference `PHASE11-PAY-2-20260824-0426` changed the invoice to **paid**, with paid `31.00` and due `0.00`. The invoice detail displayed both immutable payment-history records and the closed paid state.

| Screenshot | Observation |
| --- | --- |
| `127_0_0_1_2026-08-24_04-27-55_2298.webp` | Finance register before fixture visibility |
| `127_0_0_1_2026-08-24_04-28-03_2020.webp` | Eligible marked Sales Order in Create Invoice modal |
| `127_0_0_1_2026-08-24_04-28-27_8965.webp` | Draft invoice created with calculated totals and source link |
| `127_0_0_1_2026-08-24_04-28-34_2059.webp` | Invoice issued through UI |
| `127_0_0_1_2026-08-24_04-28-57_7118.webp` | Partially paid invoice after first payment |
| `127_0_0_1_2026-08-24_04-29-17_8294.webp` | Paid invoice after final payment with two payment records |

## Live API negative checks and traceability

A fresh administrator API session verified the completed smoke invoice and its two received payments. The recorded values were subtotal `30.0000`, discount `2.0000`, tax `3.0000`, total `31.0000`, paid `31.0000`, due `0.0000`, one invoice item, six invoice audit records, one invoice-item audit record, and two payment audit records. The Sales Order remained completed with delivered quantity `3.0000` and remaining quantity `0.0000`.

An overpayment attempt of `1.00` was rejected with HTTP `422`; because the invoice was already paid, the service correctly rejected the payment state before insertion. A duplicate attempt using the first payment’s existing idempotency key was rejected with HTTP `422` and the explicit already-recorded message. An unauthenticated request to `/api/finance/invoices` returned HTTP `401`, confirming route protection. The read-only snapshot after both negative checks remained unchanged at two smoke payments and paid `31.0000`.

## Post-cleanup browser check

After guarded cleanup, the live Finance page remained authorized and the Invoices register showed `0 invoices` with the expected empty state. The fresh screenshot confirms that the temporary invoice is no longer visible.

| Screenshot | Observation |
| --- | --- |
| `127_0_0_1_2026-08-24_04-33-01_6647.webp` | Finance invoice register empty after cleanup |
