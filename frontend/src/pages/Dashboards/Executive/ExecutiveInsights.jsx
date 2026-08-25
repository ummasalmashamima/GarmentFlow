function ExecutiveInsights({ payload }) {
  const insights = payload?.insights || []

  return (
    <section className="panel executive-insights">
      <div className="panel-heading">
        <div>
          <p className="eyebrow">Management intelligence</p>
          <h2>Smart business insights</h2>
        </div>

        <span className="muted-label">
          Rule-based operational analysis
        </span>
      </div>

      {!insights.length ? (
        <div className="empty-state">
          <strong>No active insights</strong>
          <p>
            Insights will appear here when the system detects important
            business conditions.
          </p>
        </div>
      ) : (
        <div className="insight-list">
          {insights.map((insight, index) => {
            const severity = insight.severity || 'info'

            return (
              <div
                className="insight-item"
                key={`${insight.code || insight.title || 'insight'}-${index}`}
              >
                <span className={`status-badge status-${severity}`}>
                  {severity}
                </span>

                <div>
                  <strong>
                    {insight.title || 'Business insight'}
                  </strong>

                  <p>
                    {insight.description ||
                      insight.message ||
                      'No additional description available.'}
                  </p>

                  {insight.source && (
                    <small>
                      Source: {insight.source}
                    </small>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </section>
  )
}

export default ExecutiveInsights
