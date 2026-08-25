import { Link } from 'react-router-dom'
import { DASHBOARDS } from '../../constants/dashboards'
import useAuth from '../../hooks/useAuth'

function DashboardHome() {
  const { user } = useAuth()
  const permissions = user?.permissions || []
  const visibleDashboards = DASHBOARDS.filter((dashboard) => permissions.includes(`dashboard.${dashboard.key}.view`))

  return (
    <section className="dashboard-page">
      <div className="page-intro">
        <div>
          <p className="eyebrow">GarmentFlow workspace</p>
          <h1>Decisions at the speed of the line.</h1>
          <p className="lede">
            A connected operating layer for garment demand, materials, production, quality,
            delivery, and cash. Choose a control view to begin.
          </p>
        </div>
        <div className="intro-mark" aria-hidden="true">
          <span>01</span>
          <strong>Foundation</strong>
        </div>
      </div>

      <div className="section-heading">
        <div>
          <p className="eyebrow">Control views</p>
          <h2>Five perspectives. One operating picture.</h2>
        </div>
        <span className="muted-label">Live data wiring follows the API phases</span>
      </div>

      <div className="dashboard-grid">
        {visibleDashboards.map((dashboard, index) => (
          <Link className={`dashboard-card accent-${dashboard.accent}`} key={dashboard.key} to={dashboard.path}>
            <div className="card-topline">
              <span className="card-index">0{index + 1}</span>
              <span className="arrow" aria-hidden="true">↗</span>
            </div>
            <h3>{dashboard.label}</h3>
            <p>{dashboard.description}</p>
            <span className="card-link">Open control view</span>
          </Link>
        ))}
      </div>
      {!visibleDashboards.length && <div className="empty-state"><p className="eyebrow">No dashboard access</p><h2>Your role has no dashboard view permission.</h2><p>Ask an administrator to assign a specific dashboard permission. Backend authorization remains enforced.</p></div>}

      <div className="foundation-banner">
        <div className="banner-icon" aria-hidden="true">↳</div>
        <div>
          <p className="eyebrow">Built for maintainability</p>
          <h2>Shared architecture before feature sprawl.</h2>
          <p>
            The Laravel API, domain services, normalized database, and reusable React components
            are now connected through transparent Phase 12 reporting and control views.
          </p>
        </div>
      </div>
    </section>
  )
}

export default DashboardHome
