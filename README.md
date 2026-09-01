# GarmentFlow
### Garments Supply Chain Intelligence & Decision Support System (ERP)

<div align="center">
  <img src="frontend/public/garmentflow.png" alt="GarmentFlow Logo" width="120" />
  <p><strong>A full-stack, enterprise-grade garments manufacturing supply chain intelligence and decision support platform.</strong></p>
  <p>Seamlessly connecting Buyer Orders, Demand Forecasting, Supply Planning, MRP, Inventory, Procurement, Shop-Floor Production, Sales, Logistics, Invoicing, and Cashflow.</p>
</div>

---

## 📌 Project Overview

**GarmentFlow** is designed to eliminate fragmented spreadsheets and disjointed operations in garments manufacturing enterprises. It unites all stages of the manufacturing lifecycle—from initial buyer inquiry and BOM definition to material procurement, production floor tracking, delivery logistics, and accounts receivable—into a unified, real-time single pane of glass.

* **Project:** IsDB-BISEW IT Scholarship Project (WDPF)
* **Developer:** Umma Salma Mosammad Samima Aktar (Trainee ID: `1295920`, Batch: `WDPF/CCSL-M/71/01`)
* **Project Supervisor:** Tawhid Imdad
* **Project Consultant:** Md. Moshaidul Islam

---

## ⚡ Key Highlights & Features

### 1. 11-Step End-to-End Business Pipeline
1. **Buyer Order:** Order intake, size/color variant lines, delivery target dates, approval lifecycle (`Draft` → `Submitted` → `Approved` → `Confirmed`).
2. **Demand Forecasting:** Multi-horizon demand modeling using historical sales and confirmed orders.
3. **Supply Planning:** Balancing manufacturing capacity against forecasted demand and confirmed buyer orders.
4. **Material Requirements Planning (MRP):** Automated BOM explosion, fabric and trims breakdown, net requirements calculation.
5. **Inventory Check & Shortage Detection:** Real-time multi-warehouse balance verification against required BOM items.
6. **Procurement Management:** Automated Purchase Requisition generation from shortages, Purchase Order issuance to suppliers, and Goods Receipt Notes (GRN).
7. **Warehouse Operations:** Raw material and finished goods stock management, stock-in/out, inter-warehouse transfers, and stock adjustments with reason codes.
8. **Shop Floor Production:** Production plans, work orders, line progress tracking, material consumption logging, and finished goods registration.
9. **Sales Orders:** Finished goods allocation, customer assignment, commercial terms, and order confirmation.
10. **Invoicing & Payments:** Automated invoice generation from confirmed sales, line-item pricing, tax/discount calculation, payment tracking (`Paid`, `Partially Paid`, `Unpaid`), and receivables management.
11. **Logistics & Delivery:** Shipment dispatch, vessel/carrier tracking IDs, packing list references, and fulfillment tracking (`Pending` → `Shipped` → `Delivered` / `Delayed`).

---

### 2. 5 Role-Based Specialized Dashboards
* 👔 **Executive / CEO Dashboard:** High-level enterprise KPIs, revenue, gross profit margin, total sales, purchases, inventory value, production output, and cashflow.
* 📈 **Supply Chain Manager Dashboard:** Demand forecasting, supply planning, material requirements, material shortage detection, and supply chain risk alerts.
* 🏭 **Production Manager Dashboard:** Floor progress, line-by-line efficiency, active work orders, material consumption, and finished goods output.
* 📦 **Procurement Manager Dashboard:** Supplier directory, open purchase requisitions, active purchase orders, pending deliveries, and supplier on-time performance.
* 🏬 **Warehouse Manager Dashboard:** Raw materials and finished goods inventory, low-stock alerts, warehouse locations, stock movements, and valuation.

---

### 3. 10 Multi-Dimensional Business & Financial Reports
1. **Invoice Report:** Invoices by customer, billing date, total amount, and payment status.
2. **Stock Report:** Multi-warehouse opening stock, stock in, stock out, and closing balances.
3. **Sales Report:** Sales dates, products, variants, quantities, unit prices, and revenue breakdown.
4. **Purchase Report:** Supplier metrics, raw material purchases, order quantities, and landed costs.
5. **Profit Report:** Revenue vs. Cost of Goods Sold (COGS) / Production Costs = Gross Profit & Margin %.
6. **Production Report:** Planned vs. produced quantities, pending units, and production efficiency.
7. **Procurement Report:** Requisition status, PO fulfillment, ordered vs. received quantities.
8. **Inventory Valuation Report:** Stock value across raw materials and finished goods with low-stock warnings.
9. **Payment Report:** Accounts receivable ledger, collections by method, and outstanding dues.
10. **Delivery Report:** Dispatch fulfillment, shipping carriers, delivered orders, and delayed shipments.

---

### 4. Master Data Architecture
* **Parties:** Buyers, Customers, Suppliers.
* **Products & Materials:** Products, Product Categories, Product Variants, Sizes, Colors, Materials, Material Categories, Units of Measure (UOM).
* **Facilities:** Warehouses, Warehouse Locations.

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| **Frontend** | React 19, Vite, React Router 7, Vanilla CSS3 (Custom Design System), Axios, Recharts |
| **Backend** | PHP 8.2+, Laravel 12 Framework, RESTful API Architecture |
| **Authentication** | Laravel Sanctum (Token-based SPA Authentication with RBAC & Permissions) |
| **Database** | MySQL (with full relational constraints, indexed foreign keys, and audit logs) |
| **Tooling** | Visual Studio Code, Composer, Node.js / npm, Git, GitHub |

---

## 📁 Repository Structure

```text
GarmentFlow/
├── backend/                  # Laravel 12 REST API backend
│   ├── app/
│   │   ├── Http/Controllers/ # Domain API controllers
│   │   ├── Models/           # Eloquent domain models
│   │   ├── Requests/         # Form validation rules
│   │   ├── Resources/        # API JSON resources
│   │   └── Services/         # Core business logic & workflows
│   ├── config/               # Application configuration (cors, sanctum, etc.)
│   ├── database/
│   │   ├── migrations/       # Database schema migrations
│   │   └── seeders/          # Master data, roles, and demo accounts
│   └── routes/api.php        # REST API route definitions
├── frontend/                 # React 19 + Vite frontend
│   ├── src/
│   │   ├── components/       # Reusable components & navigation
│   │   ├── context/          # Auth context & global state
│   │   ├── pages/            # Domain pages & role dashboards
│   │   ├── routes/           # Protected routing definitions
│   │   ├── services/         # Axios API service layer
│   │   └── index.css         # Unified custom design system
├── project-proposal.pdf      # Official project proposal & specification document
└── README.md                 # Project documentation & setup guide
```

---

## 🚀 Getting Started (Installation & Setup)

### Prerequisites
* **PHP:** >= 8.2
* **Composer:** Latest
* **Node.js:** >= 18.x and **npm**
* **MySQL:** >= 8.0 (or MariaDB)

---

### 1. Backend Setup

```bash
# Navigate to the backend directory
cd backend

# Install PHP dependencies
composer install

# Create environment configuration
cp .env.example .env

# Generate application encryption key
php artisan key:generate

# Configure your database in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=garmentflow
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations and seed initial master data & demo users
php artisan migrate --seed

# Start the Laravel backend server (runs on http://localhost:8000)
php artisan serve
```

---

### 2. Frontend Setup

```bash
# Navigate to the frontend directory
cd ../frontend

# Install Node dependencies
npm install

# Start the Vite development server (runs on http://localhost:5173 or http://localhost:5174)
npm run dev
```

Open `http://localhost:5173` (or `http://localhost:5174`) in your browser to access GarmentFlow.

---

## 🔑 Demo Login Credentials

The application includes a **1-Click Role Switcher** on the login page for testing. Alternatively, you can log in manually with the following accounts:

| Role | Email | Password | Primary Responsibility |
|---|---|---|---|
| **Executive / CEO** | `ceo@garmentflow.com` | `password` | Enterprise overview, revenue, profit, deliveries |
| **Supply Chain Manager** | `supplychain@garmentflow.com` | `password` | Demand forecasts, supply planning, MRP |
| **Production Manager** | `production@garmentflow.com` | `password` | Floor progress, work orders, finished goods |
| **Procurement Manager** | `procurement@garmentflow.com` | `password` | Requisitions, purchase orders, goods receipt |
| **Warehouse Manager** | `warehouse@garmentflow.com` | `password` | Stock in/out, transfers, adjustments, valuation |
| **Administrator** | `admin@garmentflow.com` | `password` | Full system access, master data, user permissions |

---

## 📄 License & Attribution

Developed under the **IsDB-BISEW IT Scholarship Programme** (WDPF) by **Umma Salma Mosammad Samima Aktar**.
