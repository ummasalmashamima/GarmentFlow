import { useMemo } from 'react'

function formatNumber(value) {
  return Number(value ?? 0).toLocaleString()
}

function formatAmount(value) {
  return `৳${Number(value ?? 0).toLocaleString(undefined, {
    maximumFractionDigits: 0,
  })}`
}

function ExecutiveKpis({ payload }) {
  const kpis = useMemo(() => {
    const source = payload?.kpis || []

    return {
      revenue: source.find((item) => item.key === 'total_revenue')?.value ?? 0,
      sales: source.find((item) => item.key === 'total_sales')?.value ?? 0,
      inventory: source.find((item) => item.key === 'inventory_value')?.value ?? 0,
      orders: source.find((item) => item.key === 'total_orders')?.value ?? 0,
      production: source.find((item) => item.key === 'production_value')?.value ?? 0,
      purchase: source.find((item) => item.key === 'purchase_value')?.value ?? 0,
      margin: source.find((item) => item.key === 'gross_margin')?.value ?? 0,
      delivery: source.find((item) => item.key === 'on_time_delivery')?.value ?? 0,
    }
  }, [payload])

  const cards = [
    {
      label: 'Total Revenue',
      value: formatAmount(kpis.revenue),
      icon: '৳',
    },
    {
      label: 'Total Sales',
      value: formatAmount(kpis.sales),
      icon: '↗',
    },
    {
      label: 'Inventory Value',
      value: formatAmount(kpis.inventory),
      icon: '▣',
    },
    {
      label: 'Total Orders',
      value: formatNumber(kpis.orders),
      icon: '◫',
    },
    {
      label: 'Production Value',
      value: formatAmount(kpis.production),
      icon: '⚙',
    },
    {
      label: 'Purchase Value',
      value: formatAmount(kpis.purchase),
      icon: '🛒',
    },
    {
      label: 'Gross Margin',
      value: `${Number(kpis.margin).toFixed(1)}%`,
      icon: '%',
    },
    {
      label: 'On-time Delivery',
      value: `${Number(kpis.delivery).toFixed(1)}%`,
      icon: '✓',
    },
  ]

  return (
    <div className="metric-grid executive-kpi-grid">
      {cards.map((card) => (
        <article className="metric-card executive-kpi-card" key={card.label}>
          <div className="kpi-icon">{card.icon}</div>
          <div>
            <span>{card.label}</span>
            <strong>{card.value}</strong>
          </div>
        </article>
      ))}
    </div>
  )
}

export default ExecutiveKpis
