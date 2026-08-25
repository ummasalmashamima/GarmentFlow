# GarmentFlow

**GarmentFlow — Garments Supply Chain Intelligence & ERP System.**

This repository is organized as a Laravel/PHP REST API backend and a React/Vite frontend, with shared database and documentation directories at the repository root.

## Repository layout

| Directory | Responsibility |
| --- | --- |
| `backend/` | Laravel application, REST API, domain services, persistence, and tests |
| `frontend/` | React/Vite application, reusable UI, routing, API clients, and pages |
| `database/` | Repository-level database planning artifacts and shared schema documentation |
| `docs/` | Architecture decisions, implementation notes, and phase verification records |

## Development status

The project is currently at the **foundation phase**. The Laravel and React/Vite applications are bootstrapped, the domain-oriented directory structure is in place, and the frontend is wired for Axios and React Router. Business domains, migrations, services, and APIs will be introduced in later phases after the foundation has been verified.

## Local development

Run the backend from `backend/` with `php artisan serve`. Run the frontend from `frontend/` with `npm run dev`.

The backend uses its local `.env` for runtime configuration. Secrets must remain outside source control; use `.env.example` as the configuration template.
