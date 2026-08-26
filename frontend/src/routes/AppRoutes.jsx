import { Navigate, Route, Routes } from 'react-router-dom'
import AppLayout from '../layouts/AppLayout'
import ProtectedRoute from '../components/common/ProtectedRoute'
import Login from '../pages/Auth/Login'
import DashboardHome from '../pages/Dashboards/DashboardHome'
import DashboardView from '../pages/Dashboards/DashboardView'
import ExecutiveDashboard from '../pages/Dashboards/Executive/ExecutiveDashboard'
import MasterDataIndex from '../pages/MasterData/MasterDataIndex'
import MasterDataPage from '../pages/MasterData/MasterDataPage'
import BOMPage from '../pages/BOM/BOMPage'
import BuyerOrderPage from '../pages/BuyerOrders/BuyerOrderPage'
import PlanningPage from '../pages/Planning/PlanningPage'
import ProcurementPage from '../pages/Procurement/ProcurementPage'
import InventoryPage from '../pages/Inventory/InventoryPage'
import ProductionPage from '../pages/Production/ProductionPage'
import SalesOrderPage from '../pages/Sales/SalesOrderPage'
import DeliveryPage from '../pages/Delivery/DeliveryPage'
import FinancePage from '../pages/Finance/FinancePage'
import ReportsPage from '../pages/Reports/ReportsPage'
import AlertsPage from '../pages/Alerts/AlertsPage'

function AppRoutes() {
  return (
    <Routes>
      <Route element={<Login />} path="/login" />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route element={<DashboardHome />} path="/" />
          <Route element={<ExecutiveDashboard />} path="/dashboards/executive" />
          <Route element={<DashboardView />} path="/dashboards/:dashboardKey" />
          <Route element={<MasterDataIndex />} path="/master-data" />
          <Route element={<MasterDataPage />} path="/master-data/:resource" />
          <Route element={<BOMPage />} path="/boms" />
          <Route element={<BuyerOrderPage />} path="/buyer-orders" />
          <Route element={<PlanningPage />} path="/planning" />
          <Route element={<ProcurementPage />} path="/procurement" />
          <Route element={<InventoryPage />} path="/inventory" />
          <Route element={<ProductionPage />} path="/production" />
          <Route element={<SalesOrderPage />} path="/sales" />
          <Route element={<DeliveryPage />} path="/deliveries" />
          <Route element={<FinancePage />} path="/finance" />
          <Route element={<ReportsPage />} path="/reports" />
          <Route element={<AlertsPage />} path="/alerts" />
        </Route>
      </Route>

      <Route element={<Navigate replace to="/" />} path="*" />
    </Routes>
  )
}

export default AppRoutes