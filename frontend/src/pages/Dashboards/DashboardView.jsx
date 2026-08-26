import { Link, useParams } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import { DASHBOARDS } from '../../constants/dashboards'
import reportingService from '../../services/reportingService'

function formatValue(value, format = 'number') {
  if (value === null || value === undefined) return 'Unavailable'
  if (format === 'amount') return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  if (format === 'percentage') return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}%`
  return Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function EmptyPanelState({ message, icon = '📊' }) {
  return (
    <div className="panel-empty-state">
      <span className="panel-empty-icon">{icon}</span>
      <p>{message}</p>
    </div>
  )
}

function SeriesBars({ series = [], valueKey }) {
  const values = series.map((item) => Number(item[valueKey] ?? item.value ?? item.count ?? 0))
  const max = Math.max(...values, 1)
  if (!series.length) return <EmptyPanelState message="No series data recorded for this period." icon="📊" />
  return (
    <div className="series-bars-wrapper">
      <div className="series-bars" aria-label={`${valueKey} chart`}>
        {series.slice(-12).map((item, index) => {
          const value = Number(item[valueKey] ?? item.value ?? item.count ?? 0)
          return (
            <div className="series-bar-item" key={`${item.period || item.status || item.label}-${index}`} title={`${item.period || item.status || item.label}: ${value}`}>
              <div className="series-bar-track"><div className="series-bar-fill" style={{ height: `${Math.max(5, (value / max) * 100)}%` }} /></div>
              <span>{String(item.period || item.status || item.label || '').slice(0, 10)}</span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function DashboardView() {
  const { dashboardKey } = useParams()
  const dashboard = DASHBOARDS.find((item) => item.key === dashboardKey) || DASHBOARDS[0]
  const [filters, setFilters] = useState({ date_from: '', date_to: '' })
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const query = useMemo(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => value)), [filters])

  useEffect(() => {
    let mounted = true
    setLoading(true)
    setError('')
    reportingService.dashboard(dashboard.key, query)
      .then((data) => mounted && setPayload(data))
      .catch((requestError) => mounted && setError(requestError.response?.data?.message || 'Dashboard data could not be loaded.'))
      .finally(() => mounted && setLoading(false))
    return () => { mounted = false }
  }, [dashboard.key, query])

  const trendSeries = payload?.series?.sales_by_date || payload?.series?.planning_by_period || payload?.series?.completion_by_date || payload?.series?.orders_by_date || payload?.series?.movements_by_type || []
  const trendKey = payload?.series?.planning_by_period ? 'required_quantity' : payload?.series?.completion_by_date ? 'completed_quantity' : payload?.series?.orders_by_date ? 'purchase_value' : payload?.series?.movements_by_type ? 'quantity' : 'sales_value'
  const statusSeries = payload?.series?.orders_by_status || payload?.tables?.orders_by_status || payload?.tables?.status_breakdown || payload?.series?.movements_by_type || []
  const detailRows = payload?.tables?.shortages || payload?.tables?.stock_by_warehouse || payload?.tables?.receivables_by_party || []

  const DASHBOARD_TABS = [
    { key: 'executive', label: '👑 Executive (CEO)', path: '/dashboards/executive' },
    { key: 'supply_chain', label: '⛓️ Supply Chain', path: '/dashboards/supply-chain' },
    { key: 'production', label: '🏭 Production', path: '/dashboards/production' },
    { key: 'procurement', label: '📦 Procurement', path: '/dashboards/procurement' },
    { key: 'warehouse', label: '🏢 Warehouse', path: '/dashboards/warehouse' },
  ]

  return (
    <section className="dashboard-page">
      {/* 5-Role Dashboard Switcher Tabs */}
      <div className="dashboard-switcher-tabs">
        {DASHBOARD_TABS.map((tab) => (
          <Link
            className={`dashboard-switcher-tab${tab.key === dashboardKey ? ' active' : ''}`}
            key={tab.key}
            to={tab.path}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      <div className="detail-header">
        <div><p className="eyebrow">{dashboard.label} dashboard</p><h1>{dashboard.label} Control View</h1><p className="lede">{dashboard.description}</p></div>
        <div className={`detail-accent accent-${dashboard.accent}`} aria-hidden="true">{dashboard.label.slice(0, 2).toUpperCase()}</div>
      </div>
      <div className="report-toolbar dashboard-toolbar">
        <label className="filter-field">From<input aria-label="Dashboard from date" onChange={(event) => setFilters({ ...filters, date_from: event.target.value })} type="date" value={filters.date_from} /></label>
        <label className="filter-field">To<input aria-label="Dashboard to date" onChange={(event) => setFilters({ ...filters, date_to: event.target.value })} type="date" value={filters.date_to} /></label>
        <button className="secondary-button" onClick={() => setFilters({ date_from: '', date_to: '' })} type="button">Reset range</button>
      </div>
      {loading && <div className="empty-state"><p className="eyebrow">Loading live data</p><h2>Refreshing the operating picture…</h2></div>}
      {error && !loading && <div className="error-state"><strong>Unable to load dashboard</strong><p>{error}</p></div>}
      {payload && !loading && !error && <>
        <div className="metric-grid">{payload.kpis?.map((metric) => <article className="metric-card" key={metric.key}><span>{metric.label}</span><strong>{formatValue(metric.value, metric.format)}</strong>{metric.complete === false && <small>Cost data incomplete</small>}</article>)}</div>
        <div className="dashboard-content-grid">
          <article className="panel"><div className="panel-heading"><div><p className="eyebrow">Trend view</p><h2>Live operational series</h2></div><span className="muted-label">Derived from transactions</span></div><SeriesBars series={trendSeries} valueKey={trendKey} /></article>
          <article className="panel"><div className="panel-heading"><div><p className="eyebrow">Status mix</p><h2>Work in each state</h2></div></div>{statusSeries.length ? <div className="status-list">{statusSeries.map((item) => <div className="status-row" key={item.status || item.label}><span className="status-label">{String(item.status || item.label).replaceAll('_', ' ')}</span><strong>{formatValue(item.count)}</strong></div>)}</div> : <EmptyPanelState message="No status data recorded for this range." icon="📋" />}</article>
        </div>
        <div className="dashboard-content-grid lower-grid">
          <article className="panel"><div className="panel-heading"><div><p className="eyebrow">Operational detail</p><h2>Top items and exposure</h2></div></div>{detailRows.length ? <div className="table-wrap"><table><thead><tr><th>Entity</th><th>Quantity / amount</th><th>Signal</th></tr></thead><tbody>{detailRows.slice(0, 8).map((row, index) => <tr key={row.id || row.warehouse_id || row.party_id || index}><td>{row.product || row.warehouse || row.party_name || 'Record'}</td><td>{formatValue(row.shortage_quantity ?? row.quantity_available ?? row.outstanding_amount ?? row.total_amount ?? row.required_quantity ?? 0)}</td><td><span className={`status-badge ${row.shortage_quantity ? 'status-critical' : 'status-info'}`}>{row.shortage_quantity ? 'Shortfall' : 'Live'}</span></td></tr>)}</tbody></table></div> : <EmptyPanelState message="No grouped transaction records for this range." icon="📦" />}</article>
          <article className="panel"><div className="panel-heading"><div><p className="eyebrow">Transparent BI</p><h2>Rule-based insights</h2></div></div>{payload.insights?.length ? <div className="insight-list">{payload.insights.map((insight, index) => <div className="insight-item" key={`${insight.code}-${index}`}><span className={`status-badge status-${insight.severity}`}>{insight.severity}</span><div><strong>{insight.title}</strong><p>{insight.description}</p><small>Source: {insight.source}</small></div></div>)}</div> : <EmptyPanelState message="No active rule-based risk insights detected." icon="🛡️" />}</article>
        </div>
      </>}
    </section>
  )
}
export default DashboardView
