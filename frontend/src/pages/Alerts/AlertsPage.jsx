import { useCallback, useEffect, useMemo, useState } from 'react'
import reportingService from '../../services/reportingService'

function AlertsPage() {
  const [filters, setFilters] = useState({ severity: '', read: '' })
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')

  const query = useMemo(
    () => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    [filters]
  )

  const load = useCallback(() => {
    setLoading(true)
    setError('')
    reportingService
      .alerts(query)
      .then(setPayload)
      .catch((requestError) => {
        setError(requestError.response?.data?.message || 'Alerts could not be loaded.')
      })
      .finally(() => setLoading(false))
  }, [query])

  useEffect(() => {
    load()
  }, [load])

  const refresh = async () => {
    setRefreshing(true)
    setError('')
    try {
      await reportingService.refreshAlerts()
      load()
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Alert rules could not be refreshed.')
    } finally {
      setRefreshing(false)
    }
  }

  const setState = async (alert) => {
    try {
      await reportingService.setAlertState(alert.id, !alert.is_read)
      setPayload((current) =>
        current
          ? {
              ...current,
              data: current.data.map((item) =>
                item.id === alert.id ? { ...item, is_read: !item.is_read } : item
              ),
            }
          : current
      )
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Alert state could not be updated.')
    }
  }

  const alerts = payload?.data || []
  const criticalCount = alerts.filter((a) => a.severity === 'critical').length
  const warningCount = alerts.filter((a) => a.severity === 'warning').length
  const unreadCount = alerts.filter((a) => !a.is_read).length

  return (
    <div className="alerts-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 12 · Operational Risk Control</p>
          <h1>Risks & Central Alerts</h1>
          <p>
            Deterministic rule-based operational signals evaluating inventory levels, production schedules, quality thresholds, and delivery milestones.
          </p>
        </div>
        <button
          className="secondary-button"
          disabled={refreshing || loading}
          onClick={refresh}
          type="button"
        >
          {refreshing ? 'Evaluating Rules…' : '↻ Re-evaluate Risk Rules'}
        </button>
      </div>

      {/* KPI Overview Summary */}
      <div className="summary-grid alerts-summary-grid">
        <div className="summary-card">
          <span className="summary-label">Total Signals</span>
          <strong className="summary-value">{payload?.total || alerts.length}</strong>
          <span className="summary-subtext">Active operational rules</span>
        </div>
        <div className="summary-card alert-kpi-critical">
          <span className="summary-label">Critical Risks</span>
          <strong className="summary-value">{criticalCount}</strong>
          <span className="summary-subtext">Requires immediate intervention</span>
        </div>
        <div className="summary-card alert-kpi-warning">
          <span className="summary-label">Warnings</span>
          <strong className="summary-value">{warningCount}</strong>
          <span className="summary-subtext">Threshold warning notices</span>
        </div>
        <div className="summary-card alert-kpi-unread">
          <span className="summary-label">Unread Signals</span>
          <strong className="summary-value">{unreadCount}</strong>
          <span className="summary-subtext">Awaiting acknowledgement</span>
        </div>
      </div>

      {/* Filters Toolbar */}
      <div className="master-data-toolbar alerts-toolbar">
        <label className="filter-field">
          <span>Filter Severity</span>
          <select
            aria-label="Alert severity"
            onChange={(event) => setFilters({ ...filters, severity: event.target.value })}
            value={filters.severity}
          >
            <option value="">All Severities</option>
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
        </label>
        <label className="filter-field">
          <span>Read Status</span>
          <select
            aria-label="Alert read state"
            onChange={(event) => setFilters({ ...filters, read: event.target.value })}
            value={filters.read}
          >
            <option value="">All States</option>
            <option value="0">Unread</option>
            <option value="1">Read</option>
          </select>
        </label>
        <span className="record-count">{alerts.length} Signal{alerts.length === 1 ? '' : 's'} displayed</span>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}

      <section className="data-card alerts-container-card" aria-busy={loading}>
        <div className="data-card-header">
          <div>
            <p className="eyebrow">Real-time signal feed</p>
            <h2>Active Trigger Register</h2>
          </div>
          <span className="data-card-hint">Read states are saved per user account</span>
        </div>

        {loading ? (
          <div className="empty-state">
            <div className="empty-state-icon">⏳</div>
            <h3>Evaluating system risk rules…</h3>
            <p>Auditing stock shortages, order lead times, and dispatch checkpoints.</p>
          </div>
        ) : alerts.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state-icon">🛡️</div>
            <h3>No Active Risk Alerts</h3>
            <p>All supply chain stages, inventory balances, and production milestones are running normally.</p>
          </div>
        ) : (
          <div className="alert-cards-feed">
            {alerts.map((alert) => {
              const severity = alert.severity || 'info'
              const relatedEntity = alert.related_type ? alert.related_type.split('\\').pop() : 'System'

              return (
                <div
                  key={alert.id}
                  className={`alert-feed-card severity-${severity} ${alert.is_read ? 'is-read' : 'is-unread'}`}
                >
                  <div className="alert-feed-badge-col">
                    <span className={`alert-pill pill-${severity}`}>
                      {severity === 'critical' && '● '}
                      {severity.toUpperCase()}
                    </span>
                  </div>

                  <div className="alert-feed-content">
                    <div className="alert-feed-header">
                      <h4 className="alert-feed-title">{alert.title}</h4>
                      <span className="alert-feed-time">
                        {alert.occurred_at
                          ? new Date(alert.occurred_at).toLocaleString(undefined, {
                              dateStyle: 'medium',
                              timeStyle: 'short',
                            })
                          : 'Recent'}
                      </span>
                    </div>

                    <p className="alert-feed-desc">{alert.description || alert.message}</p>

                    <div className="alert-feed-meta">
                      {alert.rule_code && (
                        <span className="meta-tag">
                          <code>{alert.rule_code}</code>
                        </span>
                      )}
                      <span className="meta-tag">
                        Scope: <strong>{relatedEntity}</strong> {alert.related_id ? `#${alert.related_id}` : ''}
                      </span>
                      {alert.role_slug && (
                        <span className="meta-tag">
                          Role: <strong>{alert.role_slug}</strong>
                        </span>
                      )}
                    </div>
                  </div>

                  <div className="alert-feed-actions">
                    <button
                      className={`action-btn ${alert.is_read ? 'btn-ghost' : 'btn-acknowledge'}`}
                      onClick={() => setState(alert)}
                      type="button"
                    >
                      {alert.is_read ? 'Mark Unread' : 'Acknowledge'}
                    </button>
                  </div>
                </div>
              )
            })}
          </div>
        )}

        {payload?.last_page > 1 && (
          <div className="pagination-bar">
            <span>Page {payload.current_page || 1} of {payload.last_page || 1}</span>
          </div>
        )}
      </section>
    </div>
  )
}

export default AlertsPage
