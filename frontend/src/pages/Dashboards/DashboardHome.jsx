import { Link } from 'react-router-dom'
import { DASHBOARDS } from '../../constants/dashboards'
import useAuth from '../../hooks/useAuth'

const workflowSteps = [
  { step: '1', title: 'Buyer Order', path: '/buyer-orders', icon: '🛍️' },
  { step: '2', title: 'Demand Forecast', path: '/planning', icon: '📊' },
  { step: '3', title: 'Supply Plan', path: '/planning', icon: '📋' },
  { step: '4', title: 'Material MRP', path: '/planning', icon: '🧵' },
  { step: '5', title: 'Inventory Check', path: '/inventory', icon: '🔍' },
  { step: '6', title: 'Procurement', path: '/procurement', icon: '📦' },
  { step: '7', title: 'Warehouse', path: '/inventory', icon: '🏢' },
  { step: '8', title: 'Production', path: '/production', icon: '🏭' },
  { step: '9', title: 'Sales Order', path: '/sales', icon: '🏷️' },
  { step: '10', title: 'Shipment', path: '/deliveries', icon: '🚚' },
  { step: '11', title: 'Invoice & Cash', path: '/finance', icon: '💳' },
]

function DashboardHome() {
  const { user } = useAuth()
  const permissions = user?.permissions || []
  const visibleDashboards = DASHBOARDS.filter((dashboard) => permissions.includes(`dashboard.${dashboard.key}.view`))

  return (
    <section className="dashboard-page">
      {/* Intro Hero */}
      <div className="page-intro">
        <div>
          <p className="eyebrow">Enterprise Overview & Control Center</p>
          <h1>Garments Supply Chain Intelligence</h1>
          <p className="lede">
            Centralized visibility connecting buyer orders, materials planning, inventory,
            procurement, production floor, and financial settlements in real time.
          </p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <Link className="primary-button" to="/reports">
            <svg fill="none" height="16" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="16">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
            View Reports
          </Link>
          <Link className="secondary-button" to="/alerts">
            <svg fill="none" height="16" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="16">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            Risk Alerts
          </Link>
        </div>
      </div>

      {/* 11-Step Interactive Pipeline Ribbon */}
      <div className="workflow-pipeline-card">
        <div className="workflow-pipeline-header">
          <div className="workflow-pipeline-title">
            <svg fill="none" height="18" stroke="#4f46e5" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24" width="18">
              <circle cx="12" cy="12" r="10" /><polygon points="10 8 16 12 10 16 10 8" />
            </svg>
            <span>Live Supply Chain Workflow Pipeline (Steps 1–11)</span>
          </div>
          <span style={{ fontSize: '12px', color: 'var(--slate-500)', fontWeight: 600 }}>Click any stage to navigate</span>
        </div>
        <div className="workflow-pipeline-track">
          {workflowSteps.map((ws, idx) => (
            <div key={ws.step} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Link className="pipeline-step" to={ws.path}>
                <span className="pipeline-step-number">{ws.step}</span>
                <span>{ws.icon} {ws.title}</span>
              </Link>
              {idx < workflowSteps.length - 1 && <span className="pipeline-arrow">→</span>}
            </div>
          ))}
        </div>
      </div>

      {/* 5 Role Dashboards Grid */}
      <div className="section-heading" style={{ marginBottom: '18px' }}>
        <div>
          <p className="eyebrow">Role Dashboards</p>
          <h2 style={{ fontSize: '18px', fontWeight: 800, color: 'var(--slate-900)' }}>5 Specialized Executive Perspectives</h2>
        </div>
      </div>

      <div className="dashboard-grid">
        {visibleDashboards.map((dashboard, index) => (
          <Link className={`dashboard-card accent-${dashboard.accent}`} key={dashboard.key} to={dashboard.path}>
            <div className="card-topline">
              <span className="card-index">0{index + 1}</span>
              <span style={{ fontSize: '12px', fontWeight: 700, color: 'var(--primary)', background: 'var(--primary-light)', padding: '3px 8px', borderRadius: '6px' }}>Live KPI</span>
            </div>
            <h3>{dashboard.label} Dashboard</h3>
            <p>{dashboard.description}</p>
            <span className="card-link">Open Control View</span>
          </Link>
        ))}
      </div>

      {!visibleDashboards.length && (
        <div className="panel" style={{ padding: '32px', textAlign: 'center' }}>
          <p className="eyebrow">Access Notice</p>
          <h3>Your account is not assigned to a dashboard view.</h3>
          <p style={{ color: 'var(--slate-500)', marginTop: '8px' }}>
            Please log in with one of the specialized roles (CEO, SCM Manager, Production Manager, Procurement Manager, Warehouse Manager) to access role analytics.
          </p>
        </div>
      )}
    </section>
  )
}

export default DashboardHome
