# Phase 3 BOM UI Verification Notes

## Browser session

The frontend was served from `http://127.0.0.1:5173` with the Laravel API at `http://127.0.0.1:8120`. The seeded user `test@example.com` was used for the authenticated smoke test. No credentials are stored in this document.

## Verified checkpoints

| Checkpoint | Result | Evidence |
| --- | --- | --- |
| Unauthenticated frontend entry | Passed | Opening the frontend redirected to `/login`. |
| Phase 1 login | Passed | Seeded user signed in and the protected workspace loaded. |
| Existing shell and navigation | Passed | Existing Overview, Dashboards, Master Data, and new BOM Engineering entries rendered in the same sidebar/header shell. |
| BOM register | Passed | `/boms` loaded the API-backed BOM register with the seeded `BOM-TEE-CLASSIC` record, status, product, active version, search, filter, sorting, and pagination controls. |
| BOM detail | Passed | Seeded BOM detail displayed product, lifecycle status, revision history, selected version, material line, and lifecycle actions. |
| Calculation preview | Passed | Order quantity `100` displayed `157.5 kg` for quantity `1.5` and wastage `5%`. |
| Version creation | Passed | New draft version `v2` was created with effective date `2026-08-23`. |
| Relation-backed item form | Passed | Material options included `FAB-COT-001 · Organic cotton jersey`; Unit options included `KG · Kilograms` and `PCS · Pieces`. |
| Item creation | Passed | A `1.5` quantity, `5%` wastage, line `1` item was added to draft `v2`, and the detail view refreshed with success feedback. |
| Version activation | Passed | Draft `v2` became active and `v1` became inactive; the register displayed active version `v2`. |
| Version deactivation | Passed | `v2` became inactive and the BOM became inactive when no active version remained. |
| Phase 1 logout | Passed | Logout returned the browser to `/login`. |

## Cleanup

The browser-created version and its material line were removed after the smoke test using a temporary repository-external restoration script. The seeded BOM was restored to active version `v1`, and the verification script was deleted. The temporary development servers were stopped after final verification.

## Notes

The browser automation briefly left the BOM detail modal open while attempting logout; closing the modal and repeating the preserved header logout control succeeded. This was an automation overlay interaction, not an application defect. No Phase 4 functionality was exercised or implemented.
