import { useEffect, useMemo, useState } from 'react'
import reportingService from '../../services/reportingService'

const REPORTS = [
  ['sales', 'Sales Report'],
  ['purchase', 'Purchase Report'],
  ['stock', 'Stock Report'],
  ['profit', 'Profit & Loss Report'],
  ['production', 'Production Report'],
  ['payment', 'Payment Report'],
  ['delivery', 'Delivery Report'],
  ['inventory-movement', 'Inventory Movement Report'],
  ['supplier-performance', 'Supplier Performance Report'],
  ['customer', 'Customer/Buyer Report'],
]

function display(value) {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return Number(value).toLocaleString()
  return String(value)
}

function labelFor(key) {
  return key.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function ReportsPage() {
  const [report, setReport] = useState(REPORTS[0][0])
  const [filters, setFilters] = useState({ date_from: '', date_to: '', status: '', search: '', page: 1 })
  const [payload, setPayload] = useState(null)
  const [loading, setLoading] = useState(true)
  const [exporting, setExporting] = useState(false)
  const [error, setError] = useState('')

  const query = useMemo(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null)), [filters])

  useEffect(() => {
    let mounted = true
    setLoading(true)
    setError('')
    reportingService.report(report, query)
      .then((data) => mounted && setPayload(data))
      .catch((requestError) => mounted && setError(requestError.response?.data?.message || 'Report could not be generated.'))
      .finally(() => mounted && setLoading(false))
    return () => { mounted = false }
  }, [report, query])

  const rows = payload?.rows?.data || []
  const columns = rows.length ? Object.keys(rows[0]) : payload?.columns || []

  const updateFilter = (key, value) => {
    setFilters((current) => ({
      ...current,
      [key]: value,
      ...(key !== 'page' ? { page: 1 } : {}),
    }))
  }

  const reset = () => {
    setFilters({ date_from: '', date_to: '', status: '', search: '', page: 1 })
  }

  const exportCsv = async () => {
    setExporting(true)
    try {
      const blob = await reportingService.exportReport(report, query)
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${report}-report.csv`
      link.click()
      window.URL.revokeObjectURL(url)
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'CSV export could not be created.')
    } finally { setExporting(false) }
  }

  return (
    <section className="reports-page">
      <div className="page-intro">
        <div>
          <p className="eyebrow">Enterprise Intelligence & Audit</p>
          <h1>Intelligence & Financial Reports</h1>
          <p className="lede">
            Real-time multi-dimensional reports covering sales, procurement, inventory, production, profit, payments, and supplier performance.
          </p>
        </div>
      </div>

      <div className="report-selector" role="tablist" aria-label="Report types">
        {REPORTS.map(([key, label]) => (
          <button
            className={report === key ? 'report-tab active' : 'report-tab'}
            key={key}
            onClick={() => { setReport(key); setFilters((current) => ({ ...current, page: 1 })) }}
            role="tab"
            type="button"
          >
            {label}
          </button>
        ))}
      </div>

      <div className="report-toolbar">
        <label className="filter-field"><span>From</span><input aria-label="Report from date" onChange={(event) => updateFilter('date_from', event.target.value)} type="date" value={filters.date_from} /></label>
        <label className="filter-field"><span>To</span><input aria-label="Report to date" onChange={(event) => updateFilter('date_to', event.target.value)} type="date" value={filters.date_to} /></label>
        <label className="filter-field"><span>Status</span><input aria-label="Report status" onChange={(event) => updateFilter('status', event.target.value)} placeholder="Any status" type="text" value={filters.status} /></label>
        <label className="search-field"><span>Search</span><input aria-label="Report search" onChange={(event) => updateFilter('search', event.target.value)} placeholder="Search records…" type="search" value={filters.search} /></label>
        <button className="secondary-button" onClick={reset} type="button">Reset</button>
        <button className="secondary-button" disabled={exporting} onClick={exportCsv} type="button">{exporting ? 'Exporting…' : 'Download CSV'}</button>
        <button className="primary-button" onClick={() => window.print()} type="button">Print / PDF</button>
      </div>

      {loading && (
        <div className="empty-state">
          <p className="eyebrow">Querying Database</p>
          <h2>Compiling real-time report…</h2>
        </div>
      )}

      {error && !loading && (
        <div className="error-state">
          <strong>Report unavailable</strong>
          <p>{error}</p>
        </div>
      )}

      {payload && !loading && !error && (
        <>
          <div className="metric-grid report-summary">
            {Object.entries(payload.summary || {}).map(([key, value]) => (
              <article className="metric-card" key={key}>
                <span>{labelFor(key)}</span>
                <strong>{typeof value === 'boolean' ? (value ? 'Yes' : 'No') : display(value)}</strong>
              </article>
            ))}
          </div>

          <article className="panel report-panel">
            <div className="panel-heading">
              <div>
                <p className="eyebrow">{payload.label}</p>
                <h2>Filtered Records</h2>
              </div>
              <span className="record-count">{payload.rows?.total || 0} total records</span>
            </div>

            {rows.length ? (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      {columns.map((column) => (
                        <th key={column}>{labelFor(column)}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row, index) => (
                      <tr key={row.id || row.supplier_id || row.party_id || index}>
                        {columns.map((column) => (
                          <td key={column}>{display(row[column])}</td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="empty-inline">
                <span className="panel-empty-icon" style={{ fontSize: '32px' }}>📊</span>
                <strong>No matching records found</strong>
                <span>Adjust your date range, status, or search filters to display transactions.</span>
              </div>
            )}

            <div className="pagination-bar">
              <span>Page {payload.rows?.current_page || 1} of {payload.rows?.last_page || 1}</span>
              <div>
                <button
                  className="secondary-button"
                  disabled={!payload.rows?.prev_page_url}
                  onClick={() => updateFilter('page', Math.max(1, (payload.rows?.current_page || 1) - 1))}
                  type="button"
                >
                  Previous
                </button>
                <button
                  className="secondary-button"
                  disabled={!payload.rows?.next_page_url}
                  onClick={() => updateFilter('page', (payload.rows?.current_page || 1) + 1)}
                  type="button"
                >
                  Next
                </button>
              </div>
            </div>
          </article>
        </>
      )}
    </section>
  )
}

export default ReportsPage
