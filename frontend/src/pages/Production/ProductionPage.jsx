import { useCallback, useEffect, useMemo, useState } from 'react'
import buyerOrderService from '../../services/buyerOrderService'
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

const today = () => new Date().toISOString().slice(0, 10)
const futureDate = (days = 14) => {
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toISOString().slice(0, 10)
}

const emptyPlan = () => ({
  product_id: '',
  product_variant_id: '',
  supply_plan_id: '',
  buyer_order_id: '',
  planned_quantity: '',
  planned_start_date: today(),
  planned_end_date: futureDate(14),
  priority: 'normal',
  remarks: '',
})

const emptyOrder = () => ({
  production_plan_id: '',
  planned_quantity: '',
  expected_completion_date: futureDate(14),
  issue_warehouse_id: '',
  issue_warehouse_location_id: '',
  remarks: '',
})

const emptyConsumption = () => ({
  production_order_item_id: '',
  quantity: '',
  consumption_date: today(),
  idempotency_key: '',
  remarks: '',
})

const emptyProgress = () => ({
  completed_quantity: '',
  rejected_quantity: '0',
  production_date: today(),
  remarks: '',
})

const emptyCompletion = () => ({
  finished_quantity: '',
  completed_quantity: '',
  rejected_quantity: '0',
  finished_date: today(),
  remarks: '',
})

function errorMessage(error) {
  const response = error?.response?.data
  if (response?.errors && typeof response.errors === 'object') {
    const errorList = Object.values(response.errors).flat()
    if (errorList.length > 0) return errorList.join(' | ')
  }
  return response?.message || error?.message || 'The production request could not be completed.'
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
  const [options, setOptions] = useState({ products: [], variants: [], warehouses: [], locations: [], supplyPlans: [], buyerOrders: [] })
  const [productionPlans, setProductionPlans] = useState([])
  const [summary, setSummary] = useState({ plans: 0, orders: 0, activeOrders: 0, outputs: 0 })
  const [loading, setLoading] = useState(true)
  const [optionsLoading, setOptionsLoading] = useState(true)
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
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
  const filteredSupplyPlans = useMemo(
    () => options.supplyPlans.filter((plan) => !form.product_id || String(plan.product_id) === String(form.product_id)),
    [form.product_id, options.supplyPlans],
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
      const [products, variants, warehouses, locations, supplyPlans, buyerOrders] = await Promise.all([
        masterDataService.options('products'),
        masterDataService.options('product-variants'),
        masterDataService.options('warehouses'),
        masterDataService.options('warehouse-locations'),
        planningService.listSupplyPlans({ per_page: 100 }),
        buyerOrderService.list({ per_page: 100 }),
      ])
      setOptions({
        products: products || [],
        variants: variants || [],
        warehouses: warehouses || [],
        locations: locations || [],
        supplyPlans: supplyPlans?.data || [],
        buyerOrders: buyerOrders?.data || [],
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
  
  const updateForm = (setter, field, value) => {
    setModalError('')
    setter((current) => ({ ...current, [field]: value }))
  }

  const onProductChange = (productId) => {
    setModalError('')
    setForm((current) => ({
      ...current,
      product_id: productId,
      product_variant_id: '',
      supply_plan_id: '',
    }))
  }

  const onSupplyPlanChange = (supplyPlanId) => {
    setModalError('')
    const selected = options.supplyPlans.find((p) => String(p.id) === String(supplyPlanId))
    setForm((current) => ({
      ...current,
      supply_plan_id: supplyPlanId,
      buyer_order_id: selected?.buyer_order_id ? String(selected.buyer_order_id) : current.buyer_order_id,
      product_id: selected?.product_id ? String(selected.product_id) : current.product_id,
      planned_quantity: selected?.planned_production_quantity ? String(selected.planned_production_quantity) : current.planned_quantity,
    }))
  }

  const onProductionPlanSelect = (planId) => {
    setModalError('')
    const selected = productionPlans.find((p) => String(p.id) === String(planId))
    setOrderForm((current) => ({
      ...current,
      production_plan_id: planId,
      planned_quantity: selected?.planned_quantity ? String(selected.planned_quantity) : current.planned_quantity,
      expected_completion_date: selected?.planned_end_date || current.expected_completion_date,
    }))
  }

  const openPlan = async (plan) => {
    setSubmitting(true)
    setModalError('')
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
    setModalError('')
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
    setModalError('')
  }

  const submitPlan = async (event) => {
    event.preventDefault()
    setModalError('')
    setError('')
    setSuccess('')

    if (!form.product_id) {
      setModalError('Please select a Product.')
      return
    }
    if (!form.supply_plan_id && !form.buyer_order_id) {
      setModalError('Please select either a Supply Plan source or a Buyer Order source.')
      return
    }
    if (!form.planned_quantity || Number(form.planned_quantity) <= 0) {
      setModalError('Planned quantity must be greater than zero.')
      return
    }
    if (!form.planned_start_date || !form.planned_end_date) {
      setModalError('Start and End dates are required.')
      return
    }
    if (form.planned_end_date < form.planned_start_date) {
      setModalError('Planned end date must be on or after start date.')
      return
    }

    setSubmitting(true)
    try {
      await productionService.plans.create({
        ...form,
        product_id: Number(form.product_id),
        product_variant_id: form.product_variant_id ? Number(form.product_variant_id) : null,
        supply_plan_id: form.supply_plan_id ? Number(form.supply_plan_id) : null,
        buyer_order_id: form.buyer_order_id ? Number(form.buyer_order_id) : null,
        planned_quantity: Number(form.planned_quantity),
      })
      setForm(emptyPlan())
      setModal(null)
      await refresh('Production Plan created successfully.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitOrder = async (event) => {
    event.preventDefault()
    setModalError('')
    setError('')
    setSuccess('')

    if (!orderForm.production_plan_id) {
      setModalError('Please select an Approved Production Plan.')
      return
    }
    if (!orderForm.issue_warehouse_id) {
      setModalError('Please select an Issue Warehouse for material staging.')
      return
    }

    setSubmitting(true)
    try {
      await productionService.orders.create({
        ...orderForm,
        production_plan_id: Number(orderForm.production_plan_id),
        issue_warehouse_id: Number(orderForm.issue_warehouse_id),
        planned_quantity: orderForm.planned_quantity ? Number(orderForm.planned_quantity) : null,
        issue_warehouse_location_id: orderForm.issue_warehouse_location_id ? Number(orderForm.issue_warehouse_location_id) : null,
      })
      setOrderForm(emptyOrder())
      setModal(null)
      await refresh('Production Order created successfully.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const approvePlan = async () => {
    if (!selectedPlan) return
    setSubmitting(true)
    setModalError('')
    try {
      const plan = await productionService.plans.approve(selectedPlan.id)
      setSelectedPlan(plan)
      await refresh('Production Plan approved successfully.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const startOrder = async () => {
    if (!selectedOrder) return
    setSubmitting(true)
    setModalError('')
    try {
      const order = await productionService.orders.start(selectedOrder.id)
      setSelectedOrder(order)
      setAvailability(await productionService.orders.availability(order.id))
      await refresh('Production Order started successfully.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitConsumption = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    setModalError('')
    try {
      await productionService.orders.consume(selectedOrder.id, {
        ...consumptionForm,
        production_order_item_id: Number(consumptionForm.production_order_item_id),
        quantity: Number(consumptionForm.quantity),
        idempotency_key: consumptionForm.idempotency_key || undefined,
      })
      setConsumptionForm(emptyConsumption())
      const detail = await productionService.orders.get(selectedOrder.id)
      setSelectedOrder(detail)
      setAvailability(await productionService.orders.availability(selectedOrder.id))
      await refresh('Material consumption posted and inventory decreased.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitProgress = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    setModalError('')
    try {
      await productionService.orders.progress(selectedOrder.id, {
        ...progressForm,
        completed_quantity: Number(progressForm.completed_quantity),
        rejected_quantity: Number(progressForm.rejected_quantity || 0),
      })
      setProgressForm(emptyProgress())
      const detail = await productionService.orders.get(selectedOrder.id)
      setSelectedOrder(detail)
      await refresh('Production progress recorded successfully.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  const submitCompletion = async (event) => {
    event.preventDefault()
    if (!selectedOrder) return
    setSubmitting(true)
    setModalError('')
    try {
      const completed = await productionService.orders.complete(selectedOrder.id, {
        ...completionForm,
        finished_quantity: Number(completionForm.finished_quantity),
        completed_quantity: completionForm.completed_quantity ? Number(completionForm.completed_quantity) : undefined,
        rejected_quantity: Number(completionForm.rejected_quantity || 0),
      })
      setSelectedOrder(completed)
      setCompletionForm(emptyCompletion())
      await refresh('Production completed and finished goods posted to inventory.')
    } catch (submitError) {
      setModalError(errorMessage(submitError))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="production-page master-data-page">
      <div className="page-intro production-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 8 · Production Management</p>
          <h1>Production Control Center</h1>
          <p>Translate supply plans into traceable production orders, material issues, line progress, and finished goods.</p>
        </div>
        <div className="procurement-header-actions">
          <button className="primary-button" onClick={() => { setForm(emptyPlan()); setModalError(''); setModal('planForm') }} type="button">
            + New Production Plan
          </button>
          <button className="secondary-button" onClick={() => { setOrderForm(emptyOrder()); setModalError(''); setModal('orderForm') }} type="button">
            + New Production Order
          </button>
        </div>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}
      {success && <div className="feedback-message success-message" role="status">{success}</div>}

      <div className="summary-grid production-summary-grid">
        <article className="summary-card">
          <span>Production Plans</span>
          <strong>{formatNumber(summary.plans)}</strong>
          <small>Demand translated into execution</small>
        </article>
        <article className="summary-card">
          <span>Production Orders</span>
          <strong>{formatNumber(summary.orders)}</strong>
          <small>Traceable floor orders</small>
        </article>
        <article className="summary-card">
          <span>In Progress</span>
          <strong>{formatNumber(summary.activeOrders)}</strong>
          <small>Active on production line</small>
        </article>
        <article className="summary-card summary-card-accent">
          <span>Finished Goods Records</span>
          <strong>{formatNumber(summary.outputs)}</strong>
          <small>Output ready for inventory</small>
        </article>
      </div>

      <div aria-label="Production views" className="planning-tabs procurement-tabs" role="tablist">
        {tabs.map((item) => (
          <button
            className={tab === item.key ? 'active' : ''}
            key={item.key}
            onClick={() => { setTab(item.key); setQuery((current) => ({ ...current, page: 1 })) }}
            role="tab"
            type="button"
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="master-data-toolbar procurement-toolbar">
        <label className="search-field">
          <span>Search</span>
          <input onChange={(event) => updateQuery('search', event.target.value)} placeholder="Search plans, orders, products, or actions" value={query.search} />
        </label>
        {(tab === 'plans' || tab === 'orders') && (
          <label className="filter-field">
            <span>Status</span>
            <select onChange={(event) => updateQuery('status', event.target.value)} value={query.status}>
              <option value="">All statuses</option>
              {(tab === 'plans' ? ['draft', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled'] : ['scheduled', 'in_progress', 'completed', 'cancelled']).map((status) => (
                <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>
              ))}
            </select>
          </label>
        )}
        {(tab === 'plans' || tab === 'orders') && (
          <label className="filter-field">
            <span>Product</span>
            <select onChange={(event) => updateQuery('product_id', event.target.value)} value={query.product_id}>
              <option value="">All products</option>
              {options.products.map((product) => (
                <option key={product.id} value={product.id}>{optionLabel(product)}</option>
              ))}
            </select>
          </label>
        )}
        {tab === 'history' && (
          <label className="filter-field">
            <span>Module</span>
            <select onChange={(event) => updateQuery('module', event.target.value)} value={query.module || ''}>
              <option value="">All production modules</option>
              <option value="production-plans">Production Plans</option>
              <option value="production-orders">Production Orders</option>
              <option value="production-progress">Progress</option>
              <option value="material-consumptions">Material Consumption</option>
              <option value="finished-goods">Finished Goods</option>
            </select>
          </label>
        )}
        <label className="filter-field">
          <span>Date From</span>
          <input onChange={(event) => updateQuery('date_from', event.target.value)} type="date" value={query.date_from} />
        </label>
        <label className="filter-field">
          <span>Date To</span>
          <input onChange={(event) => updateQuery('date_to', event.target.value)} type="date" value={query.date_to} />
        </label>
        <span className="record-count">{meta.total || 0} records</span>
      </div>

      <section aria-busy={loading} className="data-card">
        <div className="data-card-header">
          <div>
            <p className="eyebrow">Traceable register</p>
            <h2>{tabs.find((item) => item.key === tab)?.label}</h2>
          </div>
          <span className="data-card-hint">Workflow validated by manufacturing rules</span>
        </div>

        {loading ? (
          <div className="empty-state">Loading production records…</div>
        ) : rows.length === 0 ? (
          <div className="empty-state">
            <strong>No production records match the current filters.</strong>
            <span>Create a Production Plan from an approved Supply Plan to begin the execution trail.</span>
          </div>
        ) : (
          <div className="table-wrap">
            {tab === 'plans' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Plan #</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Window</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((plan) => (
                    <tr key={plan.id} onClick={() => openPlan(plan)}>
                      <td><strong>{plan.plan_number}</strong><br /><small style={{ color: 'var(--slate-500)' }}>{plan.supply_plan ? `Supply Plan #${plan.supply_plan.id}` : 'Buyer Order source'}</small></td>
                      <td>{plan.product?.code}<br /><small style={{ color: 'var(--slate-500)' }}>{plan.product_variant?.code || 'All variants'}</small></td>
                      <td>{formatNumber(plan.planned_quantity)}</td>
                      <td>{plan.planned_start_date}<br /><small style={{ color: 'var(--slate-500)' }}>to {plan.planned_end_date}</small></td>
                      <td><span className={statusClass(plan.status)}>{plan.status.replaceAll('_', ' ')}</span></td>
                      <td>
                        <button className="text-button" onClick={(e) => { e.stopPropagation(); openPlan(plan) }} type="button">Open</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {tab === 'orders' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Order #</th>
                    <th>Product</th>
                    <th>Planned</th>
                    <th>Progress</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((order) => (
                    <tr key={order.id} onClick={() => openOrder(order)}>
                      <td><strong>{order.order_number}</strong><br /><small style={{ color: 'var(--slate-500)' }}>{order.production_plan?.plan_number || 'Plan source'}</small></td>
                      <td>{order.product?.code}<br /><small style={{ color: 'var(--slate-500)' }}>{order.product_variant?.code || 'All variants'}</small></td>
                      <td>{formatNumber(order.planned_quantity)}</td>
                      <td>{formatNumber(order.completed_quantity)} ({formatNumber(order.progress_percentage)}%)</td>
                      <td>{order.expected_completion_date}</td>
                      <td><span className={statusClass(order.status)}>{order.status.replaceAll('_', ' ')}</span></td>
                      <td>
                        <button className="text-button" onClick={(e) => { e.stopPropagation(); openOrder(order) }} type="button">Open</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {tab === 'progress' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Date</th>
                    <th>Completed</th>
                    <th>Rejected</th>
                    <th>Remaining</th>
                    <th>Progress</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((progress) => (
                    <tr key={progress.id}>
                      <td><strong>{progress.production_order?.order_number}</strong></td>
                      <td>{progress.production_date}</td>
                      <td>{formatNumber(progress.completed_quantity)}</td>
                      <td>{formatNumber(progress.rejected_quantity)}</td>
                      <td>{formatNumber(progress.remaining_quantity)}</td>
                      <td><strong>{formatNumber(progress.progress_percentage)}%</strong></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {tab === 'consumptions' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Consumption #</th>
                    <th>Order</th>
                    <th>Material</th>
                    <th>Quantity</th>
                    <th>Date</th>
                    <th>Inventory TX</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((consumption) => (
                    <tr key={consumption.id}>
                      <td><strong>{consumption.consumption_number}</strong></td>
                      <td>{consumption.production_order?.order_number}</td>
                      <td>{consumption.material?.code}<br /><small style={{ color: 'var(--slate-500)' }}>{consumption.material?.name}</small></td>
                      <td>{formatNumber(consumption.quantity)} {consumption.unit?.symbol || consumption.unit?.code}</td>
                      <td>{consumption.consumption_date}</td>
                      <td>{consumption.inventory_transaction?.transaction_number || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {tab === 'finishedGoods' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Output #</th>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Destination</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((output) => (
                    <tr key={output.id}>
                      <td><strong>{output.finished_goods_number}</strong></td>
                      <td>{output.production_order?.order_number}</td>
                      <td>{output.product?.code}<br /><small style={{ color: 'var(--slate-500)' }}>{output.product_variant?.code || 'Standard'}</small></td>
                      <td>{formatNumber(output.quantity)} {output.unit?.symbol || output.unit?.code}</td>
                      <td>{output.warehouse?.code}<br /><small style={{ color: 'var(--slate-500)' }}>{output.warehouse_location?.code || 'General location'}</small></td>
                      <td>{output.finished_date}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {tab === 'history' && (
              <table className="master-data-table">
                <thead>
                  <tr>
                    <th>Time</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Record</th>
                    <th>User</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((entry) => (
                    <tr key={entry.id}>
                      <td>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</td>
                      <td>{entry.module}</td>
                      <td><span className="status-pill status-history">{entry.action}</span></td>
                      <td>{entry.record_type?.split('\\').pop()} #{entry.record_id}</td>
                      <td>{entry.user?.name || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        <div className="pagination-bar">
          <span>Page {meta.current_page || 1} of {meta.last_page || 1}</span>
          <div>
            <button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => updateQuery('page', (meta.current_page || 1) - 1)} type="button">Previous</button>
            <button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => updateQuery('page', (meta.current_page || 1) + 1)} type="button">Next</button>
          </div>
        </div>
      </section>

      {/* NEW PRODUCTION PLAN MODAL */}
      {modal === 'planForm' && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-labelledby="new-plan-title" aria-modal="true" className="modal-card planning-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Execution Planning</p>
                <h2 id="new-plan-title">New Production Plan</h2>
              </div>
              <button aria-label="Close form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>
                {modalError}
              </div>
            )}

            <form className="master-data-form" onSubmit={submitPlan}>
              <div className="form-grid">
                <label className="form-field">
                  <span>Product *</span>
                  <select
                    onChange={(event) => onProductChange(event.target.value)}
                    required
                    value={form.product_id}
                  >
                    <option value="">Select product</option>
                    {options.products.map((product) => (
                      <option key={product.id} value={product.id}>{optionLabel(product)}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Product Variant</span>
                  <select
                    onChange={(event) => updateForm(setForm, 'product_variant_id', event.target.value)}
                    value={form.product_variant_id}
                  >
                    <option value="">All variants (default)</option>
                    {productVariants.map((variant) => (
                      <option key={variant.id} value={variant.id}>{optionLabel(variant)}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Supply Plan Source *</span>
                  <select
                    onChange={(event) => onSupplyPlanChange(event.target.value)}
                    value={form.supply_plan_id}
                  >
                    <option value="">Select supply plan</option>
                    {filteredSupplyPlans.map((plan) => (
                      <option key={plan.id} value={plan.id}>
                        Plan #{plan.id} · {plan.product?.code || `Product ${plan.product_id}`} (Qty: {formatNumber(plan.planned_production_quantity)})
                      </option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Buyer Order Source (Alternative)</span>
                  <select
                    onChange={(event) => updateForm(setForm, 'buyer_order_id', event.target.value)}
                    value={form.buyer_order_id}
                  >
                    <option value="">Select buyer order</option>
                    {options.buyerOrders.map((bo) => (
                      <option key={bo.id} value={bo.id}>
                        {bo.order_number} · {bo.buyer?.name} (Qty: {formatNumber(bo.total_quantity)})
                      </option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Planned Production Quantity *</span>
                  <input
                    min="0.0001"
                    onChange={(event) => updateForm(setForm, 'planned_quantity', event.target.value)}
                    placeholder="e.g. 1000"
                    required
                    step="any"
                    type="number"
                    value={form.planned_quantity}
                  />
                </label>

                <label className="form-field">
                  <span>Priority *</span>
                  <select
                    onChange={(event) => updateForm(setForm, 'priority', event.target.value)}
                    value={form.priority}
                  >
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </label>

                <label className="form-field">
                  <span>Planned Start Date *</span>
                  <input
                    onChange={(event) => updateForm(setForm, 'planned_start_date', event.target.value)}
                    required
                    type="date"
                    value={form.planned_start_date}
                  />
                </label>

                <label className="form-field">
                  <span>Planned End Date *</span>
                  <input
                    onChange={(event) => updateForm(setForm, 'planned_end_date', event.target.value)}
                    required
                    type="date"
                    value={form.planned_end_date}
                  />
                </label>

                <label className="form-field full-width">
                  <span>Execution Remarks & Special Requirements</span>
                  <textarea
                    onChange={(event) => updateForm(setForm, 'remarks', event.target.value)}
                    placeholder="Provide production line notes, special wash guidelines, or cutting instructions"
                    rows="2"
                    value={form.remarks}
                  />
                </label>
              </div>

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={submitting || optionsLoading} type="submit">
                  {submitting ? 'Creating…' : 'Create Production Plan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* NEW PRODUCTION ORDER MODAL */}
      {modal === 'orderForm' && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-labelledby="new-order-title" aria-modal="true" className="modal-card planning-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Execution Order</p>
                <h2 id="new-order-title">New Production Order</h2>
              </div>
              <button aria-label="Close form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>
                {modalError}
              </div>
            )}

            <form className="master-data-form" onSubmit={submitOrder}>
              <div className="form-grid">
                <label className="form-field full-width">
                  <span>Approved Production Plan *</span>
                  <select
                    onChange={(event) => onProductionPlanSelect(event.target.value)}
                    required
                    value={orderForm.production_plan_id}
                  >
                    <option value="">Select approved plan</option>
                    {orderPlans.map((plan) => (
                      <option key={plan.id} value={plan.id}>
                        {plan.plan_number} · {plan.product?.code} · Planned: {formatNumber(plan.planned_quantity)} units
                      </option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Order Quantity (Optional - defaults to plan)</span>
                  <input
                    min="0.0001"
                    onChange={(event) => updateForm(setOrderForm, 'planned_quantity', event.target.value)}
                    placeholder="Uses full plan quantity if blank"
                    step="any"
                    type="number"
                    value={orderForm.planned_quantity}
                  />
                </label>

                <label className="form-field">
                  <span>Expected Completion Date *</span>
                  <input
                    onChange={(event) => updateForm(setOrderForm, 'expected_completion_date', event.target.value)}
                    required
                    type="date"
                    value={orderForm.expected_completion_date}
                  />
                </label>

                <label className="form-field">
                  <span>Issue Warehouse (Material Staging) *</span>
                  <select
                    onChange={(event) => updateForm(setOrderForm, 'issue_warehouse_id', event.target.value)}
                    required
                    value={orderForm.issue_warehouse_id}
                  >
                    <option value="">Select warehouse</option>
                    {options.warehouses.map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>{optionLabel(warehouse)}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Issue Warehouse Location</span>
                  <select
                    onChange={(event) => updateForm(setOrderForm, 'issue_warehouse_location_id', event.target.value)}
                    value={orderForm.issue_warehouse_location_id}
                  >
                    <option value="">General warehouse floor</option>
                    {orderLocations.map((location) => (
                      <option key={location.id} value={location.id}>{optionLabel(location)}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field full-width">
                  <span>Floor Instructions / Remarks</span>
                  <textarea
                    onChange={(event) => updateForm(setOrderForm, 'remarks', event.target.value)}
                    placeholder="Notes for floor supervisors, sewing line allocation, or packaging specs"
                    rows="2"
                    value={orderForm.remarks}
                  />
                </label>
              </div>

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={submitting || optionsLoading} type="submit">
                  {submitting ? 'Creating…' : 'Create Production Order'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* PLAN DETAIL MODAL */}
      {modal === 'planDetail' && selectedPlan && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-modal="true" className="modal-card planning-modal-card procurement-detail-modal" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Production Plan Detail</p>
                <h2>{selectedPlan.plan_number}</h2>
                <span className={statusClass(selectedPlan.status)}>{selectedPlan.status.replaceAll('_', ' ')}</span>
              </div>
              <button aria-label="Close plan details" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ margin: '16px 24px' }}>
                {modalError}
              </div>
            )}

            <div className="order-detail-summary" style={{ margin: '20px 24px' }}>
              <div>
                <span>Product</span>
                <strong>{selectedPlan.product?.code} ({selectedPlan.product?.name})</strong>
              </div>
              <div>
                <span>Planned Quantity</span>
                <strong>{formatNumber(selectedPlan.planned_quantity)} units</strong>
              </div>
              <div>
                <span>Execution Window</span>
                <strong>{selectedPlan.planned_start_date} → {selectedPlan.planned_end_date}</strong>
              </div>
              <div>
                <span>Priority</span>
                <strong>{selectedPlan.priority}</strong>
              </div>
              <div>
                <span>Source Link</span>
                <strong>{selectedPlan.supply_plan ? `Supply Plan #${selectedPlan.supply_plan.id}` : selectedPlan.buyer_order ? `Buyer Order ${selectedPlan.buyer_order.order_number}` : 'Direct Plan'}</strong>
              </div>
            </div>

            {selectedPlan.remarks && (
              <div style={{ margin: '0 24px 16px', padding: '12px 16px', background: 'var(--slate-50)', borderRadius: 'var(--radius-md)', border: '1px solid var(--border)' }}>
                <span style={{ fontSize: '12px', color: 'var(--slate-500)', fontWeight: 600 }}>Remarks:</span>
                <p style={{ margin: '4px 0 0', fontSize: '14px', color: 'var(--slate-800)' }}>{selectedPlan.remarks}</p>
              </div>
            )}

            <div className="modal-actions" style={{ padding: '16px 24px' }}>
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
              {selectedPlan.status === 'draft' && (
                <button className="primary-button" disabled={submitting} onClick={approvePlan} type="button">
                  {submitting ? 'Approving…' : 'Approve Production Plan'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ORDER DETAIL & FLOOR ACTION MODAL */}
      {modal === 'orderDetail' && selectedOrder && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-modal="true" className="modal-card planning-modal-card procurement-detail-modal" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Production Order Detail</p>
                <h2>{selectedOrder.order_number}</h2>
                <span className={statusClass(selectedOrder.status)}>{selectedOrder.status.replaceAll('_', ' ')}</span>
              </div>
              <button aria-label="Close order details" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ margin: '16px 24px' }}>
                {modalError}
              </div>
            )}

            <div className="order-detail-summary" style={{ margin: '20px 24px' }}>
              <div>
                <span>Product</span>
                <strong>{selectedOrder.product?.code} {selectedOrder.product_variant?.code ? `· ${selectedOrder.product_variant.code}` : ''}</strong>
              </div>
              <div>
                <span>Planned Quantity</span>
                <strong>{formatNumber(selectedOrder.planned_quantity)}</strong>
              </div>
              <div>
                <span>Completed</span>
                <strong>{formatNumber(selectedOrder.completed_quantity)} ({formatNumber(selectedOrder.progress_percentage)}%)</strong>
              </div>
              <div>
                <span>Issue Location</span>
                <strong>{selectedOrder.issue_warehouse?.code} · {selectedOrder.issue_warehouse_location?.code || 'Warehouse floor'}</strong>
              </div>
              <div>
                <span>BOM Version</span>
                <strong>v{selectedOrder.bom_version?.version_number || '1.0'}</strong>
              </div>
              <div>
                <span>Target Due Date</span>
                <strong>{selectedOrder.expected_completion_date}</strong>
              </div>
            </div>

            {/* Material Availability Banner */}
            <div style={{ margin: '16px 24px', padding: '16px', background: 'var(--slate-50)', borderRadius: 'var(--radius-lg)', border: '1px solid var(--border)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 700 }}>Material Availability Check</h3>
                <span className={`status-pill ${availability?.available ? 'status-good' : 'status-danger'}`}>
                  {availability?.available ? 'Ready to Start' : 'Shortage Detected'}
                </span>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {availability?.lines?.map((line) => (
                  <div key={line.production_order_item_id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 12px', background: '#fff', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)', fontSize: '13px' }}>
                    <div>
                      <strong>{line.material.code}</strong> · <span style={{ color: 'var(--slate-600)' }}>{line.material.name}</span>
                    </div>
                    <div style={{ display: 'flex', gap: '14px' }}>
                      <span>Req: {formatNumber(line.required_quantity)}</span>
                      <span>Avail: {formatNumber(line.available_quantity)}</span>
                      <strong style={{ color: line.shortage_quantity > 0 ? 'var(--danger)' : 'var(--success)' }}>
                        {line.shortage_quantity > 0 ? `Short: ${formatNumber(line.shortage_quantity)}` : 'Covered'}
                      </strong>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Floor Actions for In-Progress Order */}
            {selectedOrder.status === 'in_progress' && (
              <div style={{ margin: '20px 24px', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '16px' }}>
                {/* 1. Consume Material */}
                <form onSubmit={submitConsumption} style={{ padding: '16px', background: '#fff', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg)' }}>
                  <h4 style={{ margin: '0 0 12px', fontSize: '14px', fontWeight: 700 }}>1. Issue / Consume Material</h4>
                  <label className="form-field" style={{ marginBottom: '10px' }}>
                    <span>Material Line *</span>
                    <select
                      onChange={(event) => updateForm(setConsumptionForm, 'production_order_item_id', event.target.value)}
                      required
                      value={consumptionForm.production_order_item_id}
                    >
                      <option value="">Select line</option>
                      {selectedOrder.items?.map((item) => (
                        <option key={item.id} value={item.id}>
                          {item.material?.code} · Remaining: {formatNumber(item.remaining_quantity)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="form-field" style={{ marginBottom: '12px' }}>
                    <span>Quantity *</span>
                    <input
                      min="0.0001"
                      onChange={(event) => updateForm(setConsumptionForm, 'quantity', event.target.value)}
                      required
                      step="any"
                      type="number"
                      value={consumptionForm.quantity}
                    />
                  </label>
                  <button className="primary-button" disabled={submitting} style={{ width: '100%' }} type="submit">
                    Post Material Issue
                  </button>
                </form>

                {/* 2. Record Progress */}
                <form onSubmit={submitProgress} style={{ padding: '16px', background: '#fff', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg)' }}>
                  <h4 style={{ margin: '0 0 12px', fontSize: '14px', fontWeight: 700 }}>2. Record Line Progress</h4>
                  <label className="form-field" style={{ marginBottom: '10px' }}>
                    <span>Completed Output *</span>
                    <input
                      min="0"
                      onChange={(event) => updateForm(setProgressForm, 'completed_quantity', event.target.value)}
                      required
                      step="any"
                      type="number"
                      value={progressForm.completed_quantity}
                    />
                  </label>
                  <label className="form-field" style={{ marginBottom: '12px' }}>
                    <span>Defects / Rejected</span>
                    <input
                      min="0"
                      onChange={(event) => updateForm(setProgressForm, 'rejected_quantity', event.target.value)}
                      step="any"
                      type="number"
                      value={progressForm.rejected_quantity}
                    />
                  </label>
                  <button className="secondary-button" disabled={submitting} style={{ width: '100%' }} type="submit">
                    Record Progress
                  </button>
                </form>

                {/* 3. Complete & Post FG */}
                <form onSubmit={submitCompletion} style={{ padding: '16px', background: '#fff', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg)' }}>
                  <h4 style={{ margin: '0 0 12px', fontSize: '14px', fontWeight: 700 }}>3. Complete & Post Finished Goods</h4>
                  <label className="form-field" style={{ marginBottom: '10px' }}>
                    <span>Finished Goods Qty *</span>
                    <input
                      min="0.0001"
                      onChange={(event) => updateForm(setCompletionForm, 'finished_quantity', event.target.value)}
                      required
                      step="any"
                      type="number"
                      value={completionForm.finished_quantity}
                    />
                  </label>
                  <label className="form-field" style={{ marginBottom: '12px' }}>
                    <span>Rejected Count</span>
                    <input
                      min="0"
                      onChange={(event) => updateForm(setCompletionForm, 'rejected_quantity', event.target.value)}
                      step="any"
                      type="number"
                      value={completionForm.rejected_quantity}
                    />
                  </label>
                  <button className="primary-button" disabled={submitting} style={{ width: '100%' }} type="submit">
                    Post Finished Goods
                  </button>
                </form>
              </div>
            )}

            <div className="modal-actions" style={{ padding: '16px 24px' }}>
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
              {selectedOrder.status === 'scheduled' && (
                <button className="primary-button" disabled={submitting} onClick={startOrder} type="button">
                  {submitting ? 'Starting…' : 'Start Production Line'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </section>
  )
}

export default ProductionPage
