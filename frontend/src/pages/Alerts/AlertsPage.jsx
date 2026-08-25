import { useCallback, useEffect, useMemo, useState } from 'react'
import reportingService from '../../services/reportingService'

function AlertsPage() {
  const [filters, setFilters] = useState({ severity: '', read: '' })
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const query = useMemo(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')), [filters])
  const load = useCallback(() => {
    setLoading(true)
    setError('')
    reportingService.alerts(query).then(setPayload).catch((requestError) => setError(requestError.response?.data?.message || 'Alerts could not be loaded.')).finally(() => setLoading(false))
  }, [query])
  useEffect(() => { load() }, [load])
  const refresh = async () => {
    setRefreshing(true)
    try { await reportingService.refreshAlerts(); load() } catch (requestError) { setError(requestError.response?.data?.message || 'Alert rules could not be refreshed.') } finally { setRefreshing(false) }
  }
  const setState = async (alert) => {
    try {
      await reportingService.setAlertState(alert.id, !alert.is_read)
      setPayload((current) => current ? { ...current, data: current.data.map((item) => item.id === alert.id ? { ...item, is_read: !item.is_read } : item) } : current)
    } catch (requestError) { setError(requestError.response?.data?.message || 'Alert state could not be updated.') }
  }

  return (
    <section className="alerts-page">
      <div className="page-intro"><div><p className="eyebrow">Phase 12 · Control signals</p><h1>Central alerts.</h1><p className="lede">Rule-based operational signals with an explicit reason, source entity, severity, and your own read state. No predictions are presented as facts.</p></div><div className="intro-mark" aria-hidden="true"><span>BI</span><strong>Rules</strong></div></div>
      <div className="report-toolbar"><label className="filter-field">Severity<select aria-label="Alert severity" onChange={(event) => setFilters({ ...filters, severity: event.target.value })} value={filters.severity}><option value="">All severities</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="info">Info</option></select></label><label className="filter-field">Read state<select aria-label="Alert read state" onChange={(event) => setFilters({ ...filters, read: event.target.value })} value={filters.read}><option value="">All states</option><option value="0">Unread</option><option value="1">Read</option></select></label><button className="secondary-button" disabled={refreshing} onClick={refresh} type="button">{refreshing ? 'Refreshing…' : 'Refresh rules'}</button></div>
      {loading && <div className="empty-state"><p className="eyebrow">Loading central alerts</p><h2>Evaluating relevant rules…</h2></div>}
      {error && !loading && <div className="error-state"><strong>Alerts unavailable</strong><p>{error}</p></div>}
      {payload && !loading && !error && <article className="panel alert-panel"><div className="panel-heading"><div><p className="eyebrow">Live signal register</p><h2>{payload.total || 0} relevant alert(s)</h2></div><span className="muted-label">Read state is private to your account</span></div>{payload.data?.length ? <div className="alert-list">{payload.data.map((alert) => <div className={`alert-item ${alert.is_read ? 'read' : ''}`} key={alert.id}><div className="alert-severity"><span className={`status-badge status-${alert.severity}`}>{alert.severity}</span></div><div className="alert-copy"><strong>{alert.title}</strong><p>{alert.description}</p><small>{alert.rule_code} · {alert.related_type?.split('\\').pop()} #{alert.related_id || '—'} · {alert.occurred_at ? new Date(alert.occurred_at).toLocaleString() : 'No timestamp'}</small></div><button className="secondary-button" onClick={() => setState(alert)} type="button">{alert.is_read ? 'Mark unread' : 'Mark read'}</button></div>)}</div> : <div className="empty-inline"><strong>No active alerts match these filters.</strong><span>The register is derived from current transactional conditions.</span></div>}<div className="pagination-bar"><span>Page {payload.current_page || 1} of {payload.last_page || 1}</span></div></article>}
    </section>
  )
}
export default AlertsPage
