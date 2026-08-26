import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import deliveryService from '../../services/deliveryService'
import masterDataService from '../../services/masterDataService'
import salesService from '../../services/salesService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const emptyForm = () => ({ sales_order_id: '', warehouse_id: '', delivery_date: today(), expected_delivery_date: '', carrier_name: '', tracking_number: '', delivery_address: '', contact_information: '', remarks: '', items: [] })
const statuses = ['created', 'ready_for_shipment', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'failed', 'returned']

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]

  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function statusLabel(status) {
  return (status || 'created').replaceAll('_', ' ')
}

function statusClass(status) {
  return `status-pill status-${(status || 'created').replaceAll('_', '-')}`
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function partyLabel(order) {
  return order?.buyer?.name || order?.customer?.name || '—'
}

function Pagination({ meta, loading, onPage }) {
  return <div className="pagination-bar"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => onPage((meta.current_page || 1) - 1)} type="button">Previous</button><button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => onPage((meta.current_page || 1) + 1)} type="button">Next</button></div></div>
}

function DeliveryPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(emptyPage)
  const [historyPage, setHistoryPage] = useState(emptyPage)
  const [confirmedOrders, setConfirmedOrders] = useState([])
  const [sourceOrder, setSourceOrder] = useState(null)
  const [selectedDelivery, setSelectedDelivery] = useState(null)
  const [tab, setTab] = useState('deliveries')
  const [query, setQuery] = useState({ search: '', status: '', warehouse_id: '', delivery_date_from: '', delivery_date_to: '', expected_delivery_date_from: '', expected_delivery_date_to: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [historyQuery, setHistoryQuery] = useState({ search: '', action: '', date_from: '', date_to: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [warehouses, setWarehouses] = useState([])
  const [form, setForm] = useState(emptyForm)
  const [trackingForm, setTrackingForm] = useState({ carrier_name: '', tracking_number: '', location: '', remarks: '' })
  const [workflowRemarks, setWorkflowRemarks] = useState('')
  const [modal, setModal] = useState(null)
  const [loading, setLoading] = useState(true)
  const [historyLoading, setHistoryLoading] = useState(false)
  const [catalogLoading, setCatalogLoading] = useState(true)
  const [sourceLoading, setSourceLoading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const loadDeliveries = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setPage(await deliveryService.list(query))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [query])

  const loadHistory = useCallback(async () => {
    setHistoryLoading(true)
    setError('')
    try {
      setHistoryPage(await deliveryService.history(historyQuery))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setHistoryLoading(false)
    }
  }, [historyQuery])

  useEffect(() => {
    let active = true
    Promise.resolve().then(() => { if (active) loadDeliveries() })
    return () => { active = false }
  }, [loadDeliveries])

  useEffect(() => {
    let active = true
    Promise.all([
      masterDataService.options('warehouses'),
      salesService.list({ status: 'confirmed', page: 1, per_page: 100, sort: 'id', direction: 'desc' }),
    ]).then(([warehouseOptions, salesPage]) => {
      if (active) {
        setWarehouses(warehouseOptions)
        setConfirmedOrders(salesPage.data || [])
      }
    }).catch((requestError) => {
      if (active) setError(errorMessage(requestError))
    }).finally(() => {
      if (active) setCatalogLoading(false)
    })
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (tab !== 'history') return undefined
    let active = true
    Promise.resolve().then(() => { if (active) loadHistory() })
    return () => { active = false }
  }, [loadHistory, tab])

  useEffect(() => {
    if (!form.sales_order_id) return undefined
    let active = true
    salesService.get(form.sales_order_id).then((order) => {
      if (!active) return
      setSourceOrder(order)
      setForm((current) => ({
        ...current,
        warehouse_id: order.warehouse_id || '',
        delivery_address: order.delivery_address || '',
        contact_information: order.contact_information || '',
        items: (order.items || []).filter((item) => Number(item.remaining_quantity || 0) > 0).map((item) => ({ sales_order_item_id: item.id, product_id: item.product_id, product_variant_id: item.product_variant_id || '', unit_id: item.unit_id, remaining_quantity: item.remaining_quantity, delivery_quantity: '' })),
      }))
    }).catch((requestError) => {
      if (active) setError(errorMessage(requestError))
    }).finally(() => {
      if (active) setSourceLoading(false)
    })
    return () => { active = false }
  }, [form.sales_order_id])

  const records = page.data || []
  const meta = page.meta || emptyPage.meta
  const historyRecords = historyPage.data || []
  const historyMeta = historyPage.meta || emptyPage.meta

  const updateQuery = (changes) => setQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const updateHistoryQuery = (changes) => setHistoryQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const toggleSort = (column) => updateQuery({ sort: column, direction: query.sort === column && query.direction === 'asc' ? 'desc' : 'asc' })
  const closeModal = () => { setModal(null); setSelectedDelivery(null); setSourceOrder(null); setWorkflowRemarks(''); setNotice(''); setError('') }
  const updateForm = (name, value) => {
    if (name === 'sales_order_id') setSourceLoading(Boolean(value))
    setForm((current) => {
      if (name === 'sales_order_id' && !value) {
        return { ...current, sales_order_id: '', warehouse_id: '', delivery_address: '', contact_information: '', items: [] }
      }
      return { ...current, [name]: value }
    })
  }
  const updateLine = (index, value) => setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, delivery_quantity: value } : item) }))

  const openCreate = () => {
    setForm(emptyForm())
    setSourceOrder(null)
    setSelectedDelivery(null)
    setError('')
    setNotice('')
    setModal('create')
  }

  const openDetails = async (delivery) => {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const detail = await deliveryService.get(delivery.id)
      setSelectedDelivery(detail)
      setTrackingForm({ carrier_name: detail.carrier_name || '', tracking_number: detail.tracking_number || '', location: '', remarks: '' })
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitCreate = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const lineItems = form.items.filter((item) => item.delivery_quantity !== '')
      const items = lineItems.map((item) => ({
        sales_order_item_id: Number(item.sales_order_item_id),
        product_id: Number(item.product_id),
        product_variant_id: item.product_variant_id ? Number(item.product_variant_id) : null,
        unit_id: Number(item.unit_id),
        delivery_quantity: Number(item.delivery_quantity),
      }))
      if (!items.length) throw new Error('Enter a positive quantity for at least one Sales Order Item.')
      if (lineItems.some((item) => Number(item.delivery_quantity) <= 0 || Number(item.delivery_quantity) > Number(item.remaining_quantity || 0))) throw new Error('Each delivery quantity must be positive and no greater than the Sales Order Item remaining quantity.')
      const detail = await deliveryService.create({ sales_order_id: Number(form.sales_order_id), warehouse_id: Number(form.warehouse_id), delivery_date: form.delivery_date, expected_delivery_date: form.expected_delivery_date || null, carrier_name: form.carrier_name || null, tracking_number: form.tracking_number || null, delivery_address: form.delivery_address || null, contact_information: form.contact_information || null, remarks: form.remarks || null, items })
      setSelectedDelivery(detail)
      setNotice('Delivery created without deducting inventory. Dispatch is the stock-out event.')
      await loadDeliveries()
      setModal('details')
    } catch (requestError) {
      setError(requestError.message && !requestError.response ? requestError.message : errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const applyAction = async (operation, status, successMessage) => {
    if (!selectedDelivery) return
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const result = operation === 'dispatch'
        ? await deliveryService.dispatch(selectedDelivery.id, workflowRemarks || null)
        : operation === 'complete'
          ? await deliveryService.complete(selectedDelivery.id, workflowRemarks || null)
          : await deliveryService.transition(selectedDelivery.id, status, workflowRemarks || null)
      setSelectedDelivery(result)
      setWorkflowRemarks('')
      setNotice(successMessage)
      await loadDeliveries()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const updateTracking = async (event) => {
    event.preventDefault()
    if (!selectedDelivery) return
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const result = await deliveryService.tracking(selectedDelivery.id, trackingForm)
      setSelectedDelivery(result)
      setTrackingForm((current) => ({ ...current, location: '', remarks: '' }))
      setNotice('Tracking metadata and immutable tracking history updated.')
      await loadDeliveries()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const nextAction = (delivery) => {
    if (!delivery) return null
    if (delivery.status === 'created') return { operation: 'transition', status: 'ready_for_shipment', label: 'Prepare for shipment', message: 'Delivery moved to ready for shipment.' }
    if (delivery.status === 'ready_for_shipment') return { operation: 'dispatch', label: 'Dispatch & stock out', message: 'Delivery dispatched through InventoryService.' }
    if (delivery.status === 'shipped') return { operation: 'transition', status: 'in_transit', label: 'Mark in transit', message: 'Delivery marked in transit.' }
    if (delivery.status === 'in_transit') return { operation: 'transition', status: 'out_for_delivery', label: 'Mark out for delivery', message: 'Delivery marked out for delivery.' }
    if (delivery.status === 'out_for_delivery') return { operation: 'transition', status: 'delivered', label: 'Mark delivered', message: 'Delivery marked delivered.' }
    if (delivery.status === 'delivered') return { operation: 'complete', label: 'Complete delivery', message: 'Delivery completed.' }
    return null
  }

  const action = nextAction(selectedDelivery)
  const sortLabel = (column, label) => `${label}${query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}`

  return <div className="master-data-page delivery-page">
    <div className="tab-strip delivery-tabs" role="tablist" aria-label="Delivery views">
      <button className={tab === 'deliveries' ? 'active' : ''} onClick={() => setTab('deliveries')} role="tab" type="button" aria-selected={tab === 'deliveries'}>Delivery Register</button>
      <button className={tab === 'history' ? 'active' : ''} onClick={() => setTab('history')} role="tab" type="button" aria-selected={tab === 'history'}>Delivery History</button>
    </div>

    {tab === 'deliveries' && <>
      <div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Delivery, tracking, Sales Order, or party" value={query.search} /></label><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}><option value="">All statuses</option>{statuses.map((status) => <option key={status} value={status}>{statusLabel(status)}</option>)}</select></label><label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}><option value="">All warehouses</option>{warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}</select></label><span className="record-count">{meta.total || 0} deliveries</span></div>
      <div className="buyer-order-date-filters"><label className="filter-field"><span>Delivery from</span><input onChange={(event) => updateQuery({ delivery_date_from: event.target.value })} type="date" value={query.delivery_date_from} /></label><label className="filter-field"><span>Delivery to</span><input onChange={(event) => updateQuery({ delivery_date_to: event.target.value })} type="date" value={query.delivery_date_to} /></label><label className="filter-field"><span>Expected from</span><input onChange={(event) => updateQuery({ expected_delivery_date_from: event.target.value })} type="date" value={query.expected_delivery_date_from} /></label><label className="filter-field"><span>Expected to</span><input onChange={(event) => updateQuery({ expected_delivery_date_to: event.target.value })} type="date" value={query.expected_delivery_date_to} /></label></div>
      {error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}
      <section className="data-card" aria-busy={loading}><div className="data-card-header"><div><p className="eyebrow">Shipment control</p><h2>Delivery register</h2></div><span className="data-card-hint">Inventory changes only when a Delivery is dispatched</span></div>{loading ? <div className="empty-state">Loading Deliveries…</div> : records.length === 0 ? <div className="empty-state">No Deliveries match the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr>{[['delivery_number', 'Delivery'], ['delivery_date', 'Delivery date'], ['expected_delivery_date', 'Expected'], ['ordered_quantity', 'Ordered'], ['dispatched_quantity', 'Dispatched'], ['delivered_quantity', 'Delivered'], ['status', 'Status']].map(([column, label]) => <th key={column}><button onClick={() => toggleSort(column)} type="button">{sortLabel(column, label)}</button></th>)}<th>Sales Order</th><th>Warehouse</th><th><span className="sr-only">Actions</span></th></tr></thead><tbody>{records.map((delivery) => <tr key={delivery.id} onClick={() => openDetails(delivery)}><td><strong>{delivery.delivery_number}</strong><div className="table-subtext">{delivery.tracking_number || 'No tracking number'}</div></td><td>{delivery.delivery_date || '—'}</td><td>{delivery.expected_delivery_date || '—'}</td><td>{formatNumber(delivery.ordered_quantity)}</td><td>{formatNumber(delivery.dispatched_quantity)}</td><td>{formatNumber(delivery.delivered_quantity)}</td><td><span className={statusClass(delivery.status)}>{statusLabel(delivery.status)}</span></td><td>{delivery.sales_order?.sales_order_number || '—'}<div className="table-subtext">{partyLabel(delivery.sales_order)}</div></td><td>{delivery.warehouse?.name || '—'}</td><td className="table-actions" onClick={(event) => event.stopPropagation()}><button className="text-button" onClick={() => openDetails(delivery)} type="button">Open</button></td></tr>)}</tbody></table></div>}<Pagination loading={loading} meta={meta} onPage={(nextPage) => updateQuery({ page: nextPage })} /></section>
    </>}

    {tab === 'history' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Audit-backed register</p><h2>Delivery History</h2></div><span className="data-card-hint">Status, tracking, dispatch, and metadata events remain traceable</span></div><div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateHistoryQuery({ search: event.target.value })} placeholder="Search action or actor" value={historyQuery.search} /></label><label className="filter-field"><span>Action</span><select onChange={(event) => updateHistoryQuery({ action: event.target.value })} value={historyQuery.action}><option value="">All actions</option><option value="created">Created</option><option value="updated">Updated</option><option value="dispatched">Dispatched</option><option value="status_changed">Status changed</option><option value="tracking_updated">Tracking updated</option></select></label><label className="filter-field"><span>From</span><input onChange={(event) => updateHistoryQuery({ date_from: event.target.value })} type="date" value={historyQuery.date_from} /></label><label className="filter-field"><span>To</span><input onChange={(event) => updateHistoryQuery({ date_to: event.target.value })} type="date" value={historyQuery.date_to} /></label></div>{historyLoading ? <div className="empty-state">Loading Delivery History…</div> : historyRecords.length === 0 ? <div className="empty-state">No Delivery History matches the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Time</th><th>Action</th><th>Record</th><th>Actor</th><th>Changes</th></tr></thead><tbody>{historyRecords.map((entry) => <tr key={entry.id}><td>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</td><td>{statusLabel(entry.action)}</td><td>{entry.record_type?.split('\\').pop() || 'Delivery'} #{entry.record_id}</td><td>{entry.user?.name || entry.user?.email || '—'}</td><td>{entry.new_values?.status ? `${entry.old_values?.status || '—'} → ${entry.new_values.status}` : entry.new_values?.tracking_number ? `Tracking ${entry.new_values.tracking_number}` : 'Recorded change'}</td></tr>)}</tbody></table></div>}<Pagination loading={historyLoading} meta={historyMeta} onPage={(nextPage) => updateHistoryQuery({ page: nextPage })} /></section>}

    <button className="secondary-button back-link" onClick={() => navigate('/')} type="button">← Back to workspace</button>

    {modal === 'create' && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card" role="dialog" aria-modal="true" aria-labelledby="delivery-form-title"><div className="modal-header"><div><p className="eyebrow">Confirmed Sales Order only</p><h2 id="delivery-form-title">Create Delivery</h2></div><button aria-label="Close Delivery form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitCreate}>{catalogLoading && <div className="inline-preview" role="status">Loading confirmed Sales Orders and warehouse options…</div>}<div className="form-grid"><label className="form-field full-width"><span>Confirmed Sales Order *</span><select onChange={(event) => updateForm('sales_order_id', event.target.value)} required value={form.sales_order_id}><option value="">Select confirmed Sales Order</option>{confirmedOrders.map((order) => <option key={order.id} value={order.id}>{order.sales_order_number} · {partyLabel(order)} · remaining {formatNumber(order.remaining_quantity)}</option>)}</select></label><label className="form-field"><span>Delivery date *</span><input onChange={(event) => updateForm('delivery_date', event.target.value)} required type="date" value={form.delivery_date} /></label><label className="form-field"><span>Expected delivery</span><input onChange={(event) => updateForm('expected_delivery_date', event.target.value)} type="date" value={form.expected_delivery_date} /></label><label className="form-field"><span>Warehouse *</span><select disabled value={form.warehouse_id} required><option value="">Select from Sales Order</option>{warehouses.filter((warehouse) => String(warehouse.id) === String(form.warehouse_id)).map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}</select></label><label className="form-field"><span>Carrier</span><input onChange={(event) => updateForm('carrier_name', event.target.value)} value={form.carrier_name} /></label><label className="form-field"><span>Tracking number</span><input onChange={(event) => updateForm('tracking_number', event.target.value)} value={form.tracking_number} /></label><label className="form-field full-width"><span>Delivery address</span><textarea onChange={(event) => updateForm('delivery_address', event.target.value)} rows="2" value={form.delivery_address} /></label><label className="form-field full-width"><span>Contact information</span><input onChange={(event) => updateForm('contact_information', event.target.value)} value={form.contact_information} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => updateForm('remarks', event.target.value)} rows="2" value={form.remarks} /></label></div><div className="order-lines-heading"><div><p className="eyebrow">Source items</p><h3>Partial delivery quantities</h3></div>{sourceLoading && <span className="data-card-hint">Loading Sales Order items…</span>}</div>{!sourceLoading && sourceOrder && form.items.length === 0 && <div className="empty-state">This confirmed Sales Order has no remaining quantity.</div>}{form.items.map((item, index) => <div className="order-edit-line" key={item.sales_order_item_id}><div className="form-field"><span>Product</span><strong>{item.product_variant_id ? (sourceOrder.items.find((sourceItem) => sourceItem.id === item.sales_order_item_id)?.product_variant?.sku || 'Variant') : (sourceOrder.items.find((sourceItem) => sourceItem.id === item.sales_order_item_id)?.product?.name || 'Product')}</strong></div><div className="form-field"><span>Remaining</span><strong>{formatNumber(item.remaining_quantity)}</strong></div><label className="form-field"><span>Deliver now *</span><input min="0.0001" max={item.remaining_quantity} onChange={(event) => updateLine(index, event.target.value)} required={false} step="0.0001" type="number" value={item.delivery_quantity} /></label></div>)}<div className="inline-preview" role="status">Stock remains unchanged until Dispatch. Quantities are capped by the confirmed Sales Order remaining balance.</div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || sourceLoading || catalogLoading || !form.items.length} type="submit">{busy ? 'Creating…' : 'Create Delivery'}</button></div></form></div></div>}

    {modal === 'details' && selectedDelivery && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card order-detail-modal" role="dialog" aria-modal="true" aria-labelledby="delivery-detail-title"><div className="modal-header"><div><p className="eyebrow">Shipment detail</p><h2 id="delivery-detail-title">{selectedDelivery.delivery_number}</h2><p>{selectedDelivery.sales_order?.sales_order_number || 'Sales Order'} · {partyLabel(selectedDelivery.sales_order)}</p></div><button aria-label="Close Delivery details" className="icon-button" onClick={closeModal} type="button">×</button></div>{error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}<div className="order-detail-summary"><div><span>Status</span><strong><span className={statusClass(selectedDelivery.status)}>{statusLabel(selectedDelivery.status)}</span></strong></div><div><span>Warehouse</span><strong>{selectedDelivery.warehouse?.name || '—'}</strong></div><div><span>Delivery date</span><strong>{selectedDelivery.delivery_date || '—'}</strong></div><div><span>Expected</span><strong>{selectedDelivery.expected_delivery_date || '—'}</strong></div><div><span>Ordered</span><strong>{formatNumber(selectedDelivery.ordered_quantity)}</strong></div><div><span>Dispatched</span><strong>{formatNumber(selectedDelivery.dispatched_quantity)}</strong></div><div><span>Delivered</span><strong>{formatNumber(selectedDelivery.delivered_quantity)}</strong></div><div><span>Remaining</span><strong>{formatNumber(selectedDelivery.remaining_quantity)}</strong></div><div><span>Sales delivered</span><strong>{formatNumber(selectedDelivery.sales_order?.delivered_quantity)}</strong></div><div><span>Sales remaining</span><strong>{formatNumber(selectedDelivery.sales_order?.remaining_quantity)}</strong></div></div><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Shipment metadata</p><h3>{selectedDelivery.carrier_name || 'Carrier not assigned'}</h3></div><span>{selectedDelivery.tracking_number || 'No tracking number'}</span></div><form className="form-grid" onSubmit={updateTracking}><label className="form-field"><span>Carrier</span><input onChange={(event) => setTrackingForm((current) => ({ ...current, carrier_name: event.target.value }))} value={trackingForm.carrier_name} /></label><label className="form-field"><span>Tracking number</span><input onChange={(event) => setTrackingForm((current) => ({ ...current, tracking_number: event.target.value }))} value={trackingForm.tracking_number} /></label><label className="form-field"><span>Event location</span><input onChange={(event) => setTrackingForm((current) => ({ ...current, location: event.target.value }))} value={trackingForm.location} /></label><label className="form-field"><span>Event remarks</span><input onChange={(event) => setTrackingForm((current) => ({ ...current, remarks: event.target.value }))} value={trackingForm.remarks} /></label><div className="modal-actions full-width"><button className="secondary-button" disabled={busy} type="submit">Save tracking event</button></div></form></section><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Delivery items</p><h3>Dispatch traceability</h3></div></div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Source item</th><th>Delivery quantity</th><th>Dispatched</th><th>Delivered</th><th>Inventory reference</th></tr></thead><tbody>{(selectedDelivery.items || []).map((item) => <tr key={item.id}><td>{item.product_variant?.sku || item.product?.name || '—'}<div className="table-subtext">{item.unit?.code || '—'}</div></td><td>{formatNumber(item.delivery_quantity)}</td><td>{formatNumber(item.dispatched_quantity)}</td><td>{formatNumber(item.delivered_quantity)}</td><td>{item.inventory_transaction?.idempotency_key || 'Not dispatched'}</td></tr>)}</tbody></table></div></section><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Workflow control</p><h3>Status and dispatch</h3></div></div><label className="form-field"><span>Workflow remarks</span><textarea onChange={(event) => setWorkflowRemarks(event.target.value)} placeholder="Optional remarks for this status or dispatch event" rows="2" value={workflowRemarks} /></label><div className="workflow-actions">{action && <button className="primary-button" disabled={busy} onClick={() => applyAction(action.operation, action.status, action.message)} type="button">{action.label}</button>}{['shipped', 'in_transit', 'out_for_delivery'].includes(selectedDelivery.status) && <button className="secondary-button danger-text" disabled={busy} onClick={() => applyAction('transition', 'failed', 'Delivery marked failed.')} type="button">Mark failed</button>}{selectedDelivery.status === 'delivered' && <button className="secondary-button danger-text" disabled={busy} onClick={() => applyAction('transition', 'returned', 'Delivery marked returned.')} type="button">Mark returned</button>}{!action && !['failed', 'returned', 'cancelled', 'completed'].includes(selectedDelivery.status) && <span className="completed-note">No valid next action is available.</span>}</div></section><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Tracking history</p><h3>Immutable shipment events</h3></div></div>{(selectedDelivery.tracking_history || []).length === 0 ? <div className="empty-state">No tracking history is available.</div> : <div className="history-list">{selectedDelivery.tracking_history.map((history) => <div className="history-row" key={history.id}><span>{history.created_at ? new Date(history.created_at).toLocaleString() : '—'}</span><strong>{statusLabel(history.new_status)}</strong><span>{history.location || '—'}</span><span>{history.tracking_number || 'No tracking number'}</span><span>{history.remarks || '—'}</span></div>)}</div>}</section><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div></div></div>}
  </div>
}

export default DeliveryPage
