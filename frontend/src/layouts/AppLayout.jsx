import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { DASHBOARDS } from '../constants/dashboards'
import useAuth from '../hooks/useAuth'

const navItems = [
  { label: 'Overview', to: '/', end: true },
  { label: 'Dashboards', to: '/dashboards/executive', anyPermissions: DASHBOARDS.map((dashboard) => `dashboard.${dashboard.key}.view`) },
  { label: 'Master Data', to: '/master-data' },
  { label: 'BOM Engineering', to: '/boms' },
  { label: 'Buyer Orders', to: '/buyer-orders' },
  { label: 'Planning', to: '/planning' },
  { label: 'Procurement', to: '/procurement' },
  { label: 'Inventory', to: '/inventory' },
  { label: 'Production', to: '/production' },
  { label: 'Sales Orders', to: '/sales' },
  { label: 'Deliveries', to: '/deliveries' },
  { label: 'Finance', to: '/finance' },
  { label: 'Reports', to: '/reports', requiredPermission: 'reports.view' },
  { label: 'Alerts', to: '/alerts', requiredPermission: 'alerts.view' },
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
  const authorizedDashboards = DASHBOARDS.filter((dashboard) => permissions.includes(`dashboard.${dashboard.key}.view`))
  const visibleNavItems = navItems
    .filter((item) => (!item.requiredPermission && !item.anyPermissions) || permissions.includes(item.requiredPermission) || item.anyPermissions?.some((permission) => permissions.includes(permission)))
    .map((item) => item.anyPermissions && authorizedDashboards[0] ? { ...item, to: authorizedDashboards[0].path } : item)

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      navigate('/login', { replace: true })
    }
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand-block">
          <div className="brand-mark" aria-hidden="true">GF</div>
          <div>
            <p className="eyebrow">Supply chain intelligence</p>
            <p className="brand-name">GarmentFlow</p>
          </div>
        </div>

        <nav className="primary-nav" aria-label="Primary navigation">
          <p className="nav-section-label">Workspace</p>
          {visibleNavItems.map((item) => (
            <NavLink
              className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
              end={item.end}
              key={item.to}
              to={item.to}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-footer">
          <span className="status-dot" aria-hidden="true" />
          <span>Foundation environment</span>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div>
            <p className="eyebrow">Enterprise operations</p>
            <p className="topbar-title">Control center</p>
          </div>
          <div className="topbar-meta">
            <div className="user-summary">
              <strong>{user?.name || 'Workspace user'}</strong>
              <span>{user?.email || 'Authenticated session'}</span>
            </div>
            <span className="environment-badge">Phase 12</span>
            <span className="avatar" aria-label={user?.name || 'Current user'}>{initials(user?.name)}</span>
            <button className="logout-button" onClick={handleLogout} type="button">Log out</button>
          </div>
        </header>

        <div className="page-content">
          <Outlet />
        </div>
      </main>
    </div>
  )
}

export { DASHBOARDS }
export default AppLayout
