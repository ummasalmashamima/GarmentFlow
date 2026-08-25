# Phase 10 Delivery Browser Evidence

## Scope

This evidence records the authorized, temporary Phase 10 browser smoke test only. No Phase 11, Finance, invoices, payments, profit, reports, or dashboard implementation was performed.

The smoke test used the seeded administrator on the allowed frontend origin `http://127.0.0.1:5173`, with the live Laravel API at `http://127.0.0.1:8000`. The administrator role was reseeded before login so the fresh Sanctum token contained `delivery.view`, `delivery.manage`, `delivery.dispatch`, and `delivery.override`.

## Prepared fixture

| Entity | Exact value |
| --- | --- |
| Sales Order | `SO-20260824-0001` |
| Sales Order database ID | `2` |
| Delivery | `DLV-20260824-0001` |
| Delivery database ID | `1` |
| Delivery Item database ID | `1` |
| Product / variant | `TEE-CLASSIC` / `TEE-CLASSIC-M-NAVY` |
| Unit / warehouse | `PCS` / `DHK-01` |
| Delivery quantity | `2` |
| Carrier | `Smoke Carrier` |
| Tracking number | `SMOKE-DLV-001` |
| Tracking event | `Dhaka Hub`, `phase10-browser-smoke tracking checkpoint` |

## Browser sequence

The Delivery register initially rendered with zero rows and no authorization error after the fresh login. The creation modal displayed only the confirmed Sales Order and loaded its source item with remaining quantity `4`. The form displayed the explicit message that stock remains unchanged until Dispatch.

The authorized UI sequence then completed the following transitions:

| Step | Observed UI state | Result |
| --- | --- | --- |
| Create | `created`, ordered `2`, dispatched `0`, delivered `0`, Sales remaining `4` | Delivery created; no inventory deduction |
| Prepare | `ready for shipment` | Valid transition recorded |
| Dispatch | `shipped`, dispatched `2`, inventory reference `delivery-item:1:dispatch`, Sales delivered `2`, Sales remaining `2` | Canonical InventoryService stock-out occurred once |
| Tracking | `shipped`, carrier and tracking preserved, event at `Dhaka Hub` | Immutable tracking event recorded |
| Transit | `in transit` | Valid transition recorded |
| Last-mile | `out for delivery` | Valid transition recorded |
| Receipt | `delivered`, delivered `2` | Delivery quantities finalized |
| Completion | `completed` | Terminal delivery state reached |

The browser-rendered tracking history contained eight ordered events: `created`, `ready_for_shipment`, `shipped`, a same-status tracking checkpoint, `in_transit`, `out_for_delivery`, `delivered`, and `completed`.

## API verification before cleanup

The live API returned the completed Delivery linked to Sales Order ID `2`, with ordered, dispatched, and delivered quantities of `2.0000`, and tracking number `SMOKE-DLV-001`. The nested Delivery Item referenced inventory transaction ID `16`, transaction number `INV-20260824-0002`, transaction type `STOCK_OUT`, and idempotency key `delivery-item:1:dispatch`.

The Sales Order API returned status `confirmed`, confirmed quantity `4.0000`, delivered quantity `2.0000`, and remaining quantity `2.0000`, which is the expected partial-delivery result. The duplicate dispatch request returned HTTP `422` with the terminal-delivery validation message and did not create another movement. The tracking-history API contained the `Dhaka Hub` checkpoint.

The inventory history showed exactly one smoke stock-in under idempotency key `phase10-browser-smoke-stock-in-20260824T0335` and exactly one Delivery stock-out under idempotency key `delivery-item:1:dispatch`. The browser-smoke balance was restored during cleanup.

## Screenshots

The browser tool saved the following smoke-state screenshots:

1. Login screen: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-31-01_4563.webp`
2. Delivery register authorized and empty: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-34-27_6806.webp`
3. Delivery creation modal with confirmed Sales Order: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-36-29_5451.webp`
4. Partial quantity entered: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-37-12_5162.webp`
5. Delivery created and opened in detail: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-45-43_7837.webp`
6. Ready for shipment: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-45-51_2379.webp`
7. Dispatched and stocked out: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-45-58_8133.webp`
8. Tracking checkpoint saved: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-46-19_2451.webp`
9. Out for delivery: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-46-36_7271.webp`
10. Delivered: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-46-43_2883.webp`
11. Completed: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-46-50_1936.webp`
12. Post-cleanup empty Delivery register: `/home/ubuntu/screenshots/127_0_0_1_2026-08-24_03-49-33_5102.webp`

## Cleanup

Cleanup was guarded by the exact Delivery number, Sales Order number, smoke remarks, stock-in idempotency key, and Delivery stock-out linkage. It verified the Delivery belonged to the marked Sales Order, that no other Delivery referenced that Sales Order, that exactly one Delivery Item existed, and that the two smoke inventory movements shared the affected balance. It then restored the affected balance and removed only the marked Delivery, Delivery Item, tracking histories, marked Sales Order and item/status histories, related audit rows, and the two marked inventory transactions.

The read-only cleanup verification returned `cleanup_successful: true`: zero exact Delivery records, zero smoke Sales Orders, zero linked Delivery Items, zero tracking histories for the deleted Delivery, zero exact stock-in or stock-out transactions, zero audit rows containing the smoke marker, zero affected product balances, and affected product balance quantity `0`. A browser refresh after cleanup rendered an empty Delivery register with `0 deliveries` and no smoke notification.
