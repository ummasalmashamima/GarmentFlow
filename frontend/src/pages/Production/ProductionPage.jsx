import { useCallback, useEffect, useMemo, useState } from 'react'
import masterDataService from '../../services/masterDataService'
import planningService from '../../services/planningService'
import productionService from '../../services/productionService'

const tabs = [
  { key: 'plans', label: 'Production Plans' },
  { key: 'orders', label: 'Production Orders' },
  { key: 'progress', label: 'Progress' },
  { key: 'consumptions', label: 'Material Consumption' },
  { key: 'finishedGoods', label: 'Finished Goods' },
  { key: 'history', label: 'Production History' },
]

const today = new Date().toISOString().slice(0, 10)
const emptyPlan = {
  product_id: '',
  product_variant_id: '',
  supply_plan_id: '',
  buyer_order_id: '',
  planned_quantity: '',
  planned_start_date: today,
  planned_end_date: today,
  priority: 'normal',
  remarks: '',
}
const emptyOrder = {
  production_plan_id: '',
  planned_quantity: '',
  expected_completion_date: today,
  issue_warehouse_id: '',
  issue_warehouse_location_id: '',
  remarks: '',
}
const emptyConsumption = { production_order_item_id: '', quantity: '', consumption_date: today, idempotency_key: '', remarks: '' }
const emptyProgress = { completed_quantity: '', rejected_quantity: '0', production_date: today, remarks: '' }
const emptyCompletion = { finished_quantity: '', completed_quantity: '', rejected_quantity: '0', finished_date: today, remarks: '' }

function errorMessage(error) {
  const errors = error?.response?.data?.errors
  if (errors) return Object.values(errors).flat().join(' ')
  return error?.response?.data?.message || error?.message || 'The production request could not be completed.'
}

function unwrapRows(response) {
  return { rows: response?.data || [], meta: response?.meta || { current_page: 1, last_page: 1, total: 0 } }
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function statusClass(status = '') {
  return `status-pill status-${String(status).replaceAll('_', '-')}`
}

function optionLabel(option) {
  return option?.code ? `${option.code} · ${option.name}` : option?.name || option?.sku || `#${option?.id}`
}

function ProductionPage() {
  const [tab, setTab] = useState('plans')
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [query, setQuery] = useState({ search: '', status: '', product_id: '', date_from: '', date_to: '', page: 1, per_page: 15, sort: 'id', direction: 'desc' })
  const [options, setOptions] = useState({ products: [], variants: [], warehouses: [], locations: [], supplyPlans: [] })
  const [productionPlans, setProductionPlans] = useState([])
  const [summary, setSummary] = useState({ plans: 0, orders: 0, activeOrders: 0, outputs: 0 })
  const [loading, setLoading] = useState(true)
  const [optionsLoading, setOptionsLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [modal, setModal] = useState(null)
  const [selectedPlan, setSelectedPlan] = useState(null)
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [availability, setAvailability] = useState(null)
  const [form, setForm] = useState(emptyPlan)
  const [orderForm, setOrderForm] = useState(emptyOrder)
  const [consumptionForm, setConsumptionForm] = useState(emptyConsumption)
  const [progressForm, setProgressForm] = useState(emptyProgress)
  const [completionForm, setCompletionForm] = useState(emptyCompletion)
  const [submitting, setSubmitting] = useState(false)

  const productVariants = useMemo(
    () => options.variants.filter((variant) => !form.product_id || String(variant.product_id) === String(form.product_id)),
    [form.product_id, options.variants],
  )
  const orderPlans = useMemo(
    () => productionPlans.filter((plan) => ['approved', 'scheduled'].includes(plan.status)),
    [productionPlans],
  )
  const orderLocations = useMemo(
    () => options.locations.filter((location) => !orderForm.issue_warehouse_id || String(location.warehouse_id) === String(orderForm.issue_warehouse_id)),
    [orderForm.issue_warehouse_id, options.locations],
  )

  const loadPlanCatalog = useCallback(async () => {
    try {
      const response = await productionService.plans.list({ per_page: 100, sort: 'id', direction: 'desc' })
      setProductionPlans(response?.data || [])
    } catch (loadError) {
      setError(errorMessage(loadError))
    }
  }, [])

  const loadOptions = useCallback(async () => {
    setOptionsLoading(true)
    try {
      const [products, variants, warehouses, locations, supplyPlans] = await Promise.all([
        masterDataService.options('products'),
        masterDataService.options('product-variants'),
        masterDataService.options('warehouses'),
        masterDataService.options('warehouse-locations'),
        planningService.listSupplyPlans({ per_page: 100 }),
      ])
      setOptions({
        products: products || [],
        variants: variants || [],
        warehouses: warehouses || [],
        locations: locations || [],
        supplyPlans: supplyPlans?.data || [],
      })
    } catch (loadError) {
      setError(errorMessage(loadError))
    } finally {
      setOptionsLoading(false)
    }
  }, [])

  const loadSummary = useCallback(async () => {
    try {
      const [plans, orders, activeOrders, outputs] = await Promise.all([
        productionService.plans.list({ per_page: 1 }),
        productionService.orders.list({ per_page: 1 }),
        productionService.orders.list({ per_page: 1, status: 'in_progress' }),
        productionService.finishedGoods.list({ per_page: 1 }),
      ])
      setSummary({
        plans: plans?.meta?.total ?? 0,
        orders: orders?.meta?.total ?? 0,
        activeOrders: activeOrders?.meta?.total ?? 0,
        outputs: outputs?.meta?.total ?? 0,
      })
    } catch (loadError) {
      setError(errorMessage(loadError))
    }
  }, [])

  const loadRows = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params = { ...query, page: query.page, status: query.status || undefined, product_id: query.product_id || undefined, date_from: query.date_from || undefined, date_to: query.date_to || undefined }
      let response
      if (tab === 'plans') response = await productionService.plans.list({ ...params, planned_start_from: query.date_from || undefined, planned_end_to: query.date_to || undefined })
      if (tab === 'orders') response = await productionService.orders.list({ ...params, expected_completion_from: query.date_from || undefined, expected_completion_to: query.date_to || undefined })
      if (tab === 'progress') response = await productionService.progress.list(params)
      if (tab === 'consumptions') response = await productionService.consumptions.list(params)
      if (tab === 'finishedGoods') response = await productionService.finishedGoods.list(params)
      if (tab === 'history') response = await productionService.history.list(params)
      const normalized = unwrapRows(response)
      setRows(normalized.rows)
      setMeta(normalized.meta)
    } catch (loadError) {
      setRows([])
      setError(errorMessage(loadError))
    } finally {
      setLoading(false)
    }
  }, [query, tab])

  useEffect(() => {
    const task = Promise.resolve().then(() => Promise.all([loadOptions(), loadPlanCatalog(), loadSummary()]))
    return () => { void task }
  }, [loadOptions, loadPlanCatalog, loadSummary])

  useEffect(() => {
    const task = Promise.resolve().then(() => loadRows())
    return () => { void task }
  }, [loadRows])

  const refresh = async (message = '') => {
    if (message) setSuccess(message)
    await Promise.all([loadRows(), loadSummary(), loadPlanCatalog()])
  }

  const updateQuery = (field, value) => setQuery((current) => ({ ...current, [field]: value, page: 1 }))
  const updateForm = (setter, field, value) => setter((current) => ({ ...current, [field]: value }))

  const openPlan = async (plan) => {
    setSubmitting(true)
    try {
      setSelectedPlan(await productionService.plans.get(plan.id))
      setModal('planDetail')
    } catch (loadError) {
      setError(errorMessage(loadError))
    } finally {
      setSubmitting(false)
    }
  }

  const openOrder = async (order) => {
    setSubmitting(true)
    try {
      const detail = await productionService.orders.get(order.id)
      setSelectedOrder(detail)
      setAvailability(await productionService.orders.availability(order.id))
      setModal('orderDetail')
    } catch (loadError) {
      setError(errorMessage(loadError))
    } finally {
      setSubmitting(false)
    }
  }

  const closeModal = () => {
    setModal(null)
    setSelectedPlan(null)
    setSelectedOrder(null)
    setAvailability(null)
  }

  const submitPlan = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      await productionService.plans.create({ ...form, product_variant_id: form.product_variant_id || null, supply_plan_id: form.supply_plan_id || null, buyer_order_id: form.buyer_order_id || null })
      setForm(emptyPlan)
      setModal(null)
      await refresh('Production Plan created successfully.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitOrder = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      await productionService.orders.create({ ...orderForm, planned_quantity: orderForm.planned_quantity || null, issue_warehouse_location_id: orderForm.issue_warehouse_location_id || null })
      setOrderForm(emptyOrder)
      setModal(null)
      await refresh('Production Order created successfully.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const approvePlan = async () => {
    if (!selectedPlan) return
    setSubmitting(true)
    try {
      const plan = await productionService.plans.approve(selectedPlan.id)
      setSelectedPlan(plan)
      await refresh('Production Plan approved successfully.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const startOrder = async () => {
    if (!selectedOrder) return
    setSubmitting(true)
    try {
      const order = await productionService.orders.start(selectedOrder.id)
      setSelectedOrder(order)
      setAvailability(await productionService.orders.availability(order.id))
      await refresh('Production Order started successfully.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitConsumption = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    try {
      await productionService.orders.consume(selectedOrder.id, { ...consumptionForm, production_order_item_id: Number(consumptionForm.production_order_item_id), idempotency_key: consumptionForm.idempotency_key || undefined })
      setConsumptionForm(emptyConsumption)
      const detail = await productionService.orders.get(selectedOrder.id)
      setSelectedOrder(detail)
      setAvailability(await productionService.orders.availability(selectedOrder.id))
      await refresh('Material consumption posted and inventory decreased.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitProgress = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    try {
      await productionService.orders.progress(selectedOrder.id, progressForm)
      setProgressForm(emptyProgress)
      const detail = await productionService.orders.get(selectedOrder.id)
      setSelectedOrder(detail)
      await refresh('Production progress recorded successfully.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitCompletion = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    try {
      const completed = await productionService.orders.complete(selectedOrder.id, completionForm)
      setSelectedOrder(completed)
      setCompletionForm(emptyCompletion)
      await refresh('Production completed and finished goods posted to inventory.')
    } catch (submitError) {
      setError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="production-page">
      <div className="page-intro production-intro">
        <div>
          <p className="eyebrow">Phase 8 · Production management</p>
          <h1>Production Control Center</h1>
          <p>Translate supply plans into traceable production orders, material issues, progress, and finished goods.</p>
        </div>
        <div className="page-actions">
          <button className="button button-primary" onClick={() => { setForm(emptyPlan); setModal('planForm') }} type="button">New production plan</button>
          <button className="button button-secondary" onClick={() => { setOrderForm(emptyOrder); setModal('orderForm') }} type="button">New production order</button>
        </div>
      </div>

      {error && <div className="alert alert-error" role="alert">{error}</div>}
      {success && <div className="alert alert-success" role="status">{success}</div>}

      <div className="summary-grid production-summary-grid">
        <article className="summary-card"><span>Production plans</span><strong>{formatNumber(summary.plans)}</strong><small>Demand translated into execution</small></article>
        <article className="summary-card"><span>Production orders</span><strong>{formatNumber(summary.orders)}</strong><small>Traceable executable orders</small></article>
        <article className="summary-card"><span>In progress</span><strong>{formatNumber(summary.activeOrders)}</strong><small>Orders currently on the floor</small></article>
        <article className="summary-card summary-card-accent"><span>Finished goods records</span><strong>{formatNumber(summary.outputs)}</strong><small>Output ready for inventory traceability</small></article>
      </div>

      <div className="tab-strip production-tabs" role="tablist" aria-label="Production views">
        {tabs.map((item) => <button className={tab === item.key ? 'active' : ''} key={item.key} onClick={() => { setTab(item.key); setQuery((current) => ({ ...current, page: 1 })) }} role="tab" type="button">{item.label}</button>)}
      </div>

      <div className="filter-card production-filter-card">
        <label>Search<input onChange={(event) => updateQuery('search', event.target.value)} placeholder="Search plans, orders, products, or actions" value={query.search} /></label>
        {(tab === 'plans' || tab === 'orders') && <label>Status<select onChange={(event) => updateQuery('status', event.target.value)} value={query.status}><option value="">All statuses</option>{(tab === 'plans' ? ['draft', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled'] : ['scheduled', 'in_progress', 'completed', 'cancelled']).map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}</select></label>}
        {(tab === 'plans' || tab === 'orders') && <label>Product<select onChange={(event) => updateQuery('product_id', event.target.value)} value={query.product_id}><option value="">All products</option>{options.products.map((product) => <option key={product.id} value={product.id}>{optionLabel(product)}</option>)}</select></label>}
        {tab === 'history' && <label>Module<select onChange={(event) => updateQuery('module', event.target.value)} value={query.module || ''}><option value="">All production modules</option><option value="production-plans">Production Plans</option><option value="production-orders">Production Orders</option><option value="production-progress">Progress</option><option value="material-consumptions">Material Consumption</option><option value="finished-goods">Finished Goods</option></select></label>}
        <label>Date from<input onChange={(event) => updateQuery('date_from', event.target.value)} type="date" value={query.date_from} /></label>
        <label>Date to<input onChange={(event) => updateQuery('date_to', event.target.value)} type="date" value={query.date_to} /></label>
        <label>Sort<select onChange={(event) => updateQuery('sort', event.target.value)} value={query.sort}><option value="id">Newest</option><option value="created_at">Created</option><option value="status">Status</option><option value="planned_quantity">Quantity</option></select></label>
        <button className="button button-quiet" onClick={() => { setQuery({ search: '', status: '', product_id: '', date_from: '', date_to: '', page: 1, per_page: 15, sort: 'id', direction: 'desc' }); setSuccess('Filters reset.') }} type="button">Reset</button>
      </div>

      <div className="section-card production-register-card">
        <div className="section-card-heading"><div><p className="eyebrow">Traceable register</p><h2>{tabs.find((item) => item.key === tab)?.label}</h2></div><span>{meta.total || 0} records</span></div>
        {loading ? <div className="loading-state">Loading production records…</div> : rows.length === 0 ? <div className="empty-state"><strong>No production records match the current filters.</strong><span>Create a Production Plan from an approved Supply Plan to begin the execution trail.</span></div> : (
          <div className="table-wrap">
            {tab === 'plans' && <table><thead><tr><th>Plan</th><th>Product</th><th>Quantity</th><th>Window</th><th>Status</th><th>Action</th></tr></thead><tbody>{rows.map((plan) => <tr key={plan.id}><td><strong>{plan.plan_number}</strong><small>{plan.supply_plan ? `Supply Plan #${plan.supply_plan.id}` : 'Buyer Order source'}</small></td><td>{plan.product?.code}<small>{plan.product_variant?.code || 'All variants'}</small></td><td>{formatNumber(plan.planned_quantity)}</td><td>{plan.planned_start_date}<small>to {plan.planned_end_date}</small></td><td><span className={statusClass(plan.status)}>{plan.status.replaceAll('_', ' ')}</span></td><td><button className="table-action" onClick={() => openPlan(plan)} type="button">Open</button></td></tr>)}</tbody></table>}
            {tab === 'orders' && <table><thead><tr><th>Order</th><th>Product</th><th>Planned</th><th>Progress</th><th>Due</th><th>Status</th><th>Action</th></tr></thead><tbody>{rows.map((order) => <tr key={order.id}><td><strong>{order.order_number}</strong><small>{order.production_plan?.plan_number || 'Production Plan'}</small></td><td>{order.product?.code}<small>{order.product_variant?.code || 'All variants'}</small></td><td>{formatNumber(order.planned_quantity)}</td><td>{formatNumber(order.completed_quantity)}<small>{formatNumber(order.progress_percentage)}%</small></td><td>{order.expected_completion_date}</td><td><span className={statusClass(order.status)}>{order.status.replaceAll('_', ' ')}</span></td><td><button className="table-action" onClick={() => openOrder(order)} type="button">Open</button></td></tr>)}</tbody></table>}
            {tab === 'progress' && <table><thead><tr><th>Order</th><th>Date</th><th>Completed</th><th>Rejected</th><th>Remaining</th><th>Progress</th></tr></thead><tbody>{rows.map((progress) => <tr key={progress.id}><td>{progress.production_order?.order_number}</td><td>{progress.production_date}</td><td>{formatNumber(progress.completed_quantity)}</td><td>{formatNumber(progress.rejected_quantity)}</td><td>{formatNumber(progress.remaining_quantity)}</td><td><strong>{formatNumber(progress.progress_percentage)}%</strong></td></tr>)}</tbody></table>}
            {tab === 'consumptions' && <table><thead><tr><th>Consumption</th><th>Order</th><th>Material</th><th>Quantity</th><th>Date</th><th>Inventory</th></tr></thead><tbody>{rows.map((consumption) => <tr key={consumption.id}><td><strong>{consumption.consumption_number}</strong></td><td>{consumption.production_order?.order_number}</td><td>{consumption.material?.code}<small>{consumption.material?.name}</small></td><td>{formatNumber(consumption.quantity)} {consumption.unit?.symbol || consumption.unit?.code}</td><td>{consumption.consumption_date}</td><td>{consumption.inventory_transaction?.transaction_number || '—'}</td></tr>)}</tbody></table>}
            {tab === 'finishedGoods' && <table><thead><tr><th>Output</th><th>Order</th><th>Product</th><th>Quantity</th><th>Destination</th><th>Date</th></tr></thead><tbody>{rows.map((output) => <tr key={output.id}><td><strong>{output.finished_goods_number}</strong></td><td>{output.production_order?.order_number}</td><td>{output.product?.code}<small>{output.product_variant?.code || 'Product output'}</small></td><td>{formatNumber(output.quantity)} {output.unit?.symbol || output.unit?.code}</td><td>{output.warehouse?.code}<small>{output.warehouse_location?.code || 'Warehouse total'}</small></td><td>{output.finished_date}</td></tr>)}</tbody></table>}
            {tab === 'history' && <table><thead><tr><th>Time</th><th>Module</th><th>Action</th><th>Record</th><th>User</th></tr></thead><tbody>{rows.map((entry) => <tr key={entry.id}><td>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</td><td>{entry.module}</td><td><span className="status-pill status-history">{entry.action}</span></td><td>{entry.record_type?.split('\\').pop()} #{entry.record_id}</td><td>{entry.user?.name || '—'}</td></tr>)}</tbody></table>}
          </div>
        )}
        <div className="pagination-row"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="button button-quiet" disabled={(meta.current_page || 1) <= 1} onClick={() => setQuery((current) => ({ ...current, page: current.page - 1 }))} type="button">Previous</button><button className="button button-quiet" disabled={(meta.current_page || 1) >= (meta.last_page || 1)} onClick={() => setQuery((current) => ({ ...current, page: current.page + 1 }))} type="button">Next</button></div></div>
      </div>

      {modal === 'planForm' && <div className="modal-backdrop"><div className="modal-card"><div className="modal-heading"><div><p className="eyebrow">Execution planning</p><h2>New Production Plan</h2></div><button className="modal-close" onClick={closeModal} type="button">×</button></div><form className="form-grid" onSubmit={submitPlan}><label>Product<select required onChange={(event) => updateForm(setForm, 'product_id', event.target.value)} value={form.product_id}><option value="">Select product</option>{options.products.map((product) => <option key={product.id} value={product.id}>{optionLabel(product)}</option>)}</select></label><label>Product variant<select onChange={(event) => updateForm(setForm, 'product_variant_id', event.target.value)} value={form.product_variant_id}><option value="">All variants</option>{productVariants.map((variant) => <option key={variant.id} value={variant.id}>{optionLabel(variant)}</option>)}</select></label><label>Supply Plan source<select onChange={(event) => updateForm(setForm, 'supply_plan_id', event.target.value)} value={form.supply_plan_id}><option value="">Select Supply Plan</option>{options.supplyPlans.filter((plan) => !form.product_id || String(plan.product_id) === String(form.product_id)).map((plan) => <option key={plan.id} value={plan.id}>#{plan.id} · {plan.product?.code || `Product ${plan.product_id}`} · {formatNumber(plan.planned_production_quantity)}</option>)}</select></label><label>Buyer Order source<input min="1" onChange={(event) => updateForm(setForm, 'buyer_order_id', event.target.value)} placeholder="Optional Buyer Order ID" type="number" value={form.buyer_order_id} /></label><label>Planned quantity<input min="0.0001" required onChange={(event) => updateForm(setForm, 'planned_quantity', event.target.value)} step="0.0001" type="number" value={form.planned_quantity} /></label><label>Planned start<input required onChange={(event) => updateForm(setForm, 'planned_start_date', event.target.value)} type="date" value={form.planned_start_date} /></label><label>Planned end<input required onChange={(event) => updateForm(setForm, 'planned_end_date', event.target.value)} type="date" value={form.planned_end_date} /></label><label>Priority<select onChange={(event) => updateForm(setForm, 'priority', event.target.value)} value={form.priority}><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><label className="form-span-2">Remarks<textarea onChange={(event) => updateForm(setForm, 'remarks', event.target.value)} value={form.remarks} /></label><div className="modal-actions"><button className="button button-quiet" onClick={closeModal} type="button">Cancel</button><button className="button button-primary" disabled={submitting || optionsLoading} type="submit">{submitting ? 'Saving…' : 'Create plan'}</button></div></form></div></div>}

      {modal === 'orderForm' && <div className="modal-backdrop"><div className="modal-card"><div className="modal-heading"><div><p className="eyebrow">Execution order</p><h2>New Production Order</h2></div><button className="modal-close" onClick={closeModal} type="button">×</button></div><form className="form-grid" onSubmit={submitOrder}><label>Approved plan<select required onChange={(event) => updateForm(setOrderForm, 'production_plan_id', event.target.value)} value={orderForm.production_plan_id}><option value="">Select approved plan</option>{orderPlans.map((plan) => <option key={plan.id} value={plan.id}>{plan.plan_number} · {plan.product?.code} · {formatNumber(plan.planned_quantity)}</option>)}</select></label><label>Planned quantity<input min="0.0001" onChange={(event) => updateForm(setOrderForm, 'planned_quantity', event.target.value)} placeholder="Uses plan quantity when blank" step="0.0001" type="number" value={orderForm.planned_quantity} /></label><label>Issue warehouse<select required onChange={(event) => updateForm(setOrderForm, 'issue_warehouse_id', event.target.value)} value={orderForm.issue_warehouse_id}><option value="">Select warehouse</option>{options.warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{optionLabel(warehouse)}</option>)}</select></label><label>Issue location<select onChange={(event) => updateForm(setOrderForm, 'issue_warehouse_location_id', event.target.value)} value={orderForm.issue_warehouse_location_id}><option value="">Warehouse total</option>{orderLocations.map((location) => <option key={location.id} value={location.id}>{optionLabel(location)}</option>)}</select></label><label>Expected completion<input required onChange={(event) => updateForm(setOrderForm, 'expected_completion_date', event.target.value)} type="date" value={orderForm.expected_completion_date} /></label><label className="form-span-2">Remarks<textarea onChange={(event) => updateForm(setOrderForm, 'remarks', event.target.value)} value={orderForm.remarks} /></label><div className="modal-actions"><button className="button button-quiet" onClick={closeModal} type="button">Cancel</button><button className="button button-primary" disabled={submitting || optionsLoading} type="submit">{submitting ? 'Saving…' : 'Create order'}</button></div></form></div></div>}

      {modal === 'planDetail' && selectedPlan && <div className="modal-backdrop"><div className="modal-card modal-card-wide"><div className="modal-heading"><div><p className="eyebrow">Production Plan detail</p><h2>{selectedPlan.plan_number}</h2><span className={statusClass(selectedPlan.status)}>{selectedPlan.status.replaceAll('_', ' ')}</span></div><button className="modal-close" onClick={closeModal} type="button">×</button></div><div className="detail-grid"><div><span>Product</span><strong>{selectedPlan.product?.code}</strong></div><div><span>Quantity</span><strong>{formatNumber(selectedPlan.planned_quantity)}</strong></div><div><span>Window</span><strong>{selectedPlan.planned_start_date} → {selectedPlan.planned_end_date}</strong></div><div><span>Priority</span><strong>{selectedPlan.priority}</strong></div><div><span>Source</span><strong>{selectedPlan.supply_plan ? `Supply Plan #${selectedPlan.supply_plan.id}` : selectedPlan.buyer_order ? `Buyer Order ${selectedPlan.buyer_order.order_number}` : '—'}</strong></div></div>{selectedPlan.remarks && <p className="detail-note">{selectedPlan.remarks}</p>}<div className="modal-actions"><button className="button button-quiet" onClick={closeModal} type="button">Close</button>{selectedPlan.status === 'draft' && <button className="button button-primary" disabled={submitting} onClick={approvePlan} type="button">Approve plan</button>}</div></div></div>}

      {modal === 'orderDetail' && selectedOrder && <div className="modal-backdrop"><div className="modal-card modal-card-wide"><div className="modal-heading"><div><p className="eyebrow">Production Order detail</p><h2>{selectedOrder.order_number}</h2><span className={statusClass(selectedOrder.status)}>{selectedOrder.status.replaceAll('_', ' ')}</span></div><button className="modal-close" onClick={closeModal} type="button">×</button></div><div className="detail-grid"><div><span>Product</span><strong>{selectedOrder.product?.code} {selectedOrder.product_variant?.code ? `· ${selectedOrder.product_variant.code}` : ''}</strong></div><div><span>Planned</span><strong>{formatNumber(selectedOrder.planned_quantity)}</strong></div><div><span>Completed</span><strong>{formatNumber(selectedOrder.completed_quantity)} ({formatNumber(selectedOrder.progress_percentage)}%)</strong></div><div><span>Issue location</span><strong>{selectedOrder.issue_warehouse?.code} · {selectedOrder.issue_warehouse_location?.code || 'Warehouse total'}</strong></div><div><span>BOM version</span><strong>v{selectedOrder.bom_version?.version_number}</strong></div><div><span>Due</span><strong>{selectedOrder.expected_completion_date}</strong></div></div><div className="availability-panel"><div className="section-card-heading"><h3>Material availability</h3><span className={availability?.available ? 'availability-good' : 'availability-short'}>{availability?.available ? 'Available to start' : 'Shortage detected'}</span></div>{availability?.lines?.map((line) => <div className="availability-line" key={line.production_order_item_id}><div><strong>{line.material.code}</strong><span>{line.material.name}</span></div><span>Required {formatNumber(line.required_quantity)}</span><span>Available {formatNumber(line.available_quantity)}</span><span className={line.shortage_quantity > 0 ? 'availability-short' : 'availability-good'}>{line.shortage_quantity > 0 ? `Short ${formatNumber(line.shortage_quantity)}` : 'Covered'}</span></div>)}</div><div className="detail-lines"><h3>BOM material lines</h3>{selectedOrder.items?.map((item) => <div className="line-card" key={item.id}><strong>{item.material?.code}</strong><span>Required {formatNumber(item.required_quantity)} {item.unit?.symbol || item.unit?.code}</span><span>Consumed {formatNumber(item.consumed_quantity)}</span><span>Remaining {formatNumber(item.remaining_quantity)}</span></div>)}</div>{selectedOrder.status === 'in_progress' && <div className="production-action-grid"><form className="action-panel" onSubmit={submitConsumption}><h3>Consume material</h3><label>Material line<select required onChange={(event) => updateForm(setConsumptionForm, 'production_order_item_id', event.target.value)} value={consumptionForm.production_order_item_id}><option value="">Select line</option>{selectedOrder.items?.map((item) => <option key={item.id} value={item.id}>{item.material?.code} · remaining {formatNumber(item.remaining_quantity)}</option>)}</select></label><label>Quantity<input min="0.0001" required onChange={(event) => updateForm(setConsumptionForm, 'quantity', event.target.value)} step="0.0001" type="number" value={consumptionForm.quantity} /></label><button className="button button-primary" disabled={submitting} type="submit">Post material issue</button></form><form className="action-panel" onSubmit={submitProgress}><h3>Record progress</h3><label>Completed quantity<input min="0" required onChange={(event) => updateForm(setProgressForm, 'completed_quantity', event.target.value)} step="0.0001" type="number" value={progressForm.completed_quantity} /></label><label>Rejected placeholder<input min="0" onChange={(event) => updateForm(setProgressForm, 'rejected_quantity', event.target.value)} step="0.0001" type="number" value={progressForm.rejected_quantity} /></label><button className="button button-secondary" disabled={submitting} type="submit">Record progress</button></form><form className="action-panel" onSubmit={submitCompletion}><h3>Complete and receive</h3><label>Finished quantity<input min="0.0001" required onChange={(event) => updateForm(setCompletionForm, 'finished_quantity', event.target.value)} step="0.0001" type="number" value={completionForm.finished_quantity} /></label><label>Completed quantity<input min="0" onChange={(event) => updateForm(setCompletionForm, 'completed_quantity', event.target.value)} step="0.0001" type="number" value={completionForm.completed_quantity} /></label><button className="button button-primary" disabled={submitting} type="submit">Post finished goods</button></form></div>}{selectedOrder.status === 'scheduled' && <div className="modal-actions"><button className="button button-primary" disabled={submitting} onClick={startOrder} type="button">Start production</button></div>}<div className="modal-actions"><button className="button button-quiet" onClick={closeModal} type="button">Close</button></div></div></div>}
    </section>
  )
}

export default ProductionPage
