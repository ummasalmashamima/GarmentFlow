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

function SimpleBars({ data, valueKeys, labelKeys }) {
  if (!data.length) {
    return <p className="muted-label">No data available for this period.</p>
  }

  const values = data.map((item) => getValue(item, valueKeys))
  const max = Math.max(...values, 1)

  return (
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
  )
}

function StatusList({ data }) {
  if (!data.length) {
    return <p className="muted-label">No status data available.</p>
  }

  return (
    <div className="status-list">
      {data.map((item, index) => {
        const label =
          item?.status ||
          item?.label ||
          item?.name ||
          `Status ${index + 1}`

        const count =
          item?.count ??
          item?.quantity ??
          item?.total ??
          item?.value ??
          0

        return (
          <div className="status-row" key={`${label}-${index}`}>
            <span>{label}</span>
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
    'orders_by_date',
    'revenue_by_date',
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
          valueKeys={[
            'sales_value',
            'revenue',
            'total_amount',
            'purchase_value',
            'value',
          ]}
          labelKeys={['period', 'date', 'label']}
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
          valueKeys={[
            'completed_quantity',
            'production_quantity',
            'quantity',
            'value',
          ]}
          labelKeys={['period', 'date', 'label']}
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
          valueKeys={[
            'inventory_value',
            'quantity_available',
            'quantity',
            'value',
          ]}
          labelKeys={['period', 'date', 'warehouse', 'label']}
        />
      </article>
    </div>
  )
}

export default ExecutiveCharts
