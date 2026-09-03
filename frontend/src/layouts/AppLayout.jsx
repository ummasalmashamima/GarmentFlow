import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import useAuth from '../hooks/useAuth'

// Clean vector SVG Icons
const Icons = {
  Overview: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <rect height="7" width="7" x="3" y="3" /><rect height="7" width="7" x="14" y="3" /><rect height="7" width="7" x="14" y="14" /><rect height="7" width="7" x="3" y="14" />
    </svg>
  ),
  Executive: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
    </svg>
  ),
  SupplyChain: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><line x1="8.59" x2="15.42" y1="13.51" y2="17.49" /><line x1="15.41" x2="8.59" y1="6.51" y2="10.49" />
    </svg>
  ),
  Production: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
    </svg>
  ),
  Procurement: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="m7.5 4.27 9 5.15" /><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" /><path d="m3.3 7 8.7 5 8.7-5" /><path d="M12 22V12" />
    </svg>
  ),
  Warehouse: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
    </svg>
  ),
  BuyerOrders: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" /><path d="M3 6h18" /><path d="M16 10a4 4 0 0 1-8 0" />
    </svg>
  ),
  Planning: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M8 2v4" /><path d="M16 2v4" /><rect height="18" rx="2" width="18" x="3" y="4" /><path d="M3 10h18" /><path d="M8 14h.01" /><path d="M12 14h.01" /><path d="M16 14h.01" /><path d="M8 18h.01" /><path d="M12 18h.01" />
    </svg>
  ),
  Sales: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <circle cx="8" cy="21" r="1" /><circle cx="19" cy="21" r="1" /><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
    </svg>
  ),
  Delivery: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2" /><path d="M15 18H9" /><path d="M19 18h2a1 1 0 0 0 1-1v-5l-4-4h-3v10" /><circle cx="7" cy="18" r="2" /><circle cx="17" cy="18" r="2" />
    </svg>
  ),
  Finance: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <rect height="14" rx="2" width="20" x="2" y="5" /><line x1="2" x2="22" y1="10" y2="10" />
    </svg>
  ),
  Reports: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="16" x2="8" y1="13" y2="13" /><line x1="16" x2="8" y1="17" y2="17" /><line x1="10" x2="8" y1="9" y2="9" />
    </svg>
  ),
  Alerts: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
    </svg>
  ),
  BOM: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" /><polyline points="3.27 6.96 12 12.01 20.73 6.96" /><line x1="12" x2="12" y1="22.08" y2="12" />
    </svg>
  ),
  MasterData: () => (
    <svg fill="none" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
      <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" /><path d="M6 6h10" /><path d="M6 10h10" />
    </svg>
  ),
}

const navSections = [
  {
    title: 'Command Center',
    items: [
      { label: 'Overview', to: '/', end: true, icon: Icons.Overview },
      { label: 'Risk & Alerts', to: '/alerts', requiredPermission: 'alerts.view', icon: Icons.Alerts },
    ],
  },
  {
    title: 'Role Dashboards',
    items: [
      { label: 'Executive (CEO)', to: '/dashboards/executive', requiredPermission: 'dashboard.executive.view', icon: Icons.Executive },
      { label: 'Supply Chain', to: '/dashboards/supply-chain', anyPermissions: ['dashboard.supply-chain.view', 'dashboard.supply_chain.view'], icon: Icons.SupplyChain },
      { label: 'Production', to: '/dashboards/production', requiredPermission: 'dashboard.production.view', icon: Icons.Production },
      { label: 'Procurement', to: '/dashboards/procurement', requiredPermission: 'dashboard.procurement.view', icon: Icons.Procurement },
      { label: 'Warehouse', to: '/dashboards/warehouse', requiredPermission: 'dashboard.warehouse.view', icon: Icons.Warehouse },
    ],
  },
  {
    title: 'Garments Pipeline',
    items: [
      { label: 'Buyer Orders', to: '/buyer-orders', anyPermissions: ['buyer-order.view', 'buyer-order.manage'], icon: Icons.BuyerOrders },
      { label: 'Planning & MRP', to: '/planning', anyPermissions: ['planning.view', 'planning.manage'], icon: Icons.Planning },
      { label: 'Procurement', to: '/procurement', anyPermissions: ['procurement.view', 'procurement.manage'], icon: Icons.Procurement },
      { label: 'Production Floor', to: '/production', anyPermissions: ['production.view', 'production.manage'], icon: Icons.Production },
      { label: 'Sales Orders', to: '/sales', anyPermissions: ['sales.view', 'sales.manage'], icon: Icons.Sales },
      { label: 'Shipments & Delivery', to: '/deliveries', anyPermissions: ['delivery.view', 'delivery.manage'], icon: Icons.Delivery },
    ],
  },
  {
    title: 'Warehouse & Assets',
    items: [
      { label: 'Inventory Balances', to: '/inventory', anyPermissions: ['inventory.view', 'inventory.manage'], icon: Icons.Warehouse },
      { label: 'BOM Tech Packs', to: '/boms', anyPermissions: ['bom.view', 'bom.manage'], icon: Icons.BOM },
    ],
  },
  {
    title: 'Finance & Analytics',
    items: [
      { label: 'Invoicing & Payments', to: '/finance', anyPermissions: ['finance.view', 'finance.manage'], icon: Icons.Finance },
      { label: 'Intelligence Reports', to: '/reports', requiredPermission: 'reports.view', icon: Icons.Reports },
      { label: 'Master Data Registers', to: '/master-data', anyPermissions: ['master-data.view', 'master-data.manage'], icon: Icons.MasterData },
    ],
  },
]

function initials(name = 'GarmentFlow') {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function AppLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const permissions = user?.permissions || []

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      navigate('/login', { replace: true })
    }
  }

  const roleName = user?.roles?.[0]?.name || 'Operations User'

  return (
    <div className="app-shell">
      {/* High-End Enterprise Sidebar */}
      <aside className="sidebar">
        <div className="brand-block">
          <div className="brand-mark" aria-hidden="true">GF</div>
          <div className="brand-title-wrap">
            <h1 className="brand-name">GarmentFlow</h1>
            <span className="brand-tagline">Supply Chain ERP</span>
          </div>
        </div>

        <nav className="primary-nav" aria-label="Primary navigation">
          {navSections.map((section) => {
            const filteredItems = section.items.filter((item) => {
              if (!item.requiredPermission && !item.anyPermissions) return true
              if (item.requiredPermission && permissions.includes(item.requiredPermission)) return true
              if (item.anyPermissions && item.anyPermissions.some((p) => permissions.includes(p))) return true
              return false
            })

            if (!filteredItems.length) return null

            return (
              <div key={section.title}>
                <p className="nav-section-label">{section.title}</p>
                {filteredItems.map((item) => {
                  const Icon = item.icon
                  return (
                    <NavLink
                      className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
                      end={item.end}
                      key={item.to}
                      to={item.to}
                    >
                      <span className="nav-icon" aria-hidden="true"><Icon /></span>
                      <span>{item.label}</span>
                    </NavLink>
                  )
                })}
              </div>
            )
          })}
        </nav>

        <div className="sidebar-footer">
          <div className="system-status-indicator">
            <span className="pulse-dot" aria-hidden="true" />
            <span>Connected & Live</span>
          </div>
          <span style={{ fontSize: '11px', color: 'var(--slate-500)' }}>v2.0 PRO</span>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="main-content">
        <header className="topbar">
          <div className="topbar-left">
            <div>
              <p className="topbar-title">GarmentFlow Intelligence Platform</p>
              <span className="topbar-subtitle">End-to-End Garments Manufacturing ERP</span>
            </div>
          </div>
          <div className="topbar-meta">
            <div className="user-profile-badge">
              <div className="user-avatar" aria-label={user?.name || 'Current user'}>
                {initials(user?.name)}
              </div>
              <div className="user-info">
                <span className="user-name">{user?.name || 'Workspace User'}</span>
                <span className="user-role-tag">{roleName}</span>
              </div>
            </div>
            <button className="logout-button" onClick={handleLogout} type="button">
              <svg fill="none" height="15" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="15">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" x2="9" y1="12" y2="12" />
              </svg>
              Sign out
            </button>
          </div>
        </header>

        <div className="page-content">
          <Outlet />
        </div>
      </main>
    </div>
  )
}

export default AppLayout
