function ExecutiveAlerts({ payload }) {
  const alerts = payload?.alerts || []

  if (!alerts.length) {
    return (
      <section className="panel executive-alerts">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Operational alerts</p>
            <h2>Risk & attention center</h2>
          </div>
        </div>

        <div className="empty-state">
          <strong>No active alerts</strong>
          <p>The current operating data has no critical alerts.</p>
        </div>
      </section>
    )
  }

  return (
    <section className="panel executive-alerts">
      <div className="panel-heading">
        <div>
          <p className="eyebrow">Operational alerts</p>
          <h2>Risk & attention center</h2>
        </div>

        <span className="muted-label">
          {alerts.length} active alert{alerts.length !== 1 ? 's' : ''}
        </span>
      </div>

      <div className="insight-list">
        {alerts.map((alert, index) => {
          const severity = alert.severity || 'info'

          return (
            <div
              className="insight-item"
              key={`${alert.code || alert.title || 'alert'}-${index}`}
            >
              <span className={`status-badge status-${severity}`}>
                {severity}
              </span>

              <div>
                <strong>
                  {alert.title || alert.name || 'Operational alert'}
                </strong>

                <p>
                  {alert.description ||
                    alert.message ||
                    'Attention is required for this operational item.'}
                </p>

                {alert.source && (
                  <small>Source: {alert.source}</small>
                )}

                {alert.count !== undefined && (
                  <small>
                    Affected records: {Number(alert.count).toLocaleString()}
                  </small>
                )}
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}

export default ExecutiveAlerts
