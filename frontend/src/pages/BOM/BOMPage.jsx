import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import bomService from '../../services/bomService'
import masterDataService from '../../services/masterDataService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }

const today = () => new Date().toISOString().slice(0, 10)

const emptyHeaderForm = () => ({
  product_id: '',
  code: '',
  name: '',
  description: '',
  effective_from: today(),
  effective_to: '',
  version_notes: '',
})

const emptyVersionForm = () => ({ effective_from: today(), effective_to: '', notes: '' })
const emptyItemForm = () => ({ material_id: '', unit_id: '', quantity: '', wastage_percentage: '0', line_number: '', notes: '' })

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]

  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function statusClass(status) {
  return `status-pill ${status || 'draft'}`
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function BOMPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(emptyPage)
  const [query, setQuery] = useState({ search: '', status: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [catalog, setCatalog] = useState({ products: [], materials: [], units: [] })
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [selectedBom, setSelectedBom] = useState(null)
  const [selectedVersionId, setSelectedVersionId] = useState(null)
  const [selectedItemId, setSelectedItemId] = useState(null)
  const [modal, setModal] = useState(null)
  const [headerForm, setHeaderForm] = useState(emptyHeaderForm)
  const [versionForm, setVersionForm] = useState(emptyVersionForm)
  const [itemForm, setItemForm] = useState(emptyItemForm)
  const [calculationForm, setCalculationForm] = useState({ order_quantity: '100' })
  const [calculation, setCalculation] = useState(null)

  const loadBoms = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const response = await bomService.list(query)
      setPage(response)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [query])

  useEffect(() => {
    let active = true

    Promise.resolve().then(() => {
      if (active) loadBoms()
    })

    return () => { active = false }
  }, [loadBoms])

  useEffect(() => {
    let active = true

    Promise.all([
      masterDataService.options('products'),
      masterDataService.options('materials'),
      masterDataService.options('units'),
    ]).then(([products, materials, units]) => {
      if (active) setCatalog({ products, materials, units })
    }).catch((requestError) => {
      if (active) setError(errorMessage(requestError))
    })

    return () => { active = false }
  }, [])

  const versions = selectedBom?.versions || []
  const selectedVersion = versions.find((version) => version.id === selectedVersionId) || versions[0] || null

  const updateQuery = (changes) => setQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  const applyDetail = (detail, preferredVersionId = null) => {
    const version = detail.versions?.find((candidate) => candidate.id === preferredVersionId)
      || detail.active_version
      || detail.versions?.[0]
      || null
    setSelectedBom(detail)
    setSelectedVersionId(version?.id || null)
    setSelectedItemId(null)
    setCalculation(null)
    return version
  }

  const refreshDetail = async (bomId, preferredVersionId = null) => {
    const detail = await bomService.get(bomId)
    applyDetail(detail, preferredVersionId)
    return detail
  }

  const openCreate = () => {
    setSelectedBom(null)
    setHeaderForm(emptyHeaderForm())
    setError('')
    setNotice('')
    setModal('header')
  }

  const openDetails = async (bom) => {
    setLoading(true)
    setError('')

    try {
      const detail = await bomService.get(bom.id)
      applyDetail(detail)
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }

  const openHeaderEdit = (bom) => {
    setSelectedBom(bom)
    setHeaderForm({ product_id: bom.product_id || '', code: bom.code || '', name: bom.name || '', description: bom.description || '', effective_from: today(), effective_to: '', version_notes: '' })
    setError('')
    setNotice('')
    setModal('header')
  }

  const openVersionForm = (version = null) => {
    setSelectedVersionId(version?.id || null)
    setVersionForm({
      effective_from: version?.effective_from || today(),
      effective_to: version?.effective_to || '',
      notes: version?.notes || '',
    })
    setError('')
    setNotice('')
    setModal('version')
  }

  const openItemForm = (item = null) => {
    setSelectedItemId(item?.id || null)
    setItemForm({
      material_id: item?.material_id || '',
      unit_id: item?.unit_id || '',
      quantity: item?.quantity ?? '',
      wastage_percentage: item?.wastage_percentage ?? '0',
      line_number: item?.line_number || '',
      notes: item?.notes || '',
    })
    setError('')
    setNotice('')
    setModal('item')
  }

  const closeModal = () => {
    setModal(null)
    setSelectedItemId(null)
  }

  const handleFormChange = (setter) => (event) => {
    const { name, value } = event.target
    setter((current) => ({ ...current, [name]: value }))
  }

  const handleHeaderSubmit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')

    try {
      const payload = selectedBom
        ? { code: headerForm.code, name: headerForm.name, description: headerForm.description || null }
        : {
            ...headerForm,
            product_id: Number(headerForm.product_id),
            effective_to: headerForm.effective_to || null,
            description: headerForm.description || null,
            version_notes: headerForm.version_notes || null,
          }
      const detail = selectedBom
        ? await bomService.update(selectedBom.id, payload)
        : await bomService.create(payload)
      applyDetail(detail)
      await loadBoms()
      setNotice(selectedBom ? 'BOM updated successfully.' : 'BOM created successfully.')
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const handleVersionSubmit = async (event) => {
    event.preventDefault()
    if (!selectedBom) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      const payload = { ...versionForm, effective_to: versionForm.effective_to || null, notes: versionForm.notes || null }
      const version = selectedVersionId
        ? await bomService.updateVersion(selectedBom.id, selectedVersionId, payload)
        : await bomService.createVersion(selectedBom.id, payload)
      await refreshDetail(selectedBom.id, version.id)
      setNotice(selectedVersionId ? 'BOM version updated successfully.' : 'BOM version created successfully.')
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const handleItemSubmit = async (event) => {
    event.preventDefault()
    if (!selectedBom || !selectedVersion) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      const payload = {
        material_id: Number(itemForm.material_id),
        unit_id: Number(itemForm.unit_id),
        quantity: Number(itemForm.quantity),
        wastage_percentage: Number(itemForm.wastage_percentage || 0),
        line_number: itemForm.line_number ? Number(itemForm.line_number) : null,
        notes: itemForm.notes || null,
      }
      if (selectedItemId) {
        await bomService.updateItem(selectedBom.id, selectedVersion.id, selectedItemId, payload)
      } else {
        await bomService.createItem(selectedBom.id, selectedVersion.id, payload)
      }
      await refreshDetail(selectedBom.id, selectedVersion.id)
      setNotice(selectedItemId ? 'BOM item updated successfully.' : 'BOM item added successfully.')
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const activateVersion = async () => {
    if (!selectedBom || !selectedVersion) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.activateVersion(selectedBom.id, selectedVersion.id)
      await refreshDetail(selectedBom.id, selectedVersion.id)
      await loadBoms()
      setNotice(`Version ${selectedVersion.version_number} activated successfully.`)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const deactivateVersion = async () => {
    if (!selectedBom || !selectedVersion) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.deactivateVersion(selectedBom.id, selectedVersion.id)
      await refreshDetail(selectedBom.id, selectedVersion.id)
      await loadBoms()
      setNotice(`Version ${selectedVersion.version_number} deactivated successfully.`)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const activateBom = async () => {
    if (!selectedBom || !selectedVersion) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.activate(selectedBom.id, { version_id: selectedVersion.id })
      await refreshDetail(selectedBom.id, selectedVersion.id)
      await loadBoms()
      setNotice('BOM activated successfully.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const deactivateBom = async () => {
    if (!selectedBom) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.deactivate(selectedBom.id)
      await refreshDetail(selectedBom.id, selectedVersionId)
      await loadBoms()
      setNotice('BOM deactivated successfully.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const removeItem = async (item) => {
    if (!selectedBom || !selectedVersion || !window.confirm(`Remove ${item.material?.name || 'this material'} from the BOM version?`)) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.removeItem(selectedBom.id, selectedVersion.id, item.id)
      await refreshDetail(selectedBom.id, selectedVersion.id)
      setNotice('BOM item removed successfully.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const removeBom = async (bom = selectedBom) => {
    if (!bom || !window.confirm(`Delete ${bom.name || bom.code}?`)) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await bomService.remove(bom.id)
      closeModal()
      setSelectedBom(null)
      await loadBoms()
      setNotice('BOM deleted successfully.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const calculate = async (event) => {
    event.preventDefault()
    if (!selectedBom || !selectedVersion) return
    setBusy(true)
    setError('')

    try {
      const result = await bomService.calculate(selectedBom.id, selectedVersion.id, Number(calculationForm.order_quantity))
      setCalculation(result)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const toggleSort = (column) => setQuery((current) => ({
    ...current,
    sort: column,
    direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
    page: 1,
  }))

  const records = page.data || []
  const meta = page.meta || emptyPage.meta

  return (
    <div className="master-data-page bom-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 3 · Product engineering</p>
          <h1>Bill of Materials</h1>
          <p>Define the materials, quantities, units, and wastage assumptions behind each garment product.</p>
        </div>
        <button className="primary-button" onClick={openCreate} type="button">Add BOM</button>
      </div>

      <div className="master-data-toolbar">
        <label className="search-field">
          <span>Search</span>
          <input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search BOMs or products" value={query.search} />
        </label>
        <label className="filter-field">
          <span>Status</span>
          <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <span className="record-count">{meta.total || 0} BOMs</span>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}
      {notice && <div className="feedback-message success-message" role="status">{notice}</div>}

      <section className="data-card" aria-busy={loading}>
        <div className="data-card-header">
          <div>
            <p className="eyebrow">Engineering register</p>
            <h2>BOM definitions</h2>
          </div>
          <span className="data-card-hint">Open a row to manage versions and material lines</span>
        </div>

        {loading ? <div className="empty-state">Loading BOMs…</div> : records.length === 0 ? <div className="empty-state">No BOMs match the current filters.</div> : (
          <div className="table-wrap">
            <table className="master-data-table bom-register-table">
              <thead>
                <tr>
                  {['code', 'name', 'status', 'created_at'].map((column) => <th key={column}><button onClick={() => toggleSort(column)} type="button">{column === 'created_at' ? 'Created' : column}{query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}</button></th>)}
                  <th>Product</th>
                  <th>Active version</th>
                  <th><span className="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                {records.map((record) => (
                  <tr key={record.id} onClick={() => openDetails(record)}>
                    <td>{record.code}</td>
                    <td>{record.name}</td>
                    <td><span className={statusClass(record.status)}>{record.status}</span></td>
                    <td>{record.created_at ? new Date(record.created_at).toLocaleDateString() : '—'}</td>
                    <td>{record.product?.name || '—'}</td>
                    <td>{record.active_version ? `v${record.active_version.version_number}` : '—'}</td>
                    <td className="table-actions" onClick={(event) => event.stopPropagation()}><button className="text-button" onClick={() => openHeaderEdit(record)} type="button">Edit</button><button className="text-button danger-text" onClick={() => removeBom(record)} type="button">Delete</button></td>
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

      {modal === 'header' && <div className="modal-backdrop" role="presentation">
        <div className="modal-card bom-modal-card" role="dialog" aria-modal="true" aria-labelledby="bom-header-title">
          <div className="modal-header"><div><p className="eyebrow">Product engineering</p><h2 id="bom-header-title">{selectedBom ? 'Edit BOM' : 'Create BOM'}</h2></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close BOM form">×</button></div>
          <form className="master-data-form" onSubmit={handleHeaderSubmit}>
            <div className="form-grid">
              {!selectedBom && <label className="form-field"><span>Product *</span><select name="product_id" onChange={handleFormChange(setHeaderForm)} required value={headerForm.product_id}><option value="">Select product</option>{catalog.products.map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select></label>}
              <label className="form-field"><span>BOM code *</span><input name="code" onChange={handleFormChange(setHeaderForm)} required value={headerForm.code} /></label>
              <label className="form-field"><span>Name *</span><input name="name" onChange={handleFormChange(setHeaderForm)} required value={headerForm.name} /></label>
              {!selectedBom && <label className="form-field"><span>Effective from *</span><input name="effective_from" onChange={handleFormChange(setHeaderForm)} required type="date" value={headerForm.effective_from} /></label>}
              {!selectedBom && <label className="form-field"><span>Effective to</span><input name="effective_to" onChange={handleFormChange(setHeaderForm)} type="date" value={headerForm.effective_to} /></label>}
              <label className="form-field full-width"><span>Description</span><textarea name="description" onChange={handleFormChange(setHeaderForm)} rows="3" value={headerForm.description} /></label>
              {!selectedBom && <label className="form-field full-width"><span>Initial version notes</span><textarea name="version_notes" onChange={handleFormChange(setHeaderForm)} rows="3" value={headerForm.version_notes} /></label>}
            </div>
            <div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Saving…' : selectedBom ? 'Save BOM' : 'Create BOM'}</button></div>
          </form>
        </div>
      </div>}

      {modal === 'version' && selectedBom && <div className="modal-backdrop" role="presentation">
        <div className="modal-card bom-modal-card" role="dialog" aria-modal="true" aria-labelledby="bom-version-title">
          <div className="modal-header"><div><p className="eyebrow">BOM revision</p><h2 id="bom-version-title">{selectedVersionId ? 'Edit version' : 'Create version'}</h2></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close version form">×</button></div>
          <form className="master-data-form" onSubmit={handleVersionSubmit}>
            <div className="form-grid">
              <label className="form-field"><span>Effective from *</span><input name="effective_from" onChange={handleFormChange(setVersionForm)} required type="date" value={versionForm.effective_from} /></label>
              <label className="form-field"><span>Effective to</span><input name="effective_to" onChange={handleFormChange(setVersionForm)} type="date" value={versionForm.effective_to} /></label>
              <label className="form-field full-width"><span>Notes</span><textarea name="notes" onChange={handleFormChange(setVersionForm)} rows="4" value={versionForm.notes} /></label>
            </div>
            <div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Saving…' : selectedVersionId ? 'Save version' : 'Create version'}</button></div>
          </form>
        </div>
      </div>}

      {modal === 'item' && selectedBom && selectedVersion && <div className="modal-backdrop" role="presentation">
        <div className="modal-card bom-modal-card" role="dialog" aria-modal="true" aria-labelledby="bom-item-title">
          <div className="modal-header"><div><p className="eyebrow">BOM v{selectedVersion.version_number}</p><h2 id="bom-item-title">{selectedItemId ? 'Edit material line' : 'Add material line'}</h2></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close item form">×</button></div>
          <form className="master-data-form" onSubmit={handleItemSubmit}>
            <div className="form-grid">
              <label className="form-field"><span>Material *</span><select name="material_id" onChange={handleFormChange(setItemForm)} required value={itemForm.material_id}><option value="">Select material</option>{catalog.materials.map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select></label>
              <label className="form-field"><span>Unit *</span><select name="unit_id" onChange={handleFormChange(setItemForm)} required value={itemForm.unit_id}><option value="">Select unit</option>{catalog.units.map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select></label>
              <label className="form-field"><span>Quantity *</span><input min="0.0001" name="quantity" onChange={handleFormChange(setItemForm)} required step="any" type="number" value={itemForm.quantity} /></label>
              <label className="form-field"><span>Wastage %</span><input max="100" min="0" name="wastage_percentage" onChange={handleFormChange(setItemForm)} step="any" type="number" value={itemForm.wastage_percentage} /></label>
              <label className="form-field"><span>Line number</span><input min="1" name="line_number" onChange={handleFormChange(setItemForm)} type="number" value={itemForm.line_number} /></label>
              <label className="form-field full-width"><span>Notes</span><textarea name="notes" onChange={handleFormChange(setItemForm)} rows="3" value={itemForm.notes} /></label>
            </div>
            <div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Saving…' : selectedItemId ? 'Save item' : 'Add item'}</button></div>
          </form>
        </div>
      </div>}

      {modal === 'details' && selectedBom && <div className="modal-backdrop" role="presentation">
        <div className="modal-card bom-details-modal" role="dialog" aria-modal="true" aria-labelledby="bom-details-title">
          <div className="modal-header"><div><p className="eyebrow">Engineering definition</p><h2 id="bom-details-title">{selectedBom.name}</h2><p className="modal-subtitle">{selectedBom.code} · {selectedBom.product?.name || 'Product'}</p></div><button className="icon-button" onClick={closeModal} type="button" aria-label="Close BOM details">×</button></div>
          <div className="bom-details-body">
            <div className="bom-summary-row"><div><span className="detail-label">Product</span><strong>{selectedBom.product?.name || '—'}</strong></div><div><span className="detail-label">Status</span><span className={statusClass(selectedBom.status)}>{selectedBom.status}</span></div><div><span className="detail-label">Versions</span><strong>{versions.length}</strong></div><div className="detail-actions"><button className="secondary-button" onClick={() => openHeaderEdit(selectedBom)} type="button">Edit BOM</button><button className="text-button danger-text" onClick={removeBom} type="button">Delete</button></div></div>
            <div className="bom-section-heading"><div><p className="eyebrow">Revision history</p><h3>Versions</h3></div><button className="secondary-button" onClick={() => openVersionForm()} type="button">New version</button></div>
            <div className="version-list">
              {versions.map((version) => <button className={`version-card${selectedVersion?.id === version.id ? ' selected' : ''}`} key={version.id} onClick={() => { setSelectedVersionId(version.id); setCalculation(null) }} type="button"><span>v{version.version_number}</span><strong className={statusClass(version.status)}>{version.status}</strong><small>Effective {version.effective_from || '—'} · {version.items_count ?? version.items?.length ?? 0} lines</small></button>)}
            </div>
            {selectedVersion && <>
              <div className="bom-section-heading"><div><p className="eyebrow">Selected revision · v{selectedVersion.version_number}</p><h3>Material lines</h3></div><div className="detail-actions">{selectedVersion.status !== 'active' && <button className="secondary-button" onClick={() => openItemForm()} type="button">Add material</button>}{selectedVersion.status !== 'active' ? <button className="primary-button" disabled={busy || !(selectedVersion.items?.length)} onClick={activateVersion} type="button">Activate version</button> : <button className="secondary-button" disabled={busy} onClick={deactivateVersion} type="button">Deactivate version</button>}</div></div>
              <div className="table-wrap"><table className="master-data-table bom-items-table"><thead><tr><th>Line</th><th>Material</th><th>Quantity</th><th>Unit</th><th>Wastage</th><th>Requirement</th><th><span className="sr-only">Actions</span></th></tr></thead><tbody>{(selectedVersion.items || []).map((item) => <tr key={item.id}><td>{item.line_number}</td><td>{item.material?.name || '—'}</td><td>{formatNumber(item.quantity)}</td><td>{item.unit?.symbol || item.unit?.code || '—'}</td><td>{formatNumber(item.wastage_percentage)}%</td><td>{calculation?.lines?.find((line) => line.item_id === item.id) ? formatNumber(calculation.lines.find((line) => line.item_id === item.id).required_quantity) : '—'}</td><td className="table-actions">{selectedVersion.status !== 'active' && <><button className="text-button" onClick={() => openItemForm(item)} type="button">Edit</button><button className="text-button danger-text" onClick={() => removeItem(item)} type="button">Remove</button></>}</td></tr>)}</tbody></table></div>
              <div className="calculation-panel"><div><p className="eyebrow">Requirement preview</p><h3>Calculate material need</h3><p>Preview quantity including each line’s wastage factor before downstream planning.</p></div><form className="calculation-form" onSubmit={calculate}><label className="form-field"><span>Order quantity</span><input min="0.0001" name="order_quantity" onChange={handleFormChange(setCalculationForm)} required step="any" type="number" value={calculationForm.order_quantity} /></label><button className="primary-button" disabled={busy || !(selectedVersion.items?.length)} type="submit">{busy ? 'Calculating…' : 'Calculate'}</button></form></div>
              {calculation && <div className="calculation-result"><div><span className="detail-label">Order quantity</span><strong>{formatNumber(calculation.order_quantity)}</strong></div><div><span className="detail-label">Lines</span><strong>{calculation.total_lines}</strong></div><div className="calculation-lines">{calculation.lines.map((line) => <div key={line.item_id}><span>{line.material.name}</span><strong>{formatNumber(line.required_quantity)} {line.unit.symbol || line.unit.code}</strong></div>)}</div></div>}
            </>}
            {selectedBom.status !== 'active' ? <button className="primary-button bom-wide-action" disabled={busy || !selectedVersion || !(selectedVersion.items?.length)} onClick={activateBom} type="button">Activate BOM with v{selectedVersion?.version_number || '—'}</button> : <button className="secondary-button bom-wide-action" disabled={busy} onClick={deactivateBom} type="button">Deactivate BOM</button>}
          </div>
          <div className="modal-actions bom-modal-footer"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div>
        </div>
      </div>}

      <button className="back-link" onClick={() => navigate('/')} type="button">← Back to workspace</button>
    </div>
  )
}

export default BOMPage
