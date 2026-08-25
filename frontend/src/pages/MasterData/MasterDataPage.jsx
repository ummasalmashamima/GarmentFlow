import { useCallback, useEffect, useMemo, useState } from 'react'
import { Navigate, useNavigate, useParams } from 'react-router-dom'
import { getMasterDataModule } from '../../constants/masterDataModules'
import masterDataService from '../../services/masterDataService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }

function initialForm(module) {
  return Object.fromEntries(module.fields.map((field) => [field.name, field.name === 'status' ? 'active' : '']))
}

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]

  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function displayValue(record, column) {
  if (column.render) {
    return column.render(record)
  }

  if (column.key === 'created_at' && record[column.key]) {
    return new Date(record[column.key]).toLocaleDateString()
  }

  return record[column.key] ?? '—'
}

function MasterDataPage() {
  const { resource } = useParams()
  const navigate = useNavigate()
  const module = getMasterDataModule(resource)
  const [page, setPage] = useState(emptyPage)
  const [query, setQuery] = useState({ search: '', status: '', page: 1, per_page: 15, sort: 'id', direction: 'desc' })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [modal, setModal] = useState(null)
  const [selected, setSelected] = useState(null)
  const [form, setForm] = useState(() => (module ? initialForm(module) : {}))
  const [options, setOptions] = useState({})

  const relationResources = useMemo(() => {
    if (!module) return []

    return [...new Set(module.fields.filter((field) => field.relation).map((field) => field.relation))]
  }, [module])

  const loadRecords = useCallback(async () => {
    if (!module) return

    setLoading(true)
    setError('')

    try {
      const response = await masterDataService.list(resource, query)
      setPage(response)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [module, query, resource])

  useEffect(() => {
    let active = true

    Promise.resolve().then(() => {
      if (active) loadRecords()
    })

    return () => { active = false }
  }, [loadRecords])

  useEffect(() => {
    let cancelled = false

    async function loadOptions() {
      if (!relationResources.length) {
        setOptions({})
        return
      }

      try {
        const entries = await Promise.all(relationResources.map(async (relation) => [relation, await masterDataService.options(relation)]))
        if (!cancelled) setOptions(Object.fromEntries(entries))
      } catch (requestError) {
        if (!cancelled) setError(errorMessage(requestError))
      }
    }

    loadOptions()

    return () => { cancelled = true }
  }, [relationResources])

  if (!module) {
    return <Navigate replace to="/master-data" />
  }

  const updateQuery = (changes) => setQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  const openCreate = () => {
    setSelected(null)
    setForm(initialForm(module))
    setError('')
    setNotice('')
    setModal('form')
  }

  const openEdit = (record) => {
    setSelected(record)
    setForm(Object.fromEntries(module.fields.map((field) => [field.name, record[field.name] ?? ''])))
    setError('')
    setNotice('')
    setModal('form')
  }

  const openDetails = async (record) => {
    setLoading(true)
    setError('')

    try {
      const detail = await masterDataService.get(resource, record.id)
      setSelected(detail)
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }

  const closeModal = () => {
    setModal(null)
    setSelected(null)
  }

  const handleChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    setNotice('')

    const payload = Object.fromEntries(Object.entries(form).map(([key, value]) => [key, value === '' ? null : value]))

    try {
      if (selected) {
        await masterDataService.update(resource, selected.id, payload)
        setNotice(`${module.singular} updated successfully.`)
      } else {
        await masterDataService.create(resource, payload)
        setNotice(`${module.singular} created successfully.`)
      }

      closeModal()
      await loadRecords()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const handleRemove = async (record) => {
    if (!window.confirm(`Delete or deactivate ${record.name || record.sku || record.code}?`)) return

    setError('')
    setNotice('')

    try {
      const response = await masterDataService.remove(resource, record.id)
      setNotice(response.message || `${module.singular} removed successfully.`)
      await loadRecords()
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  const toggleSort = (column) => {
    if (column.render) return

    setQuery((current) => ({
      ...current,
      sort: column.key,
      direction: current.sort === column.key && current.direction === 'asc' ? 'desc' : 'asc',
      page: 1,
    }))
  }

  const records = page.data || []
  const meta = page.meta || emptyPage.meta

  return (
    <div className="master-data-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 2 · Master data</p>
          <h1>{module.label}</h1>
          <p>{module.description}</p>
        </div>
        <button className="primary-button" onClick={openCreate} type="button">Add {module.singular}</button>
      </div>

      <div className="master-data-toolbar">
        <label className="search-field">
          <span>Search</span>
          <input onChange={(event) => updateQuery({ search: event.target.value })} placeholder={`Search ${module.label.toLowerCase()}`} value={query.search} />
        </label>
        <label className="filter-field">
          <span>Status</span>
          <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <span className="record-count">{meta.total || 0} records</span>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}
      {notice && <div className="feedback-message success-message" role="status">{notice}</div>}

      <section className="data-card" aria-busy={loading}>
        <div className="data-card-header">
          <div>
            <p className="eyebrow">Operational reference</p>
            <h2>{module.label} register</h2>
          </div>
          <span className="data-card-hint">Click a row to view details</span>
        </div>

        {loading ? <div className="empty-state">Loading {module.label.toLowerCase()}…</div> : records.length === 0 ? <div className="empty-state">No {module.label.toLowerCase()} match the current filters.</div> : (
          <div className="table-wrap">
            <table className="master-data-table">
              <thead>
                <tr>
                  {module.columns.map((column) => <th key={column.key}><button disabled={Boolean(column.render)} onClick={() => toggleSort(column)} type="button">{column.label}{query.sort === column.key ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}</button></th>)}
                  <th><span className="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                {records.map((record) => (
                  <tr key={record.id} onClick={() => openDetails(record)}>
                    {module.columns.map((column) => <td key={column.key}>{column.key === 'status' ? <span className={`status-pill ${record.status}`}>{record.status}</span> : displayValue(record, column)}</td>)}
                    <td className="table-actions" onClick={(event) => event.stopPropagation()}>
                      <button className="text-button" onClick={() => openEdit(record)} type="button">Edit</button>
                      <button className="text-button danger-text" onClick={() => handleRemove(record)} type="button">Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="pagination-bar">
          <span>Page {meta.current_page || 1} of {meta.last_page || 1}</span>
          <div>
            <button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) - 1 })} type="button">Previous</button>
            <button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) + 1 })} type="button">Next</button>
          </div>
        </div>
      </section>

      {modal === 'form' && <div className="modal-backdrop" role="presentation">
        <div className="modal-card" role="dialog" aria-modal="true" aria-labelledby="master-data-form-title">
          <div className="modal-header"><div><p className="eyebrow">Master data</p><h2 id="master-data-form-title">{selected ? `Edit ${module.singular}` : `Add ${module.singular}`}</h2></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close form">×</button></div>
          <form className="master-data-form" onSubmit={handleSubmit}>
            <div className="form-grid">
              {module.fields.map((field) => (
                <label className={`form-field${field.type === 'textarea' ? ' full-width' : ''}`} key={field.name}>
                  <span>{field.label}{field.required ? ' *' : ''}</span>
                  {field.type === 'textarea' ? <textarea name={field.name} onChange={handleChange} rows="3" value={form[field.name] ?? ''} /> : field.type === 'select' ? <select name={field.name} onChange={handleChange} required={field.required} value={form[field.name] ?? ''}>{field.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select> : field.type === 'relation' ? <select name={field.name} onChange={handleChange} required={field.required} value={form[field.name] ?? ''}><option value="">Select {field.label.toLowerCase()}</option>{(options[field.relation] || []).map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select> : <input name={field.name} onChange={handleChange} required={field.required} step={field.type === 'number' ? 'any' : undefined} type={field.type === 'number' ? 'number' : field.type} value={form[field.name] ?? ''} />}
                </label>
              ))}
            </div>
            <div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={saving} type="submit">{saving ? 'Saving…' : selected ? 'Save changes' : `Create ${module.singular}`}</button></div>
          </form>
        </div>
      </div>}

      {modal === 'details' && selected && <div className="modal-backdrop" role="presentation">
        <div className="modal-card details-card" role="dialog" aria-modal="true" aria-labelledby="master-data-details-title">
          <div className="modal-header"><div><p className="eyebrow">Record detail</p><h2 id="master-data-details-title">{selected.name || selected.variant_name || selected.code || selected.sku}</h2></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close details">×</button></div>
          <dl className="details-list">{module.fields.map((field) => <div key={field.name}><dt>{field.label}</dt><dd>{field.type === 'relation' ? selected[field.name.replace('_id', '')]?.name || selected[field.name] || '—' : selected[field.name] ?? '—'}</dd></div>)}</dl>
          <div className="modal-actions"><button className="secondary-button" onClick={() => openEdit(selected)} type="button">Edit record</button><button className="primary-button" onClick={closeModal} type="button">Close</button></div>
        </div>
      </div>}

      <button className="back-link" onClick={() => navigate('/master-data')} type="button">← Back to all master data</button>
    </div>
  )
}

export default MasterDataPage
