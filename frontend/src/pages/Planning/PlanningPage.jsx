import { useCallback, useEffect, useMemo, useState } from 'react'
import planningService from '../../services/planningService'
import masterDataService from '../../services/masterDataService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }

function today() {
  return new Date().toISOString().slice(0, 10)
}

function monthStart() {
  const date = new Date()
  date.setDate(1)
  return date.toISOString().slice(0, 10)
}

function monthEnd(start = monthStart()) {
  const date = new Date(`${start}T00:00:00`)
  date.setMonth(date.getMonth() + 1, 0)
  return date.toISOString().slice(0, 10)
}

function periodEnd(periodType, start) {
  if (!start) return ''
  const date = new Date(`${start}T00:00:00`)
  if (periodType === 'weekly') {
    date.setDate(date.getDate() + 6)
  } else if (periodType === 'quarterly') {
    date.setMonth(date.getMonth() + 3, 0)
  } else {
    date.setMonth(date.getMonth() + 1, 0)
  }
  return date.toISOString().slice(0, 10)
}

function periodDefaults() {
  const start = monthStart()
  return { period_type: 'monthly', period_start: start, period_end: monthEnd(start) }
}

function emptyForecastForm() {
  return {
    product_id: '',
    product_variant_id: '',
    ...periodDefaults(),
    method: 'historical_average',
    forecast_quantity: '',
    forecast_date: today(),
    confidence_score: '',
    accuracy_score: '',
    lookback_periods: '3',
    notes: '',
  }
}

function emptySupplyForm() {
  return { product_id: '', product_variant_id: '', ...periodDefaults(), available_quantity: '', notes: '' }
}

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

function productLabel(product) {
  return product ? `${product.code} · ${product.name}` : '—'
}

function variantLabel(variant) {
  return variant ? `${variant.code} · ${variant.name}` : 'All variants'
}

function PlanningPage() {
  const [activeTab, setActiveTab] = useState('forecasts')
  const [forecastPage, setForecastPage] = useState(emptyPage)
  const [supplyPage, setSupplyPage] = useState(emptyPage)
  const [mrpPage, setMrpPage] = useState(emptyPage)
  const [supplyOptions, setSupplyOptions] = useState([])
  const [catalog, setCatalog] = useState({ products: [], variants: [], materials: [], units: [] })
  const [forecastQuery, setForecastQuery] = useState({ search: '', status: '', period_type: '', product_id: '', page: 1, per_page: 10, sort: 'period_start', direction: 'desc' })
  const [supplyQuery, setSupplyQuery] = useState({ search: '', status: '', period_type: '', product_id: '', page: 1, per_page: 10, sort: 'period_start', direction: 'desc' })
  const [mrpQuery, setMrpQuery] = useState({ search: '', status: '', planning_date_from: '', planning_date_to: '', page: 1, per_page: 10, sort: 'planning_date', direction: 'desc' })
  const [forecastForm, setForecastForm] = useState(emptyForecastForm)
  const [supplyForm, setSupplyForm] = useState(emptySupplyForm)
  const [mrpForm, setMrpForm] = useState({ planning_date: today(), notes: '' })
  const [forecastPreview, setForecastPreview] = useState(null)
  const [supplyPreview, setSupplyPreview] = useState(null)
  const [mrpPreview, setMrpPreview] = useState(null)
  const [selectedForecast, setSelectedForecast] = useState(null)
  const [selectedSupply, setSelectedSupply] = useState(null)
  const [selectedMrp, setSelectedMrp] = useState(null)
  const [selectedSupplyIds, setSelectedSupplyIds] = useState([])
  const [modal, setModal] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const variantsForProduct = useMemo(() => {
    if (!forecastForm.product_id && !supplyForm.product_id) return catalog.variants
    const productId = activeTab === 'forecasts' ? forecastForm.product_id : supplyForm.product_id
    return catalog.variants.filter((variant) => String(variant.product_id) === String(productId))
  }, [activeTab, catalog.variants, forecastForm.product_id, supplyForm.product_id])

  const loadForecasts = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setForecastPage(await planningService.listForecasts(forecastQuery))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [forecastQuery])

  const loadSupplyPlans = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setSupplyPage(await planningService.listSupplyPlans(supplyQuery))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [supplyQuery])

  const loadMaterialRequirements = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setMrpPage(await planningService.listMaterialRequirements(mrpQuery))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [mrpQuery])

  const loadSupplyOptions = useCallback(async () => {
    try {
      const response = await planningService.listSupplyPlans({ per_page: 100, sort: 'period_start', direction: 'asc' })
      setSupplyOptions(response.data || [])
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }, [])

  useEffect(() => {
    let active = true
    Promise.all([
      masterDataService.options('products'),
      masterDataService.options('product-variants'),
      masterDataService.options('materials'),
      masterDataService.options('units'),
    ]).then(([products, variants, materials, units]) => {
      if (active) setCatalog({ products, variants, materials, units })
    }).catch((requestError) => {
      if (active) setError(errorMessage(requestError))
    })
    return () => { active = false }
  }, [])

  useEffect(() => {
    let active = true
    Promise.resolve().then(() => {
      if (!active) return
      if (activeTab === 'forecasts') loadForecasts()
      if (activeTab === 'supply') loadSupplyPlans()
      if (activeTab === 'materials') {
        loadMaterialRequirements()
        loadSupplyOptions()
      }
    })
    return () => { active = false }
  }, [activeTab, loadForecasts, loadMaterialRequirements, loadSupplyOptions, loadSupplyPlans])

  const updateQuery = (setter, changes) => setter((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  const clearFeedback = () => { setError(''); setNotice('') }

  const closeModal = () => {
    setModal(null)
    setSelectedForecast(null)
    setSelectedSupply(null)
    setSelectedMrp(null)
    setForecastPreview(null)
    setSupplyPreview(null)
    setMrpPreview(null)
  }

  const changePeriod = (setter) => (event) => {
    const { name, value } = event.target
    setter((current) => ({ ...current, [name]: value, ...(name === 'period_type' || name === 'period_start' ? { period_end: periodEnd(name === 'period_type' ? value : current.period_type, name === 'period_start' ? value : current.period_start) } : {}) }))
  }

  const changeForm = (setter) => (event) => {
    const { name, value } = event.target
    setter((current) => ({ ...current, [name]: value, ...(name === 'product_id' ? { product_variant_id: '' } : {}) }))
  }

  const forecastPayload = () => ({
    ...forecastForm,
    product_id: Number(forecastForm.product_id),
    product_variant_id: forecastForm.product_variant_id ? Number(forecastForm.product_variant_id) : null,
    forecast_quantity: forecastForm.forecast_quantity === '' ? null : Number(forecastForm.forecast_quantity),
    forecast_date: forecastForm.forecast_date || null,
    confidence_score: forecastForm.confidence_score === '' ? null : Number(forecastForm.confidence_score),
    accuracy_score: forecastForm.accuracy_score === '' ? null : Number(forecastForm.accuracy_score),
    lookback_periods: Number(forecastForm.lookback_periods || 3),
    notes: forecastForm.notes || null,
  })

  const supplyPayload = () => ({
    ...supplyForm,
    product_id: Number(supplyForm.product_id),
    product_variant_id: supplyForm.product_variant_id ? Number(supplyForm.product_variant_id) : null,
    available_quantity: supplyForm.available_quantity === '' ? null : Number(supplyForm.available_quantity),
    notes: supplyForm.notes || null,
  })

  const handleForecastPreview = async (event) => {
    event.preventDefault()
    setBusy(true); clearFeedback()
    try {
      setForecastPreview(await planningService.previewForecast(forecastPayload()))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally { setBusy(false) }
  }

  const handleForecastSubmit = async (event) => {
    event.preventDefault()
    setBusy(true); clearFeedback()
    try {
      const forecast = await planningService.createForecast(forecastPayload())
      setSelectedForecast(forecast)
      setNotice('Demand forecast created as a draft.')
      closeModal()
      await loadForecasts()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally { setBusy(false) }
  }

  const openForecastDetails = async (forecast) => {
    setBusy(true); clearFeedback()
    try {
      setSelectedForecast(await planningService.getForecast(forecast.id))
      setModal('forecast-details')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const activateForecast = async (forecast) => {
    setBusy(true); clearFeedback()
    try {
      setSelectedForecast(await planningService.activateForecast(forecast.id))
      setNotice('Demand forecast activated for supply planning.')
      await loadForecasts()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const handleSupplyPreview = async (event) => {
    event.preventDefault()
    setBusy(true); clearFeedback()
    try { setSupplyPreview(await planningService.previewSupplyPlan(supplyPayload())) }
    catch (requestError) { setError(errorMessage(requestError)) }
    finally { setBusy(false) }
  }

  const handleSupplyGenerate = async (event) => {
    event.preventDefault()
    setBusy(true); clearFeedback()
    try {
      await planningService.generateSupplyPlans(supplyPayload())
      setNotice('Supply plan generated from confirmed demand and active forecasts.')
      setSupplyPreview(null)
      await Promise.all([loadSupplyPlans(), loadSupplyOptions()])
    } catch (requestError) { setError(errorMessage(requestError)) }
    finally { setBusy(false) }
  }

  const openSupplyDetails = async (plan) => {
    setBusy(true); clearFeedback()
    try {
      setSelectedSupply(await planningService.getSupplyPlan(plan.id))
      setModal('supply-details')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const handleSupplyRecalculate = async () => {
    if (!selectedSupply) return
    const available = window.prompt('Optional available product quantity. Leave blank to keep availability unknown.', selectedSupply.available_quantity ?? '')
    if (available === null) return
    setBusy(true); clearFeedback()
    try {
      setSelectedSupply(await planningService.recalculateSupplyPlan(selectedSupply.id, { available_quantity: available === '' ? null : Number(available) }))
      setNotice('Supply plan recalculated.')
      await Promise.all([loadSupplyPlans(), loadSupplyOptions()])
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const toggleSupplySelection = (id) => setSelectedSupplyIds((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id])

  const mrpPayload = () => ({ supply_plan_ids: selectedSupplyIds.map(Number), planning_date: mrpForm.planning_date || null, notes: mrpForm.notes || null })

  const handleMrpPreview = async () => {
    if (selectedSupplyIds.length === 0) { setError('Select at least one Supply Plan before previewing material requirements.'); return }
    setBusy(true); clearFeedback()
    try { setMrpPreview(await planningService.previewMaterialRequirements(mrpPayload())) }
    catch (requestError) { setError(errorMessage(requestError)) }
    finally { setBusy(false) }
  }

  const handleMrpGenerate = async () => {
    if (selectedSupplyIds.length === 0) { setError('Select at least one Supply Plan before generating material requirements.'); return }
    setBusy(true); clearFeedback()
    try {
      await planningService.generateMaterialRequirements(mrpPayload())
      setNotice('Material requirements generated from active BOMs and selected Supply Plans.')
      setMrpPreview(null)
      setSelectedSupplyIds([])
      await loadMaterialRequirements()
    } catch (requestError) { setError(errorMessage(requestError)) }
    finally { setBusy(false) }
  }

  const openMrpDetails = async (run) => {
    setBusy(true); clearFeedback()
    try {
      setSelectedMrp(await planningService.getMaterialRequirementRun(run.id))
      setModal('mrp-details')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const renderForecasts = () => (
    <>
      <div className="master-data-toolbar planning-toolbar">
        <label className="search-field">Search<input onChange={(event) => updateQuery(setForecastQuery, { search: event.target.value })} placeholder="Product, variant, method" value={forecastQuery.search} /></label>
        <label className="filter-field">Status<select onChange={(event) => updateQuery(setForecastQuery, { status: event.target.value })} value={forecastQuery.status}><option value="">All statuses</option><option value="draft">Draft</option><option value="active">Active</option></select></label>
        <label className="filter-field">Period<select onChange={(event) => updateQuery(setForecastQuery, { period_type: event.target.value })} value={forecastQuery.period_type}><option value="">All periods</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option></select></label>
        <label className="filter-field">Product<select onChange={(event) => updateQuery(setForecastQuery, { product_id: event.target.value })} value={forecastQuery.product_id}><option value="">All products</option>{catalog.products.map((product) => <option key={product.id} value={product.id}>{productLabel(product)}</option>)}</select></label>
        <button className="primary-button" onClick={() => { clearFeedback(); setForecastForm(emptyForecastForm()); setModal('forecast-form') }} type="button">New forecast</button>
      </div>
      <div className="data-card">
        <div className="data-card-header"><div><p className="eyebrow">Forecast register</p><h2>Demand Forecast</h2></div><span className="data-card-hint">{forecastPage.meta?.total || 0} records</span></div>
        <div className="table-wrap"><table className="master-data-table planning-table"><thead><tr><th>Product</th><th>Variant</th><th>Period</th><th>Forecast quantity</th><th>Method</th><th>Status</th><th /></tr></thead><tbody>
          {forecastPage.data?.map((forecast) => <tr key={forecast.id} onClick={() => openForecastDetails(forecast)}><td><strong>{productLabel(forecast.product)}</strong></td><td>{variantLabel(forecast.product_variant)}</td><td>{forecast.period_start} → {forecast.period_end}</td><td>{formatNumber(forecast.forecast_quantity)}</td><td>{forecast.method.replace('_', ' ')}</td><td><span className={statusClass(forecast.status)}>{forecast.status}</span></td><td><div className="table-actions"><button className="text-button" onClick={(event) => { event.stopPropagation(); openForecastDetails(forecast) }} type="button">Open</button>{forecast.status === 'draft' && <button className="text-button" disabled={busy} onClick={(event) => { event.stopPropagation(); activateForecast(forecast) }} type="button">Activate</button>}</div></td></tr>)}
        </tbody></table></div>
        {!loading && forecastPage.data?.length === 0 && <div className="empty-state planning-empty"><div className="empty-state-line" /><p className="eyebrow">No forecast records</p><h2>Build a transparent demand baseline.</h2><p>Create a manual or historical-average forecast for a defined planning period.</p></div>}
        <Pagination page={forecastPage} onChange={(page) => updateQuery(setForecastQuery, { page })} />
      </div>
    </>
  )

  const renderSupplyPlans = () => (
    <>
      <div className="planning-calculation-card">
        <div><p className="eyebrow">Supply planning input</p><h2>Confirmed demand + active forecast</h2><p>Generate a period plan without creating production records. Availability is optional and remains unknown when no stock data is supplied.</p></div>
        <form className="planning-form" onSubmit={handleSupplyPreview}><div className="form-grid">
          <label className="form-field">Product<select name="product_id" onChange={changeForm(setSupplyForm)} required value={supplyForm.product_id}><option value="">Select product</option>{catalog.products.map((product) => <option key={product.id} value={product.id}>{productLabel(product)}</option>)}</select></label>
          <label className="form-field">Product Variant<select name="product_variant_id" onChange={changeForm(setSupplyForm)} value={supplyForm.product_variant_id}><option value="">All variants</option>{variantsForProduct.map((variant) => <option key={variant.id} value={variant.id}>{variantLabel(variant)}</option>)}</select></label>
          <label className="form-field">Period<select name="period_type" onChange={changePeriod(setSupplyForm)} value={supplyForm.period_type}><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option></select></label>
          <label className="form-field">Period start<input name="period_start" onChange={changePeriod(setSupplyForm)} required type="date" value={supplyForm.period_start} /></label>
          <label className="form-field">Period end<input name="period_end" readOnly type="date" value={supplyForm.period_end} /></label>
          <label className="form-field">Available quantity<input min="0" name="available_quantity" onChange={changeForm(setSupplyForm)} placeholder="Leave blank if unknown" step="0.0001" type="number" value={supplyForm.available_quantity} /></label>
        </div><div className="modal-actions"><button className="secondary-button" disabled={busy} type="submit">Preview plan</button><button className="primary-button" disabled={busy} onClick={handleSupplyGenerate} type="button">Generate plan</button></div></form>
        {supplyPreview && <CalculationPreview title="Supply plan preview" preview={supplyPreview} kind="supply" />}
      </div>
      <div className="master-data-toolbar planning-toolbar"><label className="search-field">Search<input onChange={(event) => updateQuery(setSupplyQuery, { search: event.target.value })} placeholder="Product, variant, status" value={supplyQuery.search} /></label><label className="filter-field">Status<select onChange={(event) => updateQuery(setSupplyQuery, { status: event.target.value })} value={supplyQuery.status}><option value="">All statuses</option><option value="pending_inventory">Pending inventory</option><option value="calculated">Calculated</option></select></label><label className="filter-field">Product<select onChange={(event) => updateQuery(setSupplyQuery, { product_id: event.target.value })} value={supplyQuery.product_id}><option value="">All products</option>{catalog.products.map((product) => <option key={product.id} value={product.id}>{productLabel(product)}</option>)}</select></label></div>
      <div className="data-card"><div className="data-card-header"><div><p className="eyebrow">Supply register</p><h2>Supply Planning</h2></div><span className="data-card-hint">{supplyPage.meta?.total || 0} records</span></div><div className="table-wrap"><table className="master-data-table planning-table"><thead><tr><th>Product</th><th>Period</th><th>Firm order</th><th>Forecast</th><th>Required</th><th>Planned</th><th>Status</th><th /></tr></thead><tbody>{supplyPage.data?.map((plan) => <tr key={plan.id} onClick={() => openSupplyDetails(plan)}><td><strong>{productLabel(plan.product)}</strong><small>{variantLabel(plan.product_variant)}</small></td><td>{plan.period_start} → {plan.period_end}</td><td>{formatNumber(plan.confirmed_order_quantity)}</td><td>{formatNumber(plan.forecast_quantity)}</td><td>{formatNumber(plan.required_quantity)}</td><td>{formatNumber(plan.planned_production_quantity)}</td><td><span className={statusClass(plan.status)}>{plan.status.replace('_', ' ')}</span></td><td><button className="text-button" onClick={(event) => { event.stopPropagation(); openSupplyDetails(plan) }} type="button">Open</button></td></tr>)}</tbody></table></div>{!loading && supplyPage.data?.length === 0 && <div className="empty-state planning-empty"><div className="empty-state-line" /><p className="eyebrow">No supply plans</p><h2>Generate the first period plan.</h2><p>Select a Product and period above to combine firm Buyer Order demand with active forecast demand.</p></div>}<Pagination page={supplyPage} onChange={(page) => updateQuery(setSupplyQuery, { page })} /></div>
    </>
  )

  const renderMaterials = () => (
    <>
      <div className="planning-calculation-card">        <div><p className="eyebrow">Material requirement input</p><h2>BOM explosion and aggregation</h2><p>Select Supply Plans to calculate gross material requirements. Inventory values are optional integration inputs; no stock workflow is created here.</p></div><label className="form-field">Planning date<input name="planning_date" onChange={(event) => setMrpForm((current) => ({ ...current, planning_date: event.target.value }))} type="date" value={mrpForm.planning_date} /></label><div className="planning-selection-list">
{supplyOptions.length === 0 ? <p className="data-card-hint">No Supply Plans are available yet.</p> : supplyOptions.map((plan) => <label className="planning-selection" key={plan.id}><input checked={selectedSupplyIds.includes(plan.id)} onChange={() => toggleSupplySelection(plan.id)} type="checkbox" /><span><strong>{productLabel(plan.product)}</strong><small>{plan.period_start} · planned {formatNumber(plan.planned_production_quantity)}</small></span></label>)}</div><div className="modal-actions"><button className="secondary-button" disabled={busy || selectedSupplyIds.length === 0} onClick={handleMrpPreview} type="button">Preview requirements</button><button className="primary-button" disabled={busy || selectedSupplyIds.length === 0} onClick={handleMrpGenerate} type="button">Generate MRP run</button></div>{mrpPreview && <CalculationPreview title="Material requirement preview" preview={mrpPreview} kind="mrp" />}</div>
      <div className="master-data-toolbar planning-toolbar"><label className="search-field">Search<input onChange={(event) => updateQuery(setMrpQuery, { search: event.target.value })} placeholder="Run number or status" value={mrpQuery.search} /></label><label className="filter-field">From<input onChange={(event) => updateQuery(setMrpQuery, { planning_date_from: event.target.value })} type="date" value={mrpQuery.planning_date_from} /></label><label className="filter-field">To<input onChange={(event) => updateQuery(setMrpQuery, { planning_date_to: event.target.value })} type="date" value={mrpQuery.planning_date_to} /></label></div>
      <div className="data-card"><div className="data-card-header"><div><p className="eyebrow">MRP run register</p><h2>Material Requirements</h2></div><span className="data-card-hint">{mrpPage.meta?.total || 0} runs</span></div><div className="table-wrap"><table className="master-data-table planning-table"><thead><tr><th>Run</th><th>Planning date</th><th>Gross quantity</th><th>Net quantity</th><th>Inventory data</th><th>Status</th><th /></tr></thead><tbody>{mrpPage.data?.map((run) => <tr key={run.id} onClick={() => openMrpDetails(run)}><td><strong>{run.run_number}</strong><small>{run.material_requirement_count || 0} material lines</small></td><td>{run.planning_date}</td><td>{formatNumber(run.total_gross_quantity)}</td><td>{run.total_net_quantity === null ? 'Unknown' : formatNumber(run.total_net_quantity)}</td><td>{run.inventory_data_available ? 'Supplied' : 'Not supplied'}</td><td><span className={statusClass(run.status)}>{run.status}</span></td><td><button className="text-button" onClick={(event) => { event.stopPropagation(); openMrpDetails(run) }} type="button">Open</button></td></tr>)}</tbody></table></div>{!loading && mrpPage.data?.length === 0 && <div className="empty-state planning-empty"><div className="empty-state-line" /><p className="eyebrow">No MRP runs</p><h2>Material requirements stay reproducible.</h2><p>Generate an MRP run after creating Supply Plans for a period with an active BOM.</p></div>}<Pagination page={mrpPage} onChange={(page) => updateQuery(setMrpQuery, { page })} /></div>
    </>
  )

  return <section className="planning-page"><div className="page-intro planning-intro"><div><p className="eyebrow">Phase 5 · Planning & demand forecasting</p><h1>Plan with evidence, not guesswork.</h1><p className="lede">Translate confirmed Buyer Order demand into transparent forecasts, supply plans, and BOM-driven material requirements.</p></div><div className="intro-mark"><span>05 / 10</span><strong>Planning<br />control</strong></div></div>{error && <div className="feedback-message error-message">{error}</div>}{notice && <div className="feedback-message success-message">{notice}</div>}<div className="planning-tabs" role="tablist"><button className={activeTab === 'forecasts' ? 'active' : ''} onClick={() => { clearFeedback(); setActiveTab('forecasts') }} role="tab" type="button">Demand Forecast</button><button className={activeTab === 'supply' ? 'active' : ''} onClick={() => { clearFeedback(); setActiveTab('supply') }} role="tab" type="button">Supply Planning</button><button className={activeTab === 'materials' ? 'active' : ''} onClick={() => { clearFeedback(); setActiveTab('materials') }} role="tab" type="button">Material Requirements</button></div>{loading && <div className="route-loading planning-loading">Loading planning data…</div>}{!loading && activeTab === 'forecasts' && renderForecasts()}{!loading && activeTab === 'supply' && renderSupplyPlans()}{!loading && activeTab === 'materials' && renderMaterials()}
    {modal === 'forecast-form' && <div className="modal-backdrop"><div className="modal-card planning-modal-card"><div className="modal-header"><div><p className="eyebrow">Demand baseline</p><h2>New demand forecast</h2></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={handleForecastSubmit}><div className="form-grid"><label className="form-field">Product<select name="product_id" onChange={changeForm(setForecastForm)} required value={forecastForm.product_id}><option value="">Select product</option>{catalog.products.map((product) => <option key={product.id} value={product.id}>{productLabel(product)}</option>)}</select></label><label className="form-field">Product Variant<select name="product_variant_id" onChange={changeForm(setForecastForm)} value={forecastForm.product_variant_id}><option value="">All variants</option>{variantsForProduct.map((variant) => <option key={variant.id} value={variant.id}>{variantLabel(variant)}</option>)}</select></label><label className="form-field">Period<select name="period_type" onChange={changePeriod(setForecastForm)} value={forecastForm.period_type}><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option></select></label><label className="form-field">Period start<input name="period_start" onChange={changePeriod(setForecastForm)} required type="date" value={forecastForm.period_start} /></label><label className="form-field">Period end<input name="period_end" readOnly type="date" value={forecastForm.period_end} /></label><label className="form-field">Method<select name="method" onChange={changeForm(setForecastForm)} value={forecastForm.method}><option value="historical_average">Historical average</option><option value="manual">Manual</option></select></label><label className="form-field">Forecast quantity<input disabled={forecastForm.method !== 'manual'} min="0" name="forecast_quantity" onChange={changeForm(setForecastForm)} placeholder={forecastForm.method === 'manual' ? 'Required for manual method' : 'Calculated from history'} step="0.0001" type="number" value={forecastForm.forecast_quantity} /></label><label className="form-field">Lookback periods<input min="1" max="24" name="lookback_periods" onChange={changeForm(setForecastForm)} type="number" value={forecastForm.lookback_periods} /></label><label className="form-field">Confidence score<input max="100" min="0" name="confidence_score" onChange={changeForm(setForecastForm)} placeholder="Optional 0–100" step="0.0001" type="number" value={forecastForm.confidence_score} /></label><label className="form-field">Forecast date<input name="forecast_date" onChange={changeForm(setForecastForm)} type="date" value={forecastForm.forecast_date} /></label><label className="form-field full-width">Notes<textarea name="notes" onChange={changeForm(setForecastForm)} placeholder="Method notes or assumptions" rows="3" value={forecastForm.notes} /></label></div><div className="modal-actions"><button className="secondary-button" disabled={busy} onClick={handleForecastPreview} type="button">Preview calculation</button><button className="primary-button" disabled={busy} type="submit">Save draft</button></div>{forecastPreview && <CalculationPreview title="Forecast preview" preview={forecastPreview} kind="forecast" />}</form></div></div>}
    {modal === 'forecast-details' && selectedForecast && <div className="modal-backdrop"><div className="modal-card planning-detail-modal"><div className="modal-header"><div><p className="eyebrow">Forecast detail</p><h2>{productLabel(selectedForecast.product)}</h2><p className="modal-subtitle">{selectedForecast.period_start} → {selectedForecast.period_end}</p></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><dl className="details-list"><Detail label="Product Variant" value={variantLabel(selectedForecast.product_variant)} /><Detail label="Method" value={selectedForecast.method.replace('_', ' ')} /><Detail label="Forecast quantity" value={formatNumber(selectedForecast.forecast_quantity)} /><Detail label="Status" value={<span className={statusClass(selectedForecast.status)}>{selectedForecast.status}</span>} /><Detail label="Lookback periods" value={selectedForecast.lookback_periods} /><Detail label="Forecast date" value={selectedForecast.forecast_date} /></dl><div className="planning-detail-body">{selectedForecast.calculation_snapshot?.historical_periods?.length > 0 && <><div className="section-heading"><div><p className="eyebrow">Evidence</p><h2>Comparable historical periods</h2></div></div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Period</th><th>Firm order quantity</th></tr></thead><tbody>{selectedForecast.calculation_snapshot.historical_periods.map((period) => <tr key={period.period_start}><td>{period.period_start} → {period.period_end}</td><td>{formatNumber(period.demand_quantity)}</td></tr>)}</tbody></table></div></>}{selectedForecast.status === 'draft' && <div className="detail-actions planning-detail-actions"><button className="primary-button" disabled={busy} onClick={() => activateForecast(selectedForecast)} type="button">Activate forecast</button></div>}</div></div></div>}
    {modal === 'supply-details' && selectedSupply && <div className="modal-backdrop"><div className="modal-card planning-detail-modal"><div className="modal-header"><div><p className="eyebrow">Supply plan detail</p><h2>{productLabel(selectedSupply.product)}</h2><p className="modal-subtitle">{selectedSupply.period_start} → {selectedSupply.period_end}</p></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><dl className="details-list"><Detail label="Product Variant" value={variantLabel(selectedSupply.product_variant)} /><Detail label="Confirmed order" value={formatNumber(selectedSupply.confirmed_order_quantity)} /><Detail label="Forecast" value={formatNumber(selectedSupply.forecast_quantity)} /><Detail label="Required" value={formatNumber(selectedSupply.required_quantity)} /><Detail label="Available" value={selectedSupply.available_quantity === null ? 'Unknown' : formatNumber(selectedSupply.available_quantity)} /><Detail label="Planned production" value={formatNumber(selectedSupply.planned_production_quantity)} /><Detail label="Status" value={<span className={statusClass(selectedSupply.status)}>{selectedSupply.status.replace('_', ' ')}</span>} /></dl><div className="planning-detail-body"><div className="detail-actions planning-detail-actions"><button className="secondary-button" disabled={busy} onClick={handleSupplyRecalculate} type="button">Recalculate availability</button></div></div></div></div>}
    {modal === 'mrp-details' && selectedMrp && <div className="modal-backdrop"><div className="modal-card planning-detail-modal"><div className="modal-header"><div><p className="eyebrow">Material requirement detail</p><h2>{selectedMrp.run_number}</h2><p className="modal-subtitle">Planning date {selectedMrp.planning_date}</p></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><dl className="details-list"><Detail label="Gross requirement" value={formatNumber(selectedMrp.total_gross_quantity)} /><Detail label="Net requirement" value={selectedMrp.total_net_quantity === null ? 'Unknown until inventory integration' : formatNumber(selectedMrp.total_net_quantity)} /><Detail label="Inventory data" value={selectedMrp.inventory_data_available ? 'Supplied by caller' : 'Not supplied'} /><Detail label="Status" value={<span className={statusClass(selectedMrp.status)}>{selectedMrp.status}</span>} /></dl><div className="planning-detail-body"><div className="section-heading"><div><p className="eyebrow">Aggregated output</p><h2>Material lines</h2></div></div><div className="table-wrap"><table className="master-data-table planning-table"><thead><tr><th>Material</th><th>Gross</th><th>Available</th><th>Net</th><th>Status</th></tr></thead><tbody>{selectedMrp.material_requirements?.map((line) => <tr key={line.id}><td><strong>{line.material?.code}</strong><small>{line.material?.name} · {line.unit?.symbol || line.unit?.code}</small></td><td>{formatNumber(line.gross_quantity)}</td><td>{line.available_quantity === null ? 'Unknown' : formatNumber(line.available_quantity)}</td><td>{line.net_quantity === null ? 'Unknown' : formatNumber(line.net_quantity)}</td><td><span className={statusClass(line.status)}>{line.status.replace('_', ' ')}</span></td></tr>)}</tbody></table></div></div></div></div>}
  </section>
}

function Pagination({ page, onChange }) {
  const meta = page.meta || {}
  return <div className="pagination-bar"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={!meta.current_page || meta.current_page <= 1} onClick={() => onChange(meta.current_page - 1)} type="button">Previous</button><button className="secondary-button" disabled={!meta.last_page || meta.current_page >= meta.last_page} onClick={() => onChange(meta.current_page + 1)} type="button">Next</button></div></div>
}

function Detail({ label, value }) {
  return <div><dt>{label}</dt><dd>{value}</dd></div>
}

function CalculationPreview({ title, preview, kind }) {
  if (kind === 'forecast') return <div className="calculation-result planning-result"><div><span className="detail-label">Forecast quantity</span><strong>{formatNumber(preview.forecast_quantity)}</strong></div><div><span className="detail-label">Method</span><strong>{preview.method.replace('_', ' ')}</strong></div><div className="calculation-lines">{preview.historical_periods?.map((period) => <div key={period.period_start}><span>{period.period_start} → {period.period_end}</span><strong>{formatNumber(period.demand_quantity)}</strong></div>)}</div></div>
  if (kind === 'supply') return <div className="calculation-result planning-result"><div><span className="detail-label">Required</span><strong>{formatNumber(preview.required_quantity)}</strong></div><div><span className="detail-label">Planned</span><strong>{formatNumber(preview.planned_production_quantity)}</strong></div><div className="calculation-lines"><div><span>Confirmed orders</span><strong>{formatNumber(preview.confirmed_order_quantity)}</strong></div><div><span>Active forecast</span><strong>{formatNumber(preview.forecast_quantity)}</strong></div><div><span>Availability</span><strong>{preview.available_quantity === null ? 'Unknown' : formatNumber(preview.available_quantity)}</strong></div></div></div>
  return <div className="calculation-result planning-result"><div><span className="detail-label">Gross materials</span><strong>{formatNumber(preview.total_gross_quantity)}</strong></div><div><span className="detail-label">Net materials</span><strong>{preview.total_net_quantity === null ? 'Unknown' : formatNumber(preview.total_net_quantity)}</strong></div><div className="calculation-lines">{preview.lines?.map((line) => <div key={`${line.material.id}-${line.unit.id}`}><span>{line.material.code} · {line.unit.symbol || line.unit.code}</span><strong>{formatNumber(line.gross_quantity)}</strong></div>)}</div><p className="data-card-hint">{title} · {preview.inventory_data_available ? 'Availability supplied.' : 'Availability not supplied; net requirements remain unknown.'}</p></div>
}

export default PlanningPage
