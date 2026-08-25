# Phase 4 Buyer Order UI Verification Notes

**Application:** GarmentFlow

**Scope:** Phase 4 — Buyer Order Management only

**Verification date:** 23 August 2026

## Verification environment

The browser smoke test used the isolated Laravel API at `http://127.0.0.1:8121/api` and the configured Vite frontend at `http://127.0.0.1:5173`. Authentication used the seeded administrator account `test@example.com`. The existing Phase 1 authenticated shell, sidebar, header, protected routing, and logout behavior were preserved throughout the test.

An initial attempt was made against a temporary Vite origin at `http://127.0.0.1:5174`. Laravel rejected that origin because the existing CORS configuration allows the configured local frontend origin at port 5173. The browser was then run against port 5173, where login and all subsequent API-backed Buyer Order interactions succeeded. This was a verification-environment origin mismatch, not a product defect; the application behaved correctly on its configured origin.

| Checkpoint | Result | Evidence |
| --- | --- | --- |
| Login on configured origin | Passed | Seeded administrator authenticated and reached the protected workspace. |
| Protected shell and navigation | Passed | Existing workspace loaded with Overview, Dashboards, Master Data, BOM Engineering, Buyer Orders, and Log out. |
| Buyer Order register | Passed | Search, status filter, buyer filter, date filters, sorting, pagination, totals, status, buyer, creator, and actions rendered. |
| Protected API behavior | Passed | Unauthenticated access was rejected in the live API check; authenticated access loaded the register and detail. |

## Register and seeded draft

The Buyer Orders register loaded from the protected API and displayed the seeded reference order `BO-20260101-0001`. The register showed buyer Northstar Apparel Group, order date 2026-01-01, delivery date 2026-03-15, total quantity 1,000, total amount 12,000.00, draft status, creator Test User, and the expected Edit, Open, and Delete actions.

Opening the seeded order displayed its buyer, delivery date, status, creator, total quantity, total amount, one product-variant line, the draft-only edit and delete controls, the submit-for-approval action, and the initial draft history row. The seeded order was never changed by the browser workflow and was restored and rechecked as a draft after temporary verification data was removed.

## Draft creation, line entry, and backend totals

The Create Buyer Order modal rendered buyer, order date, delivery date, remarks, and dynamic order-line fields. Selecting `BUY-001 · Northstar Apparel Group` and `TEE-CLASSIC · Classic cotton tee` populated the Product Variant selector through the shared Master Data option contract. The selector included the seeded `TEE-CLASSIC-M-NAVY` variant and the temporary API-test variant, demonstrating that the product relationship metadata required for filtering was available to the UI without introducing a duplicate catalog endpoint.

The first line used quantity `12` and unit price `15.50`, with delivery date `2026-09-30`. Preview total returned the backend message `12 units for 186.00 total amount`, and the UI displayed quantity 12 and amount 186.00. Adding a second line rendered independent product, variant, quantity, unit-price, and remove-line controls. The second line used the distinct variant with quantity `8` and unit price `7.75`. The combined backend preview returned quantity `20` and amount `248.00`, matching `12 × 15.50 + 8 × 7.75`.

## Draft persistence and editing

Saving the two-line form created the separate browser verification draft `BO-20260823-0002`. The returned detail displayed both lines, item totals of 186.00 and 62.00, total quantity 20, total amount 248.00, draft-only workflow actions, and the initial draft history row.

Opening Edit draft repopulated the saved buyer, dates, product and variant selectors, quantities, and unit prices. Changing the first quantity from 12 to 14 and requesting Preview total returned quantity 22 and amount 279.00. Saving the edit persisted those values in both the register and detail view. This verified that draft edits refresh the backend-calculated totals rather than relying on a frontend-only calculation.

## Submission and approval

Submit for approval changed `BO-20260823-0002` to `pending approval`, displayed the success feedback, exposed Reject and Approve controls, and rendered a pending approval record awaiting a reviewer. The order history contained both `draft → submitted` and `submitted → pending approval` transitions.

Approve changed the order to `submitted`, displayed the approval success feedback, and rendered the latest approval as approved with Test User as reviewer. The workflow then exposed Confirm order, while the draft edit controls were no longer available.

## Confirmation, planning handoff, and status history

Confirm order changed the order to `confirmed`, displayed `Buyer Order confirmed for planning`, retained the approved decision, and rendered a ready Prepared input planning handoff for 22 units. The history recorded `pending approval → submitted` and `submitted → confirmed`.

Moving the order to planning changed its status to `planning` and exposed the next downstream action as Move to in production. No planning calculation, MRP calculation, procurement record, inventory workflow, production record, quality record, sales record, finance record, delivery record, or report domain was created. The browser-created order remained separate from the seeded reference draft and was removed after verification.

During this verification, a genuine Phase 4 response defect was identified: the generic downstream transition endpoint initially returned a bare order model, so the detail panel temporarily lost hydrated creator, planning-input, and status-history relationships after the planning transition. The service was corrected to reload the full order graph after generic transitions. A second live API run and browser pass confirmed that the planning detail retained Test User as creator, both lines and totals, the approved decision, the ready planning handoff, and all six history entries including `confirmed → planning`.

## Logout and final browser state

After both browser passes, Log out returned the frontend to the GarmentFlow login screen. The final browser pass therefore verified login, protected navigation, Buyer Order workflow, post-fix detail hydration, and logout without changing the canonical seeded order.

## Conclusion

The Phase 4 Buyer Order UI smoke test passed on the configured frontend origin. The browser evidence covers the seeded draft register and detail, creation with multiple lines, backend preview totals, draft persistence, editable draft recalculation, submission, approval, confirmation, planning handoff, history, the corrected downstream transition response, protected navigation, and logout. Phase 5 and all later domains were not started.
