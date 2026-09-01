import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { DASHBOARDS } from '../../../constants/dashboards'
import reportingService from '../../../services/reportingService'

import ExecutiveKpis from './ExecutiveKpis'
import ExecutiveCharts from './ExecutiveCharts'
import ExecutiveAlerts from './ExecutiveAlerts'
import ExecutiveInsights from './ExecutiveInsights'

function ExecutiveDashboard() {
  const dashboard = DASHBOARDS.find((item) => item.key === 'executive')

  const [filters, setFilters] = useState({
    date_from: '',
    date_to: '',
  })

  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const query = useMemo(
    () =>
      Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value)
      ),
    [filters]
  )

  useEffect(() => {
    let mounted = true

    setLoading(true)
    setError('')

    reportingService
      .dashboard('executive', query)
      .then((data) => {
        if (mounted) {
          setPayload(data)
        }
      })
      .catch((requestError) => {
        if (mounted) {
          setError(
            requestError.response?.data?.message ||
              'Executive dashboard data could not be loaded.'
          )
        }
      })
      .finally(() => {
        if (mounted) {
          setLoading(false)
        }
      })

    return () => {
      mounted = false
    }
  }, [query])

  const resetFilters = () => {
    setFilters({
      date_from: '',
      date_to: '',
    })
  }

  const DASHBOARD_TABS = [
    { key: 'executive', label: '👑 Executive (CEO)', path: '/dashboards/executive' },
    { key: 'supply-chain', label: '⛓️ Supply Chain', path: '/dashboards/supply-chain' },
    { key: 'production', label: '🏭 Production', path: '/dashboards/production' },
    { key: 'procurement', label: '📦 Procurement', path: '/dashboards/procurement' },
    { key: 'warehouse', label: '🏢 Warehouse', path: '/dashboards/warehouse' },
  ]

  return (
    <section className="dashboard-page executive-dashboard">
      {/* 5-Role Dashboard Switcher Tabs */}
      <div className="dashboard-switcher-tabs">
        {DASHBOARD_TABS.map((tab) => (
          <Link
            className={`dashboard-switcher-tab${tab.key === 'executive' ? ' active' : ''}`}
            key={tab.key}
            to={tab.path}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      <div className="detail-header">
        <div>
          <p className="eyebrow">
            Executive / CEO dashboard
          </p>

          <h1>
            Enterprise Control Center
          </h1>

          <p className="lede">
            {dashboard?.description ||
              'Enterprise-wide visibility across orders, finance, inventory and operations.'}
          </p>
        </div>

        <div
          className={`detail-accent accent-${dashboard?.accent || 'amber'}`}
          aria-hidden="true"
        >
          EX
        </div>
      </div>

      <div className="report-toolbar dashboard-toolbar">
        <label className="filter-field">
          From

          <input
            aria-label="Executive dashboard from date"
            type="date"
            value={filters.date_from}
            onChange={(event) =>
              setFilters({
                ...filters,
                date_from: event.target.value,
              })
            }
          />
        </label>

        <label className="filter-field">
          To

          <input
            aria-label="Executive dashboard to date"
            type="date"
            value={filters.date_to}
            onChange={(event) =>
              setFilters({
                ...filters,
                date_to: event.target.value,
              })
            }
          />
        </label>

        <button
          className="secondary-button"
          type="button"
          onClick={resetFilters}
        >
          Reset range
        </button>
      </div>

      {loading && (
        <div className="empty-state">
          <p className="eyebrow">
            Loading live data
          </p>

          <h2>
            Refreshing the executive operating picture…
          </h2>
        </div>
      )}

      {error && !loading && (
        <div className="error-state">
          <strong>
            Unable to load Executive Dashboard
          </strong>

          <p>
            {error}
          </p>
        </div>
      )}

      {!loading && !error && payload && (
        <>
          <ExecutiveKpis payload={payload} />

          <ExecutiveCharts payload={payload} />

          <ExecutiveAlerts payload={payload} />

          <ExecutiveInsights payload={payload} />
        </>
      )}
    </section>
  )
}

export default ExecutiveDashboard
