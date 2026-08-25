import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import masterDataService from '../../services/masterDataService'
import salesService from '../../services/salesService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const emptyItem = () => ({ product_id: '', product_variant_id: '', unit_id: '', ordered_quantity: '', unit_price: '', discount_amount: '', tax_amount: '', remarks: '' })
const emptyForm = () => ({ buyer_id: '', customer_id: '', order_date: today(), required_delivery_date: '', warehouse_id: '', delivery_address: '', contact_information: '', order_discount_amount: '', order_tax_amount: '', remarks: '', items: [emptyItem()] })
const statuses = ['draft', 'submitted', 'confirmed', 'ready_for_delivery', 'delivered', 'completed', 'cancelled']

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]

  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function statusClass(status) {
  return `status-pill order-status status-${(status || 'draft').replaceAll('_', '-')}`
}

function statusLabel(status) {
  return (status || 'draft').replaceAll('_', ' ')
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

function partyLabel(order) {
  return order.buyer?.name || order.customer?.name || '—'
}

function SalesOrderPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(emptyPage)
  const [historyPage, setHistoryPage] = useState(emptyPage)
  const [tab, setTab] = useState('orders')
  const [query, setQuery] = useState({ search: '', status: '', buyer_id: '', customer_id: '', warehouse_id: '', order_date_from: '', order_date_to: '', required_delivery_date_from: '', required_delivery_date_to: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [historyQuery, setHistoryQuery] = useState({ search: '', action: '', date_from: '', date_to: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [catalog, setCatalog] = useState({ buyers: [], customers: [], products: [], variants: [], units: [], warehouses: [] })
  const [loading, setLoading] = useState(true)
  const [historyLoading, setHistoryLoading] = useState(false)
  const [catalogLoading, setCatalogLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [availability, setAvailability] = useState(null)
  const [modal, setModal] = useState(null)
  const [orderForm, setOrderForm] = useState(emptyForm)
  const [preview, setPreview] = useState(null)
  const [workflowRemarks, setWorkflowRemarks] = useState('')

  const loadOrders = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      setPage(await salesService.list(query))
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
      setHistoryPage(await salesService.history(historyQuery))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setHistoryLoading(false)
    }
  }, [historyQuery])

  useEffect(() => {
    let active = true
    Promise.resolve().then(() => { if (active) loadOrders() })
    return () => { active = false }
  }, [loadOrders])

  useEffect(() => {
    let active = true
    Promise.all([
      masterDataService.options('buyers'),
      masterDataService.options('customers'),
      masterDataService.options('products'),
      masterDataService.options('product-variants'),
      masterDataService.options('units'),
      masterDataService.options('warehouses'),
    ]).then(([buyers, customers, products, variants, units, warehouses]) => {
      if (active) setCatalog({ buyers, customers, products, variants, units, warehouses })
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
    Promise.resolve().then(() => {
      if (active) loadHistory()
    })
    return () => { active = false }
  }, [loadHistory, tab])

  const records = page.data || []
  const meta = page.meta || emptyPage.meta
  const historyRecords = historyPage.data || []
  const historyMeta = historyPage.meta || emptyPage.meta

  const updateQuery = (changes) => setQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const updateHistoryQuery = (changes) => setHistoryQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const toggleSort = (column) => setQuery((current) => ({ ...current, sort: column, direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc', page: 1 }))
  const setFormValue = (name, value) => setOrderForm((current) => ({ ...current, [name]: value }))
  const setLineValue = (index, name, value) => setOrderForm((current) => ({
    ...current,
    items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, [name]: value, ...(name === 'product_id' ? { product_variant_id: '', unit_id: '' } : {}) } : item),
  }))

  const openCreate = () => {
    setSelectedOrder(null)
    setAvailability(null)
    setOrderForm(emptyForm())
    setPreview(null)
    setWorkflowRemarks('')
    setError('')
    setNotice('')
    setModal('form')
  }

  const openDetails = async (order) => {
    setBusy(true)
    setError('')
    setNotice('')

    try {
      const detail = await salesService.get(order.id)
      setSelectedOrder(detail)
      setAvailability(await salesService.availability(order.id))
      setWorkflowRemarks('')
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const openEdit = async (order) => {
    const detail = order.items ? order : await salesService.get(order.id)
    if (detail.status !== 'draft') {
      setError('Only draft Sales Orders can be edited.')
      return
    }

    setSelectedOrder(detail)
    setOrderForm({
      buyer_id: detail.buyer_id || '',
      customer_id: detail.customer_id || '',
      order_date: detail.order_date || today(),
      required_delivery_date: detail.required_delivery_date || '',
      warehouse_id: detail.warehouse_id || '',
      delivery_address: detail.delivery_address || '',
      contact_information: detail.contact_information || '',
      order_discount_amount: detail.order_discount_amount ?? '',
      order_tax_amount: detail.order_tax_amount ?? '',
      remarks: detail.remarks || '',
      items: (detail.items || []).map((item) => ({
        product_id: item.product_id || '',
        product_variant_id: item.product_variant_id || '',
        unit_id: item.unit_id || '',
        ordered_quantity: item.ordered_quantity ?? '',
        unit_price: item.unit_price ?? '',
        discount_amount: item.discount_amount ?? '',
        tax_amount: item.tax_amount ?? '',
        remarks: item.remarks || '',
      })),
    })
    setPreview(null)
    setError('')
    setNotice('')
    setModal('form')
  }

  const closeModal = () => {
    setModal(null)
    setSelectedOrder(null)
    setAvailability(null)
    setPreview(null)
    setWorkflowRemarks('')
  }

  const addLine = () => setOrderForm((current) => ({ ...current, items: [...current.items, emptyItem()] }))
  const removeLine = (index) => setOrderForm((current) => ({ ...current, items: current.items.length <= 1 ? current.items : current.items.filter((_, itemIndex) => itemIndex !== index) }))
  const normalizedItems = () => orderForm.items.map((item) => ({
    product_id: Number(item.product_id),
    product_variant_id: item.product_variant_id ? Number(item.product_variant_id) : null,
    unit_id: Number(item.unit_id),
    ordered_quantity: Number(item.ordered_quantity),
    unit_price: Number(item.unit_price),
    discount_amount: Number(item.discount_amount || 0),
    tax_amount: Number(item.tax_amount || 0),
    remarks: item.remarks || null,
  }))

  const previewTotals = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      setPreview(await salesService.preview(normalizedItems(), Number(orderForm.order_discount_amount || 0), Number(orderForm.order_tax_amount || 0)))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitForm = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const payload = {
        buyer_id: orderForm.buyer_id ? Number(orderForm.buyer_id) : null,
        customer_id: orderForm.customer_id ? Number(orderForm.customer_id) : null,
        order_date: orderForm.order_date,
        required_delivery_date: orderForm.required_delivery_date,
        warehouse_id: Number(orderForm.warehouse_id),
        delivery_address: orderForm.delivery_address || null,
        contact_information: orderForm.contact_information || null,
        order_discount_amount: Number(orderForm.order_discount_amount || 0),
        order_tax_amount: Number(orderForm.order_tax_amount || 0),
        remarks: orderForm.remarks || null,
        items: normalizedItems(),
      }
      const detail = selectedOrder ? await salesService.update(selectedOrder.id, payload) : await salesService.create(payload)
      setSelectedOrder(detail)
      setAvailability(await salesService.availability(detail.id))
      await loadOrders()
      setNotice(selectedOrder ? 'Sales Order draft updated successfully.' : 'Sales Order draft created successfully.')
      setModal('details')
      setPreview(null)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const runAction = async (action, successMessage, status = null) => {
    if (!selectedOrder) return
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const result = action === 'transition'
        ? await salesService.transition(selectedOrder.id, status, workflowRemarks || null)
        : await salesService[action](selectedOrder.id, workflowRemarks || null)
      setSelectedOrder(result)
      setAvailability(await salesService.availability(result.id))
      await loadOrders()
      setWorkflowRemarks('')
      setNotice(successMessage)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const workflowAction = () => {
    if (!selectedOrder) return null
    if (selectedOrder.status === 'draft') return { action: 'submit', label: 'Submit order', message: 'Sales Order submitted.' }
    if (selectedOrder.status === 'submitted') return { action: 'confirm', label: 'Confirm order', message: 'Sales Order confirmed after availability check.' }
    const nextStatus = { confirmed: 'ready_for_delivery', ready_for_delivery: 'delivered', delivered: 'completed' }[selectedOrder.status]
    return nextStatus ? { action: 'transition', status: nextStatus, label: `Move to ${statusLabel(nextStatus)}`, message: `Sales Order moved to ${statusLabel(nextStatus)}.` } : null
  }

  const action = workflowAction()
  const metaSortLabel = (column, label) => `${label}${query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}`
  const optionLabel = (option) => option.code || option.sku || option.name || option.variant_name || `#${option.id}`

  return (
    <div className="master-data-page buyer-orders-page sales-orders-page">
      <div className="page-intro master-data-intro">
        <div><p className="eyebrow">Phase 9 · Sales Management</p><h1>Sales Orders</h1><p>Capture commercial orders, validate finished-goods availability, and prepare confirmed quantities for future delivery.</p></div>
        <button className="primary-button" onClick={openCreate} type="button">Create Sales Order</button>
      </div>

      <div className="production-tabs" role="tablist" aria-label="Sales views">
        {[['orders', 'Sales Orders'], ['availability', 'Finished Goods Availability'], ['history', 'Sales History']].map(([value, label]) => <button className={`secondary-button ${tab === value ? 'active-tab' : ''}`} key={value} onClick={() => setTab(value)} role="tab" type="button" aria-selected={tab === value}>{label}</button>)}
      </div>

      {tab === 'orders' && <>
        <div className="master-data-toolbar buyer-order-toolbar">
          <label className="search-field"><span>Search</span><input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search Sales Order or party" value={query.search} /></label>
          <label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}><option value="">All statuses</option>{statuses.map((status) => <option key={status} value={status}>{statusLabel(status)}</option>)}</select></label>
          <label className="filter-field"><span>Buyer</span><select onChange={(event) => updateQuery({ buyer_id: event.target.value })} value={query.buyer_id}><option value="">All buyers</option>{catalog.buyers.map((buyer) => <option key={buyer.id} value={buyer.id}>{buyer.code} · {buyer.name}</option>)}</select></label>
          <label className="filter-field"><span>Customer</span><select onChange={(event) => updateQuery({ customer_id: event.target.value })} value={query.customer_id}><option value="">All customers</option>{catalog.customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.code} · {customer.name}</option>)}</select></label>
          <label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}><option value="">All warehouses</option>{catalog.warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}</select></label>
          <span className="record-count">{meta.total || 0} orders</span>
        </div>
        <div className="buyer-order-date-filters"><label className="filter-field"><span>Order from</span><input onChange={(event) => updateQuery({ order_date_from: event.target.value })} type="date" value={query.order_date_from} /></label><label className="filter-field"><span>Order to</span><input onChange={(event) => updateQuery({ order_date_to: event.target.value })} type="date" value={query.order_date_to} /></label><label className="filter-field"><span>Required delivery from</span><input onChange={(event) => updateQuery({ required_delivery_date_from: event.target.value })} type="date" value={query.required_delivery_date_from} /></label><label className="filter-field"><span>Required delivery to</span><input onChange={(event) => updateQuery({ required_delivery_date_to: event.target.value })} type="date" value={query.required_delivery_date_to} /></label></div>
        {error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}
        <section className="data-card" aria-busy={loading}><div className="data-card-header"><div><p className="eyebrow">Commercial register</p><h2>Sales Order register</h2></div><span className="data-card-hint">Open an order to check availability and manage its workflow</span></div>
          {loading ? <div className="empty-state">Loading Sales Orders…</div> : records.length === 0 ? <div className="empty-state">No Sales Orders match the current filters.</div> : <div className="table-wrap"><table className="master-data-table buyer-order-table"><thead><tr>{['sales_order_number', 'order_date', 'required_delivery_date', 'ordered_quantity', 'total_amount', 'status'].map((column) => <th key={column}><button onClick={() => toggleSort(column)} type="button">{metaSortLabel(column, column === 'sales_order_number' ? 'Order number' : column.replaceAll('_', ' '))}</button></th>)}<th>Party</th><th>Warehouse</th><th><span className="sr-only">Actions</span></th></tr></thead><tbody>{records.map((order) => <tr key={order.id} onClick={() => openDetails(order)}><td><strong>{order.sales_order_number}</strong></td><td>{order.order_date || '—'}</td><td>{order.required_delivery_date || '—'}</td><td>{formatNumber(order.ordered_quantity)}</td><td>{formatMoney(order.total_amount)}</td><td><span className={statusClass(order.status)}>{statusLabel(order.status)}</span></td><td>{partyLabel(order)}</td><td>{order.warehouse?.name || '—'}</td><td className="table-actions" onClick={(event) => event.stopPropagation()}><button className="text-button" onClick={() => openDetails(order)} type="button">Open</button>{order.status === 'draft' && <button className="text-button" onClick={() => openEdit(order)} type="button">Edit</button>}</td></tr>)}</tbody></table></div>}
          <div className="pagination-bar"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) - 1 })} type="button">Previous</button><button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) + 1 })} type="button">Next</button></div></div>
        </section>
      </>}

      {tab === 'availability' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Finished goods control</p><h2>Availability by Sales Order</h2></div><span className="data-card-hint">Confirmation checks canonical InventoryService available quantity</span></div>{records.length === 0 ? <div className="empty-state">Create or load a Sales Order to inspect availability.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Order</th><th>Party</th><th>Status</th><th>Ordered</th><th>Remaining</th><th>Warehouse</th><th /></tr></thead><tbody>{records.map((order) => <tr key={order.id}><td><strong>{order.sales_order_number}</strong></td><td>{partyLabel(order)}</td><td><span className={statusClass(order.status)}>{statusLabel(order.status)}</span></td><td>{formatNumber(order.ordered_quantity)}</td><td>{formatNumber(order.remaining_quantity)}</td><td>{order.warehouse?.name || '—'}</td><td><button className="text-button" onClick={() => openDetails(order)} type="button">Check availability</button></td></tr>)}</tbody></table></div>}</section>}

      {tab === 'history' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Audit-backed register</p><h2>Sales History</h2></div><span className="data-card-hint">Every important Sales status change remains traceable</span></div><div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateHistoryQuery({ search: event.target.value })} placeholder="Search action or actor" value={historyQuery.search} /></label><label className="filter-field"><span>Action</span><select onChange={(event) => updateHistoryQuery({ action: event.target.value })} value={historyQuery.action}><option value="">All actions</option><option value="created">Created</option><option value="updated">Updated</option><option value="status_changed">Status changed</option></select></label><label className="filter-field"><span>From</span><input onChange={(event) => updateHistoryQuery({ date_from: event.target.value })} type="date" value={historyQuery.date_from} /></label><label className="filter-field"><span>To</span><input onChange={(event) => updateHistoryQuery({ date_to: event.target.value })} type="date" value={historyQuery.date_to} /></label></div>{historyLoading ? <div className="empty-state">Loading Sales History…</div> : historyRecords.length === 0 ? <div className="empty-state">No Sales History matches the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Time</th><th>Action</th><th>Record</th><th>Actor</th><th>Changes</th></tr></thead><tbody>{historyRecords.map((entry) => <tr key={entry.id}><td>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</td><td>{statusLabel(entry.action)}</td><td>{entry.record_type?.split('\\').pop() || 'SalesOrder'} #{entry.record_id}</td><td>{entry.user?.name || entry.user?.email || '—'}</td><td>{entry.new_values?.status ? `${entry.old_values?.status || '—'} → ${entry.new_values.status}` : 'Recorded change'}</td></tr>)}</tbody></table></div>}<div className="pagination-bar"><span>Page {historyMeta.current_page || 1} of {historyMeta.last_page || 1}</span><div><button className="secondary-button" disabled={(historyMeta.current_page || 1) <= 1 || historyLoading} onClick={() => updateHistoryQuery({ page: (historyMeta.current_page || 1) - 1 })} type="button">Previous</button><button className="secondary-button" disabled={(historyMeta.current_page || 1) >= (historyMeta.last_page || 1) || historyLoading} onClick={() => updateHistoryQuery({ page: (historyMeta.current_page || 1) + 1 })} type="button">Next</button></div></div></section>}

      <button className="secondary-button back-link" onClick={() => navigate('/')} type="button">← Back to workspace</button>

      {modal === 'form' && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card" role="dialog" aria-modal="true" aria-labelledby="sales-order-form-title"><div className="modal-header"><div><p className="eyebrow">Sales Order draft</p><h2 id="sales-order-form-title">{selectedOrder ? 'Edit draft Sales Order' : 'Create Sales Order'}</h2></div><button aria-label="Close Sales Order form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitForm}>
        {catalogLoading && <div className="inline-preview" role="status">Loading Sales master-data options…</div>}
        <div className="form-grid"><label className="form-field"><span>Buyer</span><select onChange={(event) => setFormValue('buyer_id', event.target.value)} value={orderForm.buyer_id}><option value="">Select buyer</option>{catalog.buyers.map((buyer) => <option key={buyer.id} value={buyer.id}>{buyer.code} · {buyer.name}</option>)}</select></label><label className="form-field"><span>Customer</span><select onChange={(event) => setFormValue('customer_id', event.target.value)} value={orderForm.customer_id}><option value="">Select customer</option>{catalog.customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.code} · {customer.name}</option>)}</select></label><label className="form-field"><span>Order date *</span><input onChange={(event) => setFormValue('order_date', event.target.value)} required type="date" value={orderForm.order_date} /></label><label className="form-field"><span>Required delivery date *</span><input onChange={(event) => setFormValue('required_delivery_date', event.target.value)} required type="date" value={orderForm.required_delivery_date} /></label><label className="form-field"><span>Warehouse *</span><select onChange={(event) => setFormValue('warehouse_id', event.target.value)} required value={orderForm.warehouse_id}><option value="">Select warehouse</option>{catalog.warehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}</select></label><label className="form-field"><span>Order discount</span><input min="0" onChange={(event) => setFormValue('order_discount_amount', event.target.value)} step="0.0001" type="number" value={orderForm.order_discount_amount} /></label><label className="form-field"><span>Order tax</span><input min="0" onChange={(event) => setFormValue('order_tax_amount', event.target.value)} step="0.0001" type="number" value={orderForm.order_tax_amount} /></label><label className="form-field full-width"><span>Delivery address</span><textarea onChange={(event) => setFormValue('delivery_address', event.target.value)} rows="2" value={orderForm.delivery_address} /></label><label className="form-field full-width"><span>Contact information</span><input onChange={(event) => setFormValue('contact_information', event.target.value)} value={orderForm.contact_information} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setFormValue('remarks', event.target.value)} rows="2" value={orderForm.remarks} /></label></div>
        <div className="order-lines-heading"><div><p className="eyebrow">Sales lines</p><h3>Products and variants</h3></div><button className="secondary-button" onClick={addLine} type="button">Add line</button></div><div className="order-edit-lines">{orderForm.items.map((item, index) => <div className="order-edit-line" key={`line-${index}`}><label className="form-field"><span>Product *</span><select onChange={(event) => setLineValue(index, 'product_id', event.target.value)} required value={item.product_id}><option value="">Select product</option>{catalog.products.map((product) => <option key={product.id} value={product.id}>{optionLabel(product)}{product.name ? ` · ${product.name}` : ''}</option>)}</select></label><label className="form-field"><span>Variant</span><select onChange={(event) => setLineValue(index, 'product_variant_id', event.target.value)} value={item.product_variant_id}><option value="">Product-level stock</option>{catalog.variants.filter((variant) => String(variant.product_id) === String(item.product_id)).map((variant) => <option key={variant.id} value={variant.id}>{optionLabel(variant)}{variant.variant_name ? ` · ${variant.variant_name}` : ''}</option>)}</select></label><label className="form-field"><span>Unit *</span><select onChange={(event) => setLineValue(index, 'unit_id', event.target.value)} required value={item.unit_id}><option value="">Select unit</option>{catalog.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} · {unit.name}</option>)}</select></label><label className="form-field"><span>Quantity *</span><input min="0.0001" onChange={(event) => setLineValue(index, 'ordered_quantity', event.target.value)} required step="0.0001" type="number" value={item.ordered_quantity} /></label><label className="form-field"><span>Unit price *</span><input min="0" onChange={(event) => setLineValue(index, 'unit_price', event.target.value)} required step="0.0001" type="number" value={item.unit_price} /></label><label className="form-field"><span>Line discount</span><input min="0" onChange={(event) => setLineValue(index, 'discount_amount', event.target.value)} step="0.0001" type="number" value={item.discount_amount} /></label><label className="form-field"><span>Line tax</span><input min="0" onChange={(event) => setLineValue(index, 'tax_amount', event.target.value)} step="0.0001" type="number" value={item.tax_amount} /></label><button aria-label={`Remove line ${index + 1}`} className="icon-button danger-text" disabled={orderForm.items.length <= 1} onClick={() => removeLine(index)} type="button">×</button></div>)}</div>
        <div className="order-preview-bar"><div><span>Subtotal</span><strong>{preview ? formatMoney(preview.subtotal) : '—'}</strong></div><div><span>Total quantity</span><strong>{preview ? formatNumber(preview.total_quantity) : '—'}</strong></div><div><span>Total amount</span><strong>{preview ? formatMoney(preview.total_amount) : '—'}</strong></div><button className="secondary-button" disabled={busy} onClick={previewTotals} type="button">{busy ? 'Calculating…' : 'Preview total'}</button></div>{preview && <div className="inline-preview" role="status">Backend preview: {formatNumber(preview.total_quantity)} units for {formatMoney(preview.total_amount)} total amount.</div>}<div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || catalogLoading} type="submit">{busy ? 'Saving…' : 'Save draft'}</button></div>
      </form></div></div>}

      {modal === 'details' && selectedOrder && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card order-detail-modal" role="dialog" aria-modal="true" aria-labelledby="sales-order-detail-title"><div className="modal-header"><div><p className="eyebrow">Sales Order detail</p><h2 id="sales-order-detail-title">{selectedOrder.sales_order_number}</h2><p>{partyLabel(selectedOrder)} · ordered {selectedOrder.order_date}</p></div><button aria-label="Close Sales Order details" className="icon-button" onClick={closeModal} type="button">×</button></div><div className="order-detail-summary"><div><span>Party</span><strong>{partyLabel(selectedOrder)}</strong></div><div><span>Required delivery</span><strong>{selectedOrder.required_delivery_date || '—'}</strong></div><div><span>Warehouse</span><strong>{selectedOrder.warehouse?.name || '—'}</strong></div><div><span>Status</span><strong><span className={statusClass(selectedOrder.status)}>{statusLabel(selectedOrder.status)}</span></strong></div><div><span>Ordered</span><strong>{formatNumber(selectedOrder.ordered_quantity)}</strong></div><div><span>Confirmed</span><strong>{formatNumber(selectedOrder.confirmed_quantity)}</strong></div><div><span>Delivered</span><strong>{formatNumber(selectedOrder.delivered_quantity)}</strong></div><div><span>Remaining</span><strong>{formatNumber(selectedOrder.remaining_quantity)}</strong></div><div><span>Total amount</span><strong>{formatMoney(selectedOrder.total_amount)}</strong></div></div>
        {error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}
        <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Sales lines</p><h3>Products and variants</h3></div>{selectedOrder.status === 'draft' && <button className="secondary-button" onClick={() => openEdit(selectedOrder)} type="button">Edit draft</button>}</div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Product</th><th>Variant</th><th>Quantity</th><th>Unit</th><th>Unit price</th><th>Line total</th><th>Remaining</th></tr></thead><tbody>{(selectedOrder.items || []).map((item) => <tr key={item.id}><td>{item.product?.name || '—'}</td><td>{item.product_variant?.sku || item.product_variant?.variant_name || 'Product level'}</td><td>{formatNumber(item.ordered_quantity)}</td><td>{item.unit?.code || item.unit?.symbol || '—'}</td><td>{formatMoney(item.unit_price)}</td><td>{formatMoney(item.line_total)}</td><td>{formatNumber(item.remaining_quantity)}</td></tr>)}</tbody></table></div></section>
        <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Finished goods availability</p><h3>{availability?.available ? 'Covered for confirmation' : 'Availability review'}</h3></div><span className={availability?.available ? 'status-pill status-active' : 'status-pill status-inactive'}>{availability ? (availability.available ? 'Available' : 'Shortage') : 'Loading…'}</span></div>{availability?.lines?.length ? <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Product</th><th>Required</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Shortage</th></tr></thead><tbody>{availability.lines.map((line) => <tr key={line.id}><td>{line.product_variant?.sku || line.product?.name || '—'}</td><td>{formatNumber(line.required_quantity)}</td><td>{formatNumber(line.quantity_on_hand)}</td><td>{formatNumber(line.quantity_reserved)}</td><td>{formatNumber(line.available_quantity)}</td><td>{formatNumber(line.shortage_quantity)}</td></tr>)}</tbody></table></div> : <div className="empty-state">Availability is loading or no lines are present.</div>}</section>
        <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Workflow control</p><h3>Status management</h3></div></div><label className="form-field"><span>Workflow remarks</span><textarea onChange={(event) => setWorkflowRemarks(event.target.value)} placeholder="Optional remarks for this action" rows="2" value={workflowRemarks} /></label><div className="workflow-actions">{selectedOrder.status !== 'completed' && selectedOrder.status !== 'cancelled' && selectedOrder.status !== 'delivered' && selectedOrder.status !== 'ready_for_delivery' && <button className="secondary-button danger-text" disabled={busy} onClick={() => runAction('cancel', 'Sales Order cancelled.')} type="button">Cancel order</button>}{action && <button className="primary-button" disabled={busy} onClick={() => runAction(action.action, action.message, action.status)} type="button">{action.label}</button>}{selectedOrder.status === 'completed' && <span className="completed-note">Completed Sales Orders are read-only.</span>}{selectedOrder.status === 'cancelled' && <span className="completed-note">Cancelled Sales Orders cannot be confirmed.</span>}</div></section>
        <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Status history</p><h3>Immutable transitions</h3></div></div><div className="history-list">{(selectedOrder.status_history || []).map((history) => <div className="history-row" key={history.id}><span>{history.created_at ? new Date(history.created_at).toLocaleString() : '—'}</span><strong>{history.previous_status ? `${statusLabel(history.previous_status)} → ` : ''}{statusLabel(history.new_status)}</strong><span>{history.changed_by?.name || 'System'}</span><span>{history.remarks || '—'}</span></div>)}</div></section>
        <div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div>
      </div></div>}
    </div>
  )
}

export default SalesOrderPage
