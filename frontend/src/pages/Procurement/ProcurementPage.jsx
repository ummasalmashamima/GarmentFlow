import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import masterDataService from '../../services/masterDataService'
import procurementService from '../../services/procurementService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const emptyRequisitionItem = () => ({ material_id: '', unit_id: '', quantity: '', remarks: '' })
const emptyRequisitionForm = () => ({ request_date: today(), required_date: '', priority: 'normal', source: '', remarks: '', items: [emptyRequisitionItem()] })
const emptyOrderForm = () => ({ purchase_requisition_id: '', supplier_id: '', po_date: today(), expected_delivery_date: '', currency: 'USD', payment_terms: '', shipping_terms: '', tax_total: '', discount_total: '', remarks: '', items: [] })
const emptyReceiptForm = () => ({ purchase_order_id: '', supplier_id: '', warehouse_id: '', warehouse_location_id: '', receipt_date: today(), remarks: '', items: [] })

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]
  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function statusLabel(status) {
  return (status || 'draft').replaceAll('_', ' ')
}

function actionPastTense(action) {
  return {
    submit: 'submitted',
    approve: 'approved',
    reject: 'rejected',
    send: 'sent to supplier',
    close: 'closed',
    receive: 'received',
    accept: 'accepted',
    post: 'posted',
  }[action] || action
}

function statusClass(status) {
  return `status-pill order-status status-${(status || 'draft').replaceAll('_', '-')}`
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

function ProcurementPage() {
  const navigate = useNavigate()
  const [tab, setTab] = useState('requisitions')
  const [pages, setPages] = useState({ requisitions: emptyPage, orders: emptyPage, receipts: emptyPage, history: emptyPage })
  const [queries, setQueries] = useState({
    requisitions: { search: '', status: '', priority: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' },
    orders: { search: '', status: '', supplier_id: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' },
    receipts: { search: '', status: '', supplier_id: '', warehouse_id: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' },
    history: { search: '', document_type: '', new_status: '', page: 1, per_page: 15, direction: 'desc' },
  })
  const [catalog, setCatalog] = useState({ suppliers: [], materials: [], units: [], warehouses: [], locations: [] })
  const [approvedRequisitions, setApprovedRequisitions] = useState([])
  const [availableOrders, setAvailableOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [modal, setModal] = useState(null)
  const [selected, setSelected] = useState(null)
  const [form, setForm] = useState(emptyRequisitionForm)
  const [preview, setPreview] = useState(null)
  const [workflowRemarks, setWorkflowRemarks] = useState('')

  const loadList = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const service = { requisitions: procurementService.requisitions, orders: procurementService.orders, receipts: procurementService.receipts }[tab]
      const response = tab === 'history' ? await procurementService.history(queries.history) : await service.list(queries[tab])
      setPages((current) => ({ ...current, [tab]: response }))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [queries, tab])

  useEffect(() => {
    Promise.resolve().then(() => loadList())
  }, [loadList])

  useEffect(() => {
    let active = true
    Promise.all([
      masterDataService.options('suppliers'),
      masterDataService.options('materials'),
      masterDataService.options('units'),
      masterDataService.options('warehouses'),
      masterDataService.options('warehouse-locations'),
    ]).then(([suppliers, materials, units, warehouses, locations]) => {
      if (active) setCatalog({ suppliers, materials, units, warehouses, locations })
    }).catch((requestError) => { if (active) setError(errorMessage(requestError)) })
    return () => { active = false }
  }, [])

  const records = pages[tab]?.data || []
  const meta = pages[tab]?.meta || emptyPage.meta
  const query = queries[tab]

  const updateQuery = (changes) => setQueries((current) => ({ ...current, [tab]: { ...current[tab], ...changes, page: changes.page ?? 1 } }))
  const toggleSort = (column) => setQueries((current) => ({ ...current, [tab]: { ...current[tab], sort: column, direction: current[tab].sort === column && current[tab].direction === 'asc' ? 'desc' : 'asc', page: 1 } }))
  const sortLabel = (column, label) => `${label}${query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}`
  const updateForm = (name, value) => setForm((current) => ({ ...current, [name]: value }))
  const updateItem = (index, name, value) => setForm((current) => ({ ...current, items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, [name]: value } : item) }))
  const addItem = (emptyItem) => setForm((current) => ({ ...current, items: [...current.items, emptyItem()] }))
  const removeItem = (index) => setForm((current) => ({ ...current, items: current.items.length <= 1 ? current.items : current.items.filter((_, itemIndex) => itemIndex !== index) }))
  const closeModal = () => { setModal(null); setSelected(null); setPreview(null); setWorkflowRemarks('') }
  const refresh = async () => { await loadList() }

  const openRequisitionForm = (requisition = null) => {
    setSelected(requisition)
    setPreview(null)
    setError('')
    setForm(requisition ? {
      request_date: requisition.request_date || today(),
      required_date: requisition.required_date || '',
      priority: requisition.priority || 'normal',
      source: requisition.source || '',
      remarks: requisition.remarks || '',
      items: (requisition.items || []).map((item) => ({ material_id: item.material_id, unit_id: item.unit_id, quantity: item.quantity, remarks: item.remarks || '' })),
    } : emptyRequisitionForm())
    setModal('requisition-form')
  }

  const openOrderForm = async (requisition = null) => {
    setBusy(true); setError('')
    try {
      const response = await procurementService.requisitions.list({ status: 'approved', per_page: 100, sort: 'required_date', direction: 'asc' })
      setApprovedRequisitions(response.data || [])
      if (!requisition && response.data?.length === 0) throw new Error('No approved Purchase Requisitions are available for conversion.')
      const detail = requisition?.items ? requisition : (requisition ? await procurementService.requisitions.get(requisition.id) : null)
      setSelected(detail)
      setForm(detail ? {
        ...emptyOrderForm(),
        purchase_requisition_id: detail.id,
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({ purchase_requisition_item_id: item.id, material_id: item.material_id, unit_id: item.unit_id, material_name: item.material?.name || '', quantity: item.remaining_quantity, unit_price: '', remarks: item.remarks || '' })),
      } : emptyOrderForm())
      setModal('order-form')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const chooseRequisition = async (id) => {
    if (!id) { setForm((current) => ({ ...current, purchase_requisition_id: '', items: [] })); return }
    setBusy(true)
    try {
      const detail = await procurementService.requisitions.get(id)
      setSelected(detail)
      setForm((current) => ({ ...current, purchase_requisition_id: detail.id, items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({ purchase_requisition_item_id: item.id, material_id: item.material_id, unit_id: item.unit_id, material_name: item.material?.name || '', quantity: item.remaining_quantity, unit_price: '', remarks: item.remarks || '' })) }))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const openReceiptForm = async (order = null) => {
    setBusy(true); setError('')
    try {
      const [sent, partial] = await Promise.all([
        procurementService.orders.list({ status: 'sent_to_supplier', per_page: 100 }),
        procurementService.orders.list({ status: 'partially_received', per_page: 100 }),
      ])
      const options = [...(sent.data || []), ...(partial.data || [])]
      setAvailableOrders(options)
      if (!order && options.length === 0) throw new Error('No Purchase Orders are ready for Goods Receipt.')
      const detail = order?.items ? order : (order ? await procurementService.orders.get(order.id) : null)
      setSelected(detail)
      setForm(detail ? {
        ...emptyReceiptForm(),
        purchase_order_id: detail.id,
        supplier_id: detail.supplier_id,
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({ purchase_order_item_id: item.id, material_id: item.material_id, unit_id: item.unit_id, material_name: item.material?.name || '', remaining_quantity: item.remaining_quantity, received_quantity: '', accepted_quantity: '', rejected_quantity: '0' })),
      } : emptyReceiptForm())
      setModal('receipt-form')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const chooseOrder = async (id) => {
    if (!id) { setForm((current) => ({ ...current, purchase_order_id: '', supplier_id: '', items: [] })); return }
    setBusy(true)
    try {
      const detail = await procurementService.orders.get(id)
      setSelected(detail)
      setForm((current) => ({ ...current, purchase_order_id: detail.id, supplier_id: detail.supplier_id, items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({ purchase_order_item_id: item.id, material_id: item.material_id, unit_id: item.unit_id, material_name: item.material?.name || '', remaining_quantity: item.remaining_quantity, received_quantity: '', accepted_quantity: '', rejected_quantity: '0' })) }))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const openDetails = async (type, record) => {
    setBusy(true); setError('')
    try {
      const detail = type === 'requisition' ? await procurementService.requisitions.get(record.id) : type === 'order' ? await procurementService.orders.get(record.id) : await procurementService.receipts.get(record.id)
      setSelected(detail); setWorkflowRemarks(''); setModal(`${type}-details`)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const submitRequisition = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { ...form, items: form.items.map((item) => ({ material_id: Number(item.material_id), unit_id: Number(item.unit_id), quantity: Number(item.quantity), remarks: item.remarks || null })) }
      const detail = selected ? await procurementService.requisitions.update(selected.id, payload) : await procurementService.requisitions.create(payload)
      setSelected(detail); setModal('requisition-details'); setNotice('Purchase Requisition saved successfully.'); await refresh()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const previewOrder = async () => {
    setBusy(true); setError('')
    try {
      const payload = { ...form, supplier_id: Number(form.supplier_id), purchase_requisition_id: Number(form.purchase_requisition_id), tax_total: Number(form.tax_total || 0), discount_total: Number(form.discount_total || 0), items: form.items.map((item) => ({ purchase_requisition_item_id: Number(item.purchase_requisition_item_id), material_id: Number(item.material_id), unit_id: Number(item.unit_id), quantity: Number(item.quantity), unit_price: Number(item.unit_price) })) }
      setPreview(await procurementService.orders.preview(payload))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const submitOrder = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { ...form, supplier_id: Number(form.supplier_id), purchase_requisition_id: Number(form.purchase_requisition_id), tax_total: Number(form.tax_total || 0), discount_total: Number(form.discount_total || 0), items: form.items.map((item) => ({ purchase_requisition_item_id: Number(item.purchase_requisition_item_id), quantity: Number(item.quantity), unit_price: Number(item.unit_price), remarks: item.remarks || null })) }
      const detail = await procurementService.orders.create(payload)
      setSelected(detail); setModal('order-details'); setPreview(null); setNotice('Purchase Order draft created successfully.'); await refresh()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const submitReceipt = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { ...form, purchase_order_id: Number(form.purchase_order_id), supplier_id: Number(form.supplier_id), warehouse_id: Number(form.warehouse_id), warehouse_location_id: form.warehouse_location_id ? Number(form.warehouse_location_id) : null, items: form.items.filter((item) => Number(item.received_quantity) > 0).map((item) => ({ purchase_order_item_id: Number(item.purchase_order_item_id), received_quantity: Number(item.received_quantity), accepted_quantity: Number(item.accepted_quantity), rejected_quantity: Number(item.rejected_quantity), remarks: item.remarks || null })) }
      const detail = await procurementService.receipts.create(payload)
      setSelected(detail); setModal('receipt-details'); setNotice('Goods Receipt draft created successfully.'); await refresh()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const runAction = async (type, action, successMessage) => {
    if (!selected) return
    setBusy(true); setError(''); setNotice('')
    try {
      const service = type === 'requisition' ? procurementService.requisitions : type === 'order' ? procurementService.orders : procurementService.receipts
      const result = await service[action](selected.id, workflowRemarks || null)
      setSelected(result); setWorkflowRemarks(''); setNotice(successMessage); await refresh()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const requisitionAction = selected?.status === 'draft' ? ['submit', 'Submit for approval'] : selected?.status === 'submitted' ? ['approve', 'Approve'] : null
  const orderAction = selected?.status === 'draft' ? ['submit', 'Submit for approval'] : selected?.status === 'submitted' ? ['approve', 'Approve'] : selected?.status === 'approved' ? ['send', 'Send to Supplier'] : selected?.status === 'fully_received' ? ['close', 'Close'] : null
  const receiptAction = selected?.status === 'draft' ? ['receive', 'Mark received'] : selected?.status === 'received' ? ['accept', 'Inspect / accept'] : selected?.status === 'accepted' ? ['post', 'Post receipt'] : null

  const renderFilters = () => {
    if (tab === 'requisitions') return <><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}><option value="">All statuses</option>{['draft', 'submitted', 'approved', 'rejected', 'converted_to_po'].map((value) => <option key={value} value={value}>{statusLabel(value)}</option>)}</select></label><label className="filter-field"><span>Priority</span><select onChange={(event) => updateQuery({ priority: event.target.value })} value={query.priority}><option value="">All priorities</option>{['low', 'normal', 'high', 'urgent'].map((value) => <option key={value} value={value}>{value}</option>)}</select></label></>
    if (tab === 'orders') return <><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}><option value="">All statuses</option>{['draft', 'submitted', 'approved', 'sent_to_supplier', 'partially_received', 'fully_received', 'closed', 'cancelled'].map((value) => <option key={value} value={value}>{statusLabel(value)}</option>)}</select></label><label className="filter-field"><span>Supplier</span><select onChange={(event) => updateQuery({ supplier_id: event.target.value })} value={query.supplier_id}><option value="">All suppliers</option>{catalog.suppliers.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label></>
    if (tab === 'receipts') return <><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}><option value="">All statuses</option>{['draft', 'received', 'accepted', 'posted'].map((value) => <option key={value} value={value}>{statusLabel(value)}</option>)}</select></label><label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}><option value="">All warehouses</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label></>
    return <label className="filter-field"><span>Document type</span><select onChange={(event) => updateQuery({ document_type: event.target.value })} value={query.document_type}><option value="">All documents</option><option value="purchase_requisition">Purchase Requisition</option><option value="purchase_order">Purchase Order</option><option value="goods_receipt">Goods Receipt</option></select></label>
  }

  return (
    <div className="master-data-page procurement-page">
      <div className="page-intro master-data-intro">
        <div><p className="eyebrow">Phase 6 · Procurement Management</p><h1>Procurement Control Center</h1><p>Trace material requirements through requisitions, supplier orders, and warehouse receipt integration.</p></div>
        <div className="procurement-header-actions">{tab === 'requisitions' && <button className="primary-button" onClick={() => openRequisitionForm()} type="button">Create PR</button>}{tab === 'orders' && <button className="primary-button" onClick={() => openOrderForm()} type="button">Create PO</button>}{tab === 'receipts' && <button className="primary-button" onClick={() => openReceiptForm()} type="button">Create GRN</button>}</div>
      </div>
      <div className="planning-tabs procurement-tabs" role="tablist"><button className={tab === 'requisitions' ? 'active' : ''} onClick={() => setTab('requisitions')} role="tab" type="button">Purchase Requisitions</button><button className={tab === 'orders' ? 'active' : ''} onClick={() => setTab('orders')} role="tab" type="button">Purchase Orders</button><button className={tab === 'receipts' ? 'active' : ''} onClick={() => setTab('receipts')} role="tab" type="button">Goods Receipts</button><button className={tab === 'history' ? 'active' : ''} onClick={() => setTab('history')} role="tab" type="button">Procurement History</button></div>
      <div className="master-data-toolbar procurement-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search document, supplier, or status" value={query.search} /></label>{renderFilters()}<span className="record-count">{meta.total || 0} records</span></div>
      {error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}
      <section className="data-card" aria-busy={loading}><div className="data-card-header"><div><p className="eyebrow">Traceable register</p><h2>{tab === 'requisitions' ? 'Purchase Requisitions' : tab === 'orders' ? 'Purchase Orders' : tab === 'receipts' ? 'Goods Receipts' : 'Status History'}</h2></div><span className="data-card-hint">Workflow actions are validated by the backend</span></div>
        {loading ? <div className="empty-state">Loading procurement records…</div> : records.length === 0 ? <div className="empty-state">No records match the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr>{tab === 'requisitions' && <><th><button onClick={() => toggleSort('requisition_number')} type="button">{sortLabel('requisition_number', 'PR number')}</button></th><th>Required date</th><th>Priority</th><th>Status</th><th>Items</th><th>Requested by</th></>}{tab === 'orders' && <><th><button onClick={() => toggleSort('purchase_order_number')} type="button">{sortLabel('purchase_order_number', 'PO number')}</button></th><th>Supplier</th><th>Expected delivery</th><th>Total</th><th>Status</th></>}{tab === 'receipts' && <><th><button onClick={() => toggleSort('receipt_number')} type="button">{sortLabel('receipt_number', 'GRN number')}</button></th><th>PO</th><th>Supplier</th><th>Warehouse</th><th>Receipt date</th><th>Status</th></>}{tab === 'history' && <><th>Document</th><th>Transition</th><th>Actor</th><th>Remarks</th><th>When</th></>}</tr></thead><tbody>{records.map((record) => <tr key={record.id} onClick={() => tab === 'history' ? null : openDetails(tab === 'requisitions' ? 'requisition' : tab === 'orders' ? 'order' : 'receipt', record)}>{tab === 'requisitions' && <><td><strong>{record.requisition_number}</strong></td><td>{record.required_date || '—'}</td><td>{record.priority}</td><td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td><td>{record.items_count || '—'}</td><td>{record.requester?.name || '—'}</td></>}{tab === 'orders' && <><td><strong>{record.purchase_order_number}</strong></td><td>{record.supplier?.name || '—'}</td><td>{record.expected_delivery_date || '—'}</td><td>{record.currency} {formatMoney(record.total_amount)}</td><td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td></>}{tab === 'receipts' && <><td><strong>{record.receipt_number}</strong></td><td>{record.purchase_order?.purchase_order_number || '—'}</td><td>{record.supplier?.name || '—'}</td><td>{record.warehouse?.name || '—'}</td><td>{record.receipt_date || '—'}</td><td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td></>}{tab === 'history' && <><td>{record.document_type} #{record.document_id}</td><td>{record.previous_status ? `${statusLabel(record.previous_status)} → ` : ''}{statusLabel(record.new_status)}</td><td>{record.changed_by?.name || '—'}</td><td>{record.remarks || '—'}</td><td>{record.created_at ? new Date(record.created_at).toLocaleString() : '—'}</td></>}</tr>)}</tbody></table></div>}
        <div className="pagination-bar"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) - 1 })} type="button">Previous</button><button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => updateQuery({ page: (meta.current_page || 1) + 1 })} type="button">Next</button></div></div>
      </section>
      <button className="secondary-button back-link" onClick={() => navigate('/')} type="button">← Back to workspace</button>

      {modal === 'requisition-form' && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card" role="dialog" aria-modal="true"><div className="modal-header"><div><p className="eyebrow">Purchase Requisition</p><h2>{selected ? 'Edit draft PR' : 'Create Purchase Requisition'}</h2></div><button aria-label="Close PR form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitRequisition}><div className="form-grid"><label className="form-field"><span>Request date *</span><input required onChange={(event) => updateForm('request_date', event.target.value)} type="date" value={form.request_date} /></label><label className="form-field"><span>Required date *</span><input required onChange={(event) => updateForm('required_date', event.target.value)} type="date" value={form.required_date} /></label><label className="form-field"><span>Priority *</span><select required onChange={(event) => updateForm('priority', event.target.value)} value={form.priority}>{['low', 'normal', 'high', 'urgent'].map((value) => <option key={value} value={value}>{value}</option>)}</select></label><label className="form-field"><span>Department/source</span><input onChange={(event) => updateForm('source', event.target.value)} value={form.source} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => updateForm('remarks', event.target.value)} rows="2" value={form.remarks} /></label></div><div className="order-lines-heading"><div><p className="eyebrow">Requested materials</p><h3>PR items</h3></div><button className="secondary-button" onClick={() => addItem(emptyRequisitionItem)} type="button">Add item</button></div><div className="order-edit-lines">{form.items.map((item, index) => <div className="order-edit-line procurement-line" key={`pr-${index}`}><label className="form-field"><span>Material *</span><select required onChange={(event) => updateItem(index, 'material_id', event.target.value)} value={item.material_id}><option value="">Select material</option>{catalog.materials.map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select></label><label className="form-field"><span>Unit *</span><select required onChange={(event) => updateItem(index, 'unit_id', event.target.value)} value={item.unit_id}><option value="">Select unit</option>{catalog.units.map((option) => <option key={option.id} value={option.id}>{option.code} · {option.name}</option>)}</select></label><label className="form-field"><span>Quantity *</span><input min="0.0001" required step="0.0001" onChange={(event) => updateItem(index, 'quantity', event.target.value)} type="number" value={item.quantity} /></label><button aria-label={`Remove PR item ${index + 1}`} className="icon-button danger-text" disabled={form.items.length <= 1} onClick={() => removeItem(index)} type="button">×</button></div>)}</div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Saving…' : 'Save draft'}</button></div></form></div></div>}

      {modal === 'order-form' && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card" role="dialog" aria-modal="true"><div className="modal-header"><div><p className="eyebrow">Purchase Order</p><h2>Create from approved PR</h2></div><button aria-label="Close PO form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitOrder}><div className="form-grid"><label className="form-field full-width"><span>Approved Purchase Requisition *</span><select required onChange={(event) => chooseRequisition(event.target.value)} value={form.purchase_requisition_id}><option value="">Select approved PR</option>{approvedRequisitions.map((item) => <option key={item.id} value={item.id}>{item.requisition_number} · due {item.required_date}</option>)}</select></label><label className="form-field"><span>Supplier *</span><select required onChange={(event) => updateForm('supplier_id', event.target.value)} value={form.supplier_id}><option value="">Select supplier</option>{catalog.suppliers.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>PO date *</span><input required onChange={(event) => updateForm('po_date', event.target.value)} type="date" value={form.po_date} /></label><label className="form-field"><span>Expected delivery *</span><input required onChange={(event) => updateForm('expected_delivery_date', event.target.value)} type="date" value={form.expected_delivery_date} /></label><label className="form-field"><span>Currency</span><input maxLength="10" onChange={(event) => updateForm('currency', event.target.value)} value={form.currency} /></label><label className="form-field"><span>Payment terms</span><input onChange={(event) => updateForm('payment_terms', event.target.value)} value={form.payment_terms} /></label><label className="form-field"><span>Shipping terms</span><input onChange={(event) => updateForm('shipping_terms', event.target.value)} value={form.shipping_terms} /></label><label className="form-field"><span>Tax total</span><input min="0" onChange={(event) => updateForm('tax_total', event.target.value)} step="0.0001" type="number" value={form.tax_total} /></label><label className="form-field"><span>Discount total</span><input min="0" onChange={(event) => updateForm('discount_total', event.target.value)} step="0.0001" type="number" value={form.discount_total} /></label></div><div className="order-lines-heading"><div><p className="eyebrow">Supplier commitment</p><h3>PO items</h3></div></div><div className="order-edit-lines">{form.items.map((item, index) => <div className="order-edit-line procurement-line" key={`po-${item.purchase_requisition_item_id || index}`}><div className="form-field"><span>Material</span><strong>{item.material_name || item.material_id}</strong></div><label className="form-field"><span>Quantity *</span><input min="0.0001" max={item.quantity} required step="0.0001" onChange={(event) => updateItem(index, 'quantity', event.target.value)} type="number" value={item.quantity} /></label><label className="form-field"><span>Unit price *</span><input min="0" required step="0.0001" onChange={(event) => updateItem(index, 'unit_price', event.target.value)} type="number" value={item.unit_price} /></label></div>)}</div><div className="order-preview-bar"><div><span>Subtotal</span><strong>{preview ? formatMoney(preview.subtotal) : '—'}</strong></div><div><span>PO total</span><strong>{preview ? formatMoney(preview.total_amount) : '—'}</strong></div><button className="secondary-button" disabled={busy || !form.items.length} onClick={previewOrder} type="button">{busy ? 'Calculating…' : 'Preview totals'}</button></div>{preview && <div className="inline-preview" role="status">Backend calculation: {formatMoney(preview.subtotal)} subtotal plus tax less discount = {formatMoney(preview.total_amount)} {form.currency}.</div>}<div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || !form.items.length} type="submit">{busy ? 'Creating…' : 'Create PO draft'}</button></div></form></div></div>}

      {modal === 'receipt-form' && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card" role="dialog" aria-modal="true"><div className="modal-header"><div><p className="eyebrow">Goods Receipt</p><h2>Create GRN</h2></div><button aria-label="Close GRN form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitReceipt}><div className="form-grid"><label className="form-field full-width"><span>Receivable Purchase Order *</span><select required onChange={(event) => chooseOrder(event.target.value)} value={form.purchase_order_id}><option value="">Select PO</option>{availableOrders.map((item) => <option key={item.id} value={item.id}>{item.purchase_order_number} · {item.supplier?.name}</option>)}</select></label><label className="form-field"><span>Supplier</span><input disabled value={catalog.suppliers.find((item) => String(item.id) === String(form.supplier_id))?.name || 'Select PO'} /></label><label className="form-field"><span>Warehouse *</span><select required onChange={(event) => updateForm('warehouse_id', event.target.value)} value={form.warehouse_id}><option value="">Select warehouse</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Location</span><select onChange={(event) => updateForm('warehouse_location_id', event.target.value)} value={form.warehouse_location_id}><option value="">Select location</option>{catalog.locations.filter((item) => !form.warehouse_id || String(item.warehouse_id) === String(form.warehouse_id)).map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Receipt date *</span><input required onChange={(event) => updateForm('receipt_date', event.target.value)} type="date" value={form.receipt_date} /></label></div><div className="order-lines-heading"><div><p className="eyebrow">Receipt quantities</p><h3>PO items</h3></div></div><div className="order-edit-lines">{form.items.map((item, index) => <div className="order-edit-line procurement-line" key={`grn-${item.purchase_order_item_id || index}`}><div className="form-field"><span>Material / remaining</span><strong>{item.material_name || item.material_id} · {formatNumber(item.remaining_quantity)}</strong></div><label className="form-field"><span>Received *</span><input min="0.0001" max={item.remaining_quantity} required step="0.0001" onChange={(event) => updateItem(index, 'received_quantity', event.target.value)} type="number" value={item.received_quantity} /></label><label className="form-field"><span>Accepted *</span><input min="0" required step="0.0001" onChange={(event) => updateItem(index, 'accepted_quantity', event.target.value)} type="number" value={item.accepted_quantity} /></label><label className="form-field"><span>Rejected *</span><input min="0" required step="0.0001" onChange={(event) => updateItem(index, 'rejected_quantity', event.target.value)} type="number" value={item.rejected_quantity} /></label></div>)}</div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || !form.items.length} type="submit">{busy ? 'Saving…' : 'Save GRN draft'}</button></div></form></div></div>}

      {modal === 'requisition-details' && selected && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card procurement-detail-modal" role="dialog" aria-modal="true"><DetailHeader eyebrow="Purchase Requisition" title={selected.requisition_number} status={selected.status} onClose={closeModal} /><DetailSummary values={[["Request date", selected.request_date], ["Required date", selected.required_date], ["Priority", selected.priority], ["Requested by", selected.requester?.name || '—']]} /><DetailItems items={selected.items || []} type="requisition" /><WorkflowPanel remarks={workflowRemarks} setRemarks={setWorkflowRemarks} busy={busy} action={requisitionAction} onAction={() => runAction('requisition', requisitionAction[0], `Purchase Requisition ${actionPastTense(requisitionAction[0])} successfully.`)} extra={selected.status === 'approved' ? <button className="primary-button" disabled={busy} onClick={() => openOrderForm(selected)} type="button">Convert to PO</button> : null} /><HistoryList histories={selected.status_history || []} /><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div></div></div>}
      {modal === 'order-details' && selected && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card procurement-detail-modal" role="dialog" aria-modal="true"><DetailHeader eyebrow="Purchase Order" title={selected.purchase_order_number} status={selected.status} onClose={closeModal} /><DetailSummary values={[["Supplier", selected.supplier?.name || '—'], ["PO date", selected.po_date], ["Expected delivery", selected.expected_delivery_date], ["Subtotal", `${selected.currency} ${formatMoney(selected.subtotal)}`], ["Total", `${selected.currency} ${formatMoney(selected.total_amount)}`]]} /><DetailItems items={selected.items || []} type="order" /><WorkflowPanel remarks={workflowRemarks} setRemarks={setWorkflowRemarks} busy={busy} action={orderAction} onAction={() => runAction('order', orderAction[0], `Purchase Order ${actionPastTense(orderAction[0])} successfully.`)} extra={['sent_to_supplier', 'partially_received'].includes(selected.status) ? <button className="secondary-button" disabled={busy} onClick={() => openReceiptForm(selected)} type="button">Create GRN</button> : null} /><HistoryList histories={selected.status_history || []} /><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div></div></div>}
      {modal === 'receipt-details' && selected && <div className="modal-backdrop" role="presentation"><div className="modal-card procurement-modal-card procurement-detail-modal" role="dialog" aria-modal="true"><DetailHeader eyebrow="Goods Receipt" title={selected.receipt_number} status={selected.status} onClose={closeModal} /><DetailSummary values={[["Purchase Order", selected.purchase_order?.purchase_order_number || '—'], ["Supplier", selected.supplier?.name || '—'], ["Warehouse", selected.warehouse?.name || '—'], ["Receipt date", selected.receipt_date]]} /><DetailItems items={selected.items || []} type="receipt" /><WorkflowPanel remarks={workflowRemarks} setRemarks={setWorkflowRemarks} busy={busy} action={receiptAction} onAction={() => runAction('receipt', receiptAction[0], `Goods Receipt ${actionPastTense(receiptAction[0])} successfully.`)} /><HistoryList histories={selected.status_history || []} /><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div></div></div>}
    </div>
  )
}

function DetailHeader({ eyebrow, title, status, onClose }) { return <div className="modal-header"><div><p className="eyebrow">{eyebrow}</p><h2>{title}</h2><span className={statusClass(status)}>{statusLabel(status)}</span></div><button aria-label={`Close ${eyebrow} details`} className="icon-button" onClick={onClose} type="button">×</button></div> }
function DetailSummary({ values }) { return <div className="order-detail-summary">{values.map(([label, value]) => <div key={label}><span>{label}</span><strong>{value || '—'}</strong></div>)}</div> }
function DetailItems({ items, type }) { return <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Document lines</p><h3>{type === 'requisition' ? 'Requested materials' : type === 'order' ? 'Supplier commitment' : 'Received quantities'}</h3></div></div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Material</th><th>Unit</th><th>Quantity</th>{type === 'order' && <><th>Unit price</th><th>Line total</th><th>Remaining</th></>}{type === 'receipt' && <><th>Accepted</th><th>Rejected</th></>}</tr></thead><tbody>{items.map((item) => <tr key={item.id}><td>{item.material?.name || item.material_id || '—'}</td><td>{item.unit?.code || '—'}</td><td>{formatNumber(type === 'receipt' ? item.received_quantity : item.quantity)}</td>{type === 'order' && <><td>{formatMoney(item.unit_price)}</td><td>{formatMoney(item.line_total)}</td><td>{formatNumber(item.remaining_quantity)}</td></>}{type === 'receipt' && <><td>{formatNumber(item.accepted_quantity)}</td><td>{formatNumber(item.rejected_quantity)}</td></>}</tr>)}</tbody></table></div></section> }
function WorkflowPanel({ remarks, setRemarks, busy, action, onAction, extra }) { return <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Workflow control</p><h3>Status transition</h3></div></div><label className="form-field"><span>Remarks</span><textarea onChange={(event) => setRemarks(event.target.value)} placeholder="Optional remarks" rows="2" value={remarks} /></label><div className="workflow-actions">{extra}{action && <button className="primary-button" disabled={busy} onClick={onAction} type="button">{busy ? 'Working…' : action[1]}</button>}</div></section> }
function HistoryList({ histories }) { return <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Traceability</p><h3>Status history</h3></div></div><div className="history-list">{histories.length ? histories.map((item) => <div className="history-row" key={item.id}><span>{item.created_at ? new Date(item.created_at).toLocaleString() : '—'}</span><strong>{item.previous_status ? `${statusLabel(item.previous_status)} → ` : ''}{statusLabel(item.new_status)}</strong><span>{item.changed_by?.name || '—'}</span><span>{item.remarks || '—'}</span></div>) : <div className="empty-state">No status history yet.</div>}</div></section> }

export default ProcurementPage
