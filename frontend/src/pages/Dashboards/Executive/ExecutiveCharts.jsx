function getSeries(payload, possibleKeys = []) {
  for (const key of possibleKeys) {
    if (payload?.series?.[key]?.length) {
      return payload.series[key]
    }
  }

  return []
}

function getValue(item, keys = []) {
  for (const key of keys) {
    if (item?.[key] !== undefined && item?.[key] !== null) {
      return Number(item[key])
    }
  }

  return 0
}

function formatNumber(value) {
  return Number(value ?? 0).toLocaleString()
}

function EmptyPanelState({ message, icon = '📊' }) {
  return (
    <div className="panel-empty-state">
      <span className="panel-empty-icon">{icon}</span>
      <p>{message}</p>
    </div>
  )
}

function SimpleBars({ data, valueKeys, labelKeys, emptyMessage = 'No data available for this period.' }) {
  if (!data.length) {
    return <EmptyPanelState message={emptyMessage} icon="📊" />
  }

  const values = data.map((item) => getValue(item, valueKeys))
  const max = Math.max(...values, 1)

  return (
    <div className="series-bars-wrapper">
      <div className="series-bars">
        {data.slice(-10).map((item, index) => {
          const value = getValue(item, valueKeys)
          const label =
            labelKeys.map((key) => item?.[key]).find(Boolean) || `Item ${index + 1}`

          return (
            <div
              className="series-bar-item"
              key={`${label}-${index}`}
              title={`${label}: ${formatNumber(value)}`}
            >
              <div className="series-bar-track">
                <div
                  className="series-bar-fill"
                  style={{
                    height: `${Math.max(6, (value / max) * 100)}%`,
                  }}
                />
              </div>

              <span>{String(label).slice(0, 10)}</span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function StatusList({ data }) {
  if (!data.length) {
    return <EmptyPanelState message="No order status data available." icon="📋" />
  }

  return (
    <div className="status-list">
      {data.map((item, index) => {
        const label = item.status || item.label || `State ${index + 1}`
        const count =
          item.count ?? item.total_orders ?? item.orders_count ?? item.value ?? 0

        return (
          <div className="status-row" key={`${label}-${index}`}>
            <span className="status-label">{label.replaceAll('_', ' ')}</span>
            <strong>{formatNumber(count)}</strong>
          </div>
        )
      })}
    </div>
  )
}

function ExecutiveCharts({ payload }) {
  const salesSeries = getSeries(payload, [
    'sales_by_date',
    'revenue_by_date',
    'orders_trend',
    'sales_trend',
  ])

  const productionSeries = getSeries(payload, [
    'production_by_date',
    'completion_by_date',
    'production_trend',
  ])

  const orderStatus = getSeries(payload, [
    'orders_by_status',
    'order_status',
  ])

  const inventorySeries = getSeries(payload, [
    'inventory_by_date',
    'inventory_trend',
    'stock_by_date',
  ])

  return (
    <div className="dashboard-content-grid executive-charts-grid">
      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Revenue & Sales</p>
            <h2>Business performance trend</h2>
          </div>
          <span className="muted-label">Live transactions</span>
        </div>

        <SimpleBars
          data={salesSeries}
          emptyMessage="No sales transaction data recorded for this period."
          labelKeys={['period', 'date', 'label']}
          valueKeys={[
            'sales_value',
            'revenue',
            'total_amount',
            'purchase_value',
            'value',
          ]}
        />
      </article>

      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Order Status</p>
            <h2>Orders by current state</h2>
          </div>
        </div>

        <StatusList data={orderStatus} />
      </article>

      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Production</p>
            <h2>Production performance</h2>
          </div>
          <span className="muted-label">Planned vs completed</span>
        </div>

        <SimpleBars
          data={productionSeries}
          emptyMessage="No production output data recorded for this period."
          labelKeys={['period', 'date', 'label']}
          valueKeys={[
            'completed_quantity',
            'production_quantity',
            'quantity',
            'value',
          ]}
        />
      </article>

      <article className="panel">
        <div className="panel-heading">
          <div>
            <p className="eyebrow">Inventory</p>
            <h2>Inventory health trend</h2>
          </div>
        </div>

        <SimpleBars
          data={inventorySeries}
          emptyMessage="No inventory movements recorded for this period."
          labelKeys={['period', 'date', 'warehouse', 'label']}
          valueKeys={[
            'inventory_value',
            'quantity_available',
            'quantity',
            'value',
          ]}
        />
      </article>
    </div>
  )
}

export default ExecutiveCharts
