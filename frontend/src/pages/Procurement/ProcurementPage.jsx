import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import masterDataService from '../../services/masterDataService'
import procurementService from '../../services/procurementService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const futureDate = (days = 14) => {
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toISOString().slice(0, 10)
}

const emptyRequisitionItem = () => ({ material_id: '', unit_id: '', quantity: '', remarks: '' })
const emptyRequisitionForm = () => ({
  request_date: today(),
  required_date: futureDate(14),
  priority: 'normal',
  source: '',
  remarks: '',
  items: [emptyRequisitionItem()],
})
const emptyOrderForm = () => ({
  purchase_requisition_id: '',
  supplier_id: '',
  po_date: today(),
  expected_delivery_date: futureDate(14),
  currency: 'USD',
  payment_terms: 'Net 30',
  shipping_terms: 'FOB',
  tax_total: '',
  discount_total: '',
  remarks: '',
  items: [],
})
const emptyReceiptForm = () => ({
  purchase_order_id: '',
  supplier_id: '',
  warehouse_id: '',
  warehouse_location_id: '',
  receipt_date: today(),
  remarks: '',
  items: [],
})

function errorMessage(error) {
  const response = error?.response?.data
  if (response?.errors && typeof response.errors === 'object') {
    const errorList = Object.values(response.errors).flat()
    if (errorList.length > 0) return errorList.join(' | ')
  }
  return response?.message || error?.message || 'Unable to complete the request. Please try again.'
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
  const [modalError, setModalError] = useState('')
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
  
  const updateForm = (name, value) => {
    setModalError('')
    setForm((current) => ({ ...current, [name]: value }))
  }

  const updateItem = (index, name, value) => {
    setModalError('')
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) => itemIndex === index ? { ...item, [name]: value } : item),
    }))
  }

  const updateRequisitionMaterial = (index, materialId) => {
    setModalError('')
    const selectedMat = catalog.materials.find((m) => String(m.id) === String(materialId))
    const autoUnitId = selectedMat?.unit_id ? String(selectedMat.unit_id) : ''

    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) => {
        if (itemIndex !== index) return item
        return {
          ...item,
          material_id: materialId,
          unit_id: autoUnitId || item.unit_id,
        }
      }),
    }))
  }

  const addItem = (emptyItem) => {
    setModalError('')
    setForm((current) => ({ ...current, items: [...current.items, emptyItem()] }))
  }

  const removeItem = (index) => {
    setModalError('')
    setForm((current) => ({
      ...current,
      items: current.items.length <= 1 ? current.items : current.items.filter((_, itemIndex) => itemIndex !== index),
    }))
  }

  const closeModal = () => {
    setModal(null)
    setSelected(null)
    setPreview(null)
    setWorkflowRemarks('')
    setModalError('')
  }

  const refresh = async () => { await loadList() }

  const openRequisitionForm = (requisition = null) => {
    setSelected(requisition)
    setPreview(null)
    setError('')
    setModalError('')
    setForm(requisition ? {
      request_date: requisition.request_date || today(),
      required_date: requisition.required_date || futureDate(14),
      priority: requisition.priority || 'normal',
      source: requisition.source || '',
      remarks: requisition.remarks || '',
      items: (requisition.items || []).map((item) => ({
        material_id: item.material_id,
        unit_id: item.unit_id,
        quantity: item.quantity,
        remarks: item.remarks || '',
      })),
    } : emptyRequisitionForm())
    setModal('requisition-form')
  }

  const openOrderForm = async (requisition = null) => {
    setBusy(true)
    setError('')
    setModalError('')
    try {
      const response = await procurementService.requisitions.list({ status: 'approved', per_page: 100, sort: 'required_date', direction: 'asc' })
      setApprovedRequisitions(response.data || [])
      if (!requisition && response.data?.length === 0) throw new Error('No approved Purchase Requisitions are available for conversion.')
      const detail = requisition?.items ? requisition : (requisition ? await procurementService.requisitions.get(requisition.id) : null)
      setSelected(detail)
      setForm(detail ? {
        ...emptyOrderForm(),
        purchase_requisition_id: detail.id,
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({
          purchase_requisition_item_id: item.id,
          material_id: item.material_id,
          unit_id: item.unit_id,
          material_name: item.material?.name || '',
          quantity: item.remaining_quantity,
          unit_price: '',
          remarks: item.remarks || '',
        })),
      } : emptyOrderForm())
      setModal('order-form')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const chooseRequisition = async (id) => {
    if (!id) { setForm((current) => ({ ...current, purchase_requisition_id: '', items: [] })); return }
    setBusy(true)
    setModalError('')
    try {
      const detail = await procurementService.requisitions.get(id)
      setSelected(detail)
      setForm((current) => ({
        ...current,
        purchase_requisition_id: detail.id,
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({
          purchase_requisition_item_id: item.id,
          material_id: item.material_id,
          unit_id: item.unit_id,
          material_name: item.material?.name || '',
          quantity: item.remaining_quantity,
          unit_price: '',
          remarks: item.remarks || '',
        })),
      }))
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const openReceiptForm = async (order = null) => {
    setBusy(true)
    setError('')
    setModalError('')
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
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({
          purchase_order_item_id: item.id,
          material_id: item.material_id,
          unit_id: item.unit_id,
          material_name: item.material?.name || '',
          remaining_quantity: item.remaining_quantity,
          received_quantity: item.remaining_quantity,
          accepted_quantity: item.remaining_quantity,
          rejected_quantity: '0',
        })),
      } : emptyReceiptForm())
      setModal('receipt-form')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const chooseOrder = async (id) => {
    if (!id) { setForm((current) => ({ ...current, purchase_order_id: '', supplier_id: '', items: [] })); return }
    setBusy(true)
    setModalError('')
    try {
      const detail = await procurementService.orders.get(id)
      setSelected(detail)
      setForm((current) => ({
        ...current,
        purchase_order_id: detail.id,
        supplier_id: detail.supplier_id,
        items: (detail.items || []).filter((item) => Number(item.remaining_quantity) > 0).map((item) => ({
          purchase_order_item_id: item.id,
          material_id: item.material_id,
          unit_id: item.unit_id,
          material_name: item.material?.name || '',
          remaining_quantity: item.remaining_quantity,
          received_quantity: item.remaining_quantity,
          accepted_quantity: item.remaining_quantity,
          rejected_quantity: '0',
        })),
      }))
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const openDetails = async (type, record) => {
    setBusy(true)
    setError('')
    setModalError('')
    try {
      const detail = type === 'requisition' ? await procurementService.requisitions.get(record.id) : type === 'order' ? await procurementService.orders.get(record.id) : await procurementService.receipts.get(record.id)
      setSelected(detail)
      setWorkflowRemarks('')
      setModal(`${type}-details`)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitRequisition = async (event) => {
    event.preventDefault()
    setModalError('')
    setError('')
    setNotice('')

    if (!form.request_date) {
      setModalError('Request date is required.')
      return
    }
    if (!form.required_date) {
      setModalError('Required date is required.')
      return
    }
    if (form.required_date < form.request_date) {
      setModalError('Required date must be on or after Request date.')
      return
    }
    if (!form.items || form.items.length === 0) {
      setModalError('Please add at least one material item.')
      return
    }
    for (let i = 0; i < form.items.length; i++) {
      const it = form.items[i]
      if (!it.material_id) {
        setModalError(`Please select a Material for Line #${i + 1}.`)
        return
      }
      if (!it.unit_id) {
        setModalError(`Please select a Unit for Line #${i + 1}.`)
        return
      }
      if (!it.quantity || Number(it.quantity) <= 0) {
        setModalError(`Quantity must be greater than zero on Line #${i + 1}.`)
        return
      }
    }

    setBusy(true)
    try {
      const payload = {
        ...form,
        items: form.items.map((item) => ({
          material_id: Number(item.material_id),
          unit_id: Number(item.unit_id),
          quantity: Number(item.quantity),
          remarks: item.remarks ? item.remarks.trim() : null,
        })),
      }
      const detail = selected ? await procurementService.requisitions.update(selected.id, payload) : await procurementService.requisitions.create(payload)
      setSelected(detail)
      setModal('requisition-details')
      setNotice('Purchase Requisition draft saved successfully.')
      await refresh()
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const previewOrder = async () => {
    setBusy(true)
    setModalError('')
    try {
      const payload = {
        ...form,
        supplier_id: Number(form.supplier_id),
        purchase_requisition_id: Number(form.purchase_requisition_id),
        tax_total: Number(form.tax_total || 0),
        discount_total: Number(form.discount_total || 0),
        items: form.items.map((item) => ({
          purchase_requisition_item_id: Number(item.purchase_requisition_item_id),
          material_id: Number(item.material_id),
          unit_id: Number(item.unit_id),
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
        })),
      }
      setPreview(await procurementService.orders.preview(payload))
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitOrder = async (event) => {
    event.preventDefault()
    setBusy(true)
    setModalError('')
    setError('')
    setNotice('')
    try {
      const payload = {
        ...form,
        supplier_id: Number(form.supplier_id),
        purchase_requisition_id: Number(form.purchase_requisition_id),
        tax_total: Number(form.tax_total || 0),
        discount_total: Number(form.discount_total || 0),
        items: form.items.map((item) => ({
          purchase_requisition_item_id: Number(item.purchase_requisition_item_id),
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
          remarks: item.remarks || null,
        })),
      }
      const detail = await procurementService.orders.create(payload)
      setSelected(detail)
      setModal('order-details')
      setPreview(null)
      setNotice('Purchase Order draft created successfully.')
      await refresh()
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitReceipt = async (event) => {
    event.preventDefault()
    setBusy(true)
    setModalError('')
    setError('')
    setNotice('')
    try {
      const payload = {
        ...form,
        purchase_order_id: Number(form.purchase_order_id),
        supplier_id: Number(form.supplier_id),
        warehouse_id: Number(form.warehouse_id),
        warehouse_location_id: form.warehouse_location_id ? Number(form.warehouse_location_id) : null,
        items: form.items
          .filter((item) => Number(item.received_quantity) > 0)
          .map((item) => ({
            purchase_order_item_id: Number(item.purchase_order_item_id),
            received_quantity: Number(item.received_quantity),
            accepted_quantity: Number(item.accepted_quantity),
            rejected_quantity: Number(item.rejected_quantity),
            remarks: item.remarks || null,
          })),
      }
      const detail = await procurementService.receipts.create(payload)
      setSelected(detail)
      setModal('receipt-details')
      setNotice('Goods Receipt draft created successfully.')
      await refresh()
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const runAction = async (type, action, successMessage) => {
    if (!selected) return
    setBusy(true)
    setModalError('')
    setError('')
    setNotice('')
    try {
      const service = type === 'requisition' ? procurementService.requisitions : type === 'order' ? procurementService.orders : procurementService.receipts
      const result = await service[action](selected.id, workflowRemarks || null)
      setSelected(result)
      setWorkflowRemarks('')
      setNotice(successMessage)
      await refresh()
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const requisitionAction = selected?.status === 'draft' ? ['submit', 'Submit for approval'] : selected?.status === 'submitted' ? ['approve', 'Approve'] : null
  const orderAction = selected?.status === 'draft' ? ['submit', 'Submit for approval'] : selected?.status === 'submitted' ? ['approve', 'Approve'] : selected?.status === 'approved' ? ['send', 'Send to Supplier'] : selected?.status === 'fully_received' ? ['close', 'Close'] : null
  const receiptAction = selected?.status === 'draft' ? ['receive', 'Mark received'] : selected?.status === 'received' ? ['accept', 'Inspect / accept'] : selected?.status === 'accepted' ? ['post', 'Post receipt'] : null

  const totalRequisitionQuantity = form.items?.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0) || 0

  const renderFilters = () => {
    if (tab === 'requisitions') {
      return (
        <>
          <label className="filter-field">
            <span>Status</span>
            <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
              <option value="">All statuses</option>
              {['draft', 'submitted', 'approved', 'rejected', 'converted_to_po'].map((value) => (
                <option key={value} value={value}>{statusLabel(value)}</option>
              ))}
            </select>
          </label>
          <label className="filter-field">
            <span>Priority</span>
            <select onChange={(event) => updateQuery({ priority: event.target.value })} value={query.priority}>
              <option value="">All priorities</option>
              {['low', 'normal', 'high', 'urgent'].map((value) => (
                <option key={value} value={value}>{value}</option>
              ))}
            </select>
          </label>
        </>
      )
    }
    if (tab === 'orders') {
      return (
        <>
          <label className="filter-field">
            <span>Status</span>
            <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
              <option value="">All statuses</option>
              {['draft', 'submitted', 'approved', 'sent_to_supplier', 'partially_received', 'fully_received', 'closed', 'cancelled'].map((value) => (
                <option key={value} value={value}>{statusLabel(value)}</option>
              ))}
            </select>
          </label>
          <label className="filter-field">
            <span>Supplier</span>
            <select onChange={(event) => updateQuery({ supplier_id: event.target.value })} value={query.supplier_id}>
              <option value="">All suppliers</option>
              {catalog.suppliers.map((item) => (
                <option key={item.id} value={item.id}>{item.code} · {item.name}</option>
              ))}
            </select>
          </label>
        </>
      )
    }
    if (tab === 'receipts') {
      return (
        <>
          <label className="filter-field">
            <span>Status</span>
            <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
              <option value="">All statuses</option>
              {['draft', 'received', 'accepted', 'posted'].map((value) => (
                <option key={value} value={value}>{statusLabel(value)}</option>
              ))}
            </select>
          </label>
          <label className="filter-field">
            <span>Warehouse</span>
            <select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}>
              <option value="">All warehouses</option>
              {catalog.warehouses.map((item) => (
                <option key={item.id} value={item.id}>{item.code} · {item.name}</option>
              ))}
            </select>
          </label>
        </>
      )
    }
    return (
      <label className="filter-field">
        <span>Document type</span>
        <select onChange={(event) => updateQuery({ document_type: event.target.value })} value={query.document_type}>
          <option value="">All documents</option>
          <option value="purchase_requisition">Purchase Requisition</option>
          <option value="purchase_order">Purchase Order</option>
          <option value="goods_receipt">Goods Receipt</option>
        </select>
      </label>
    )
  }

  return (
    <div className="master-data-page procurement-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 6 · Procurement Management</p>
          <h1>Procurement Control Center</h1>
          <p>Trace material requirements through requisitions, supplier orders, and warehouse receipt integration.</p>
        </div>
        <div className="procurement-header-actions">
          {tab === 'requisitions' && <button className="primary-button" onClick={() => openRequisitionForm()} type="button">+ Create PR</button>}
          {tab === 'orders' && <button className="primary-button" onClick={() => openOrderForm()} type="button">+ Create PO</button>}
          {tab === 'receipts' && <button className="primary-button" onClick={() => openReceiptForm()} type="button">+ Create GRN</button>}
        </div>
      </div>

      <div className="planning-tabs procurement-tabs" role="tablist">
        <button className={tab === 'requisitions' ? 'active' : ''} onClick={() => setTab('requisitions')} role="tab" type="button">Purchase Requisitions</button>
        <button className={tab === 'orders' ? 'active' : ''} onClick={() => setTab('orders')} role="tab" type="button">Purchase Orders</button>
        <button className={tab === 'receipts' ? 'active' : ''} onClick={() => setTab('receipts')} role="tab" type="button">Goods Receipts</button>
        <button className={tab === 'history' ? 'active' : ''} onClick={() => setTab('history')} role="tab" type="button">Procurement History</button>
      </div>

      <div className="master-data-toolbar procurement-toolbar">
        <label className="search-field">
          <span>Search</span>
          <input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search document, supplier, or status" value={query.search} />
        </label>
        {renderFilters()}
        <span className="record-count">{meta.total || 0} records</span>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}
      {notice && <div className="feedback-message success-message" role="status">{notice}</div>}

      <section aria-busy={loading} className="data-card">
        <div className="data-card-header">
          <div>
            <p className="eyebrow">Traceable register</p>
            <h2>{tab === 'requisitions' ? 'Purchase Requisitions' : tab === 'orders' ? 'Purchase Orders' : tab === 'receipts' ? 'Goods Receipts' : 'Status History'}</h2>
          </div>
          <span className="data-card-hint">Workflow actions are validated by the backend</span>
        </div>

        {loading ? (
          <div className="empty-state">Loading procurement records…</div>
        ) : records.length === 0 ? (
          <div className="empty-state">No records match the current filters.</div>
        ) : (
          <div className="table-wrap">
            <table className="master-data-table">
              <thead>
                <tr>
                  {tab === 'requisitions' && (
                    <>
                      <th><button onClick={() => toggleSort('requisition_number')} type="button">{sortLabel('requisition_number', 'PR number')}</button></th>
                      <th>Required date</th>
                      <th>Priority</th>
                      <th>Status</th>
                      <th>Items</th>
                      <th>Requested by</th>
                      <th><span className="sr-only">Actions</span></th>
                    </>
                  )}
                  {tab === 'orders' && (
                    <>
                      <th><button onClick={() => toggleSort('purchase_order_number')} type="button">{sortLabel('purchase_order_number', 'PO number')}</button></th>
                      <th>Supplier</th>
                      <th>Expected delivery</th>
                      <th>Total</th>
                      <th>Status</th>
                      <th><span className="sr-only">Actions</span></th>
                    </>
                  )}
                  {tab === 'receipts' && (
                    <>
                      <th><button onClick={() => toggleSort('receipt_number')} type="button">{sortLabel('receipt_number', 'GRN number')}</button></th>
                      <th>PO</th>
                      <th>Supplier</th>
                      <th>Warehouse</th>
                      <th>Receipt date</th>
                      <th>Status</th>
                      <th><span className="sr-only">Actions</span></th>
                    </>
                  )}
                  {tab === 'history' && (
                    <>
                      <th>Document</th>
                      <th>Transition</th>
                      <th>Actor</th>
                      <th>Remarks</th>
                      <th>When</th>
                    </>
                  )}
                </tr>
              </thead>
              <tbody>
                {records.map((record) => (
                  <tr key={record.id} onClick={() => tab === 'history' ? null : openDetails(tab === 'requisitions' ? 'requisition' : tab === 'orders' ? 'order' : 'receipt', record)}>
                    {tab === 'requisitions' && (
                      <>
                        <td><strong>{record.requisition_number}</strong></td>
                        <td>{record.required_date || '—'}</td>
                        <td>
                          <span className={`status-pill ${record.priority === 'urgent' ? 'status-danger' : record.priority === 'high' ? 'status-warning' : 'status-neutral'}`}>
                            {record.priority}
                          </span>
                        </td>
                        <td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td>
                        <td>{record.items_count || record.items?.length || '—'}</td>
                        <td>{record.requester?.name || '—'}</td>
                        <td>
                          <div className="table-actions" onClick={(e) => e.stopPropagation()}>
                            <button className="text-button" onClick={() => openDetails('requisition', record)} type="button">Open</button>
                            {record.status === 'draft' && (
                              <button className="text-button" onClick={() => openRequisitionForm(record)} type="button">Edit</button>
                            )}
                          </div>
                        </td>
                      </>
                    )}
                    {tab === 'orders' && (
                      <>
                        <td><strong>{record.purchase_order_number}</strong></td>
                        <td>{record.supplier?.name || '—'}</td>
                        <td>{record.expected_delivery_date || '—'}</td>
                        <td>{record.currency} {formatMoney(record.total_amount)}</td>
                        <td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td>
                        <td>
                          <button className="text-button" onClick={(e) => { e.stopPropagation(); openDetails('order', record) }} type="button">Open</button>
                        </td>
                      </>
                    )}
                    {tab === 'receipts' && (
                      <>
                        <td><strong>{record.receipt_number}</strong></td>
                        <td>{record.purchase_order?.purchase_order_number || '—'}</td>
                        <td>{record.supplier?.name || '—'}</td>
                        <td>{record.warehouse?.name || '—'}</td>
                        <td>{record.receipt_date || '—'}</td>
                        <td><span className={statusClass(record.status)}>{statusLabel(record.status)}</span></td>
                        <td>
                          <button className="text-button" onClick={(e) => { e.stopPropagation(); openDetails('receipt', record) }} type="button">Open</button>
                        </td>
                      </>
                    )}
                    {tab === 'history' && (
                      <>
                        <td>{record.document_type} #{record.document_id}</td>
                        <td>{record.previous_status ? `${statusLabel(record.previous_status)} → ` : ''}{statusLabel(record.new_status)}</td>
                        <td>{record.changed_by?.name || '—'}</td>
                        <td>{record.remarks || '—'}</td>
                        <td>{record.created_at ? new Date(record.created_at).toLocaleString() : '—'}</td>
                      </>
                    )}
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

      <button className="secondary-button back-link" onClick={() => navigate('/')} type="button">← Back to workspace</button>

      {/* CREATE / EDIT PURCHASE REQUISITION MODAL */}
      {modal === 'requisition-form' && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-labelledby="pr-modal-title" aria-modal="true" className="modal-card procurement-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Purchase Requisition</p>
                <h2 id="pr-modal-title">{selected ? `Edit Draft PR #${selected.requisition_number || selected.id}` : 'Create Purchase Requisition'}</h2>
              </div>
              <button aria-label="Close PR form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>
                {modalError}
              </div>
            )}

            <form className="master-data-form" onSubmit={submitRequisition}>
              <div className="form-grid">
                <label className="form-field">
                  <span>Request Date *</span>
                  <input
                    onChange={(event) => updateForm('request_date', event.target.value)}
                    required
                    type="date"
                    value={form.request_date}
                  />
                </label>

                <label className="form-field">
                  <span>Required Date *</span>
                  <input
                    onChange={(event) => updateForm('required_date', event.target.value)}
                    required
                    type="date"
                    value={form.required_date}
                  />
                </label>

                <label className="form-field">
                  <span>Priority *</span>
                  <select
                    onChange={(event) => updateForm('priority', event.target.value)}
                    required
                    value={form.priority}
                  >
                    <option value="low">Low (Standard replenishments)</option>
                    <option value="normal">Normal (Standard production)</option>
                    <option value="high">High (Priority batch / export)</option>
                    <option value="urgent">Urgent (Line stoppage risk)</option>
                  </select>
                </label>

                <label className="form-field">
                  <span>Department / Source</span>
                  <input
                    onChange={(event) => updateForm('source', event.target.value)}
                    placeholder="e.g. Cutting Division / MRP Run"
                    value={form.source}
                  />
                </label>

                <label className="form-field full-width">
                  <span>Requisition Notes & Business Justification</span>
                  <textarea
                    onChange={(event) => updateForm('remarks', event.target.value)}
                    placeholder="Provide details about why these materials are requested"
                    rows="2"
                    value={form.remarks}
                  />
                </label>
              </div>

              <div className="order-lines-heading">
                <div>
                  <p className="eyebrow">Requested materials</p>
                  <h3>PR Items ({form.items.length})</h3>
                </div>
                <button className="secondary-button" onClick={() => addItem(emptyRequisitionItem)} type="button">
                  + Add Item Line
                </button>
              </div>

              <div className="order-edit-lines">
                {form.items.map((item, index) => (
                  <div className="procurement-item-card" key={`pr-${index}`}>
                    <div className="procurement-item-header">
                      <div className="procurement-item-badge">
                        <span>Item Line #{index + 1}</span>
                      </div>
                      <button
                        aria-label={`Remove PR item ${index + 1}`}
                        className="icon-button danger-text"
                        disabled={form.items.length <= 1}
                        onClick={() => removeItem(index)}
                        title="Remove item"
                        type="button"
                      >
                        ×
                      </button>
                    </div>

                    <div className="procurement-item-row-top">
                      <label className="form-field">
                        <span>Material *</span>
                        <select
                          onChange={(event) => updateRequisitionMaterial(index, event.target.value)}
                          required
                          value={item.material_id}
                        >
                          <option value="">Select material</option>
                          {catalog.materials.map((option) => (
                            <option key={option.id} value={option.id}>
                              {option.code} · {option.name}
                            </option>
                          ))}
                        </select>
                      </label>

                      <label className="form-field">
                        <span>Unit *</span>
                        <select
                          onChange={(event) => updateItem(index, 'unit_id', event.target.value)}
                          required
                          value={item.unit_id}
                        >
                          <option value="">Select unit</option>
                          {catalog.units.map((option) => (
                            <option key={option.id} value={option.id}>
                              {option.code} · {option.name}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>

                    <div className="procurement-item-row-bottom">
                      <label className="form-field">
                        <span>Required Quantity *</span>
                        <input
                          min="0.0001"
                          onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                          placeholder="e.g. 500"
                          required
                          step="any"
                          type="number"
                          value={item.quantity}
                        />
                      </label>

                      <label className="form-field">
                        <span>Item Remarks / Specs</span>
                        <input
                          onChange={(event) => updateItem(index, 'remarks', event.target.value)}
                          placeholder="e.g. Batch spec or shade requirement"
                          value={item.remarks}
                        />
                      </label>
                    </div>
                  </div>
                ))}
              </div>

              <div className="order-preview-bar">
                <div>
                  <span>Total Items</span>
                  <strong>{form.items.length} lines</strong>
                </div>
                <div>
                  <span>Total Requested Quantity</span>
                  <strong>{formatNumber(totalRequisitionQuantity)} units</strong>
                </div>
              </div>

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={busy} type="submit">
                  {busy ? 'Saving…' : 'Save Requisition Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE PURCHASE ORDER FROM PR */}
      {modal === 'order-form' && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-labelledby="po-modal-title" aria-modal="true" className="modal-card procurement-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Purchase Order</p>
                <h2 id="po-modal-title">Create PO from Approved PR</h2>
              </div>
              <button aria-label="Close PO form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>
                {modalError}
              </div>
            )}

            <form className="master-data-form" onSubmit={submitOrder}>
              <div className="form-grid">
                <label className="form-field full-width">
                  <span>Approved Purchase Requisition *</span>
                  <select required onChange={(event) => chooseRequisition(event.target.value)} value={form.purchase_requisition_id}>
                    <option value="">Select approved PR</option>
                    {approvedRequisitions.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.requisition_number} · due {item.required_date}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Supplier *</span>
                  <select required onChange={(event) => updateForm('supplier_id', event.target.value)} value={form.supplier_id}>
                    <option value="">Select supplier</option>
                    {catalog.suppliers.map((item) => (
                      <option key={item.id} value={item.id}>{item.code} · {item.name}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>PO Date *</span>
                  <input required onChange={(event) => updateForm('po_date', event.target.value)} type="date" value={form.po_date} />
                </label>

                <label className="form-field">
                  <span>Expected Delivery *</span>
                  <input required onChange={(event) => updateForm('expected_delivery_date', event.target.value)} type="date" value={form.expected_delivery_date} />
                </label>

                <label className="form-field">
                  <span>Currency</span>
                  <input maxLength="10" onChange={(event) => updateForm('currency', event.target.value)} value={form.currency} />
                </label>

                <label className="form-field">
                  <span>Payment Terms</span>
                  <input onChange={(event) => updateForm('payment_terms', event.target.value)} value={form.payment_terms} />
                </label>

                <label className="form-field">
                  <span>Shipping Terms</span>
                  <input onChange={(event) => updateForm('shipping_terms', event.target.value)} value={form.shipping_terms} />
                </label>

                <label className="form-field">
                  <span>Tax Total</span>
                  <input min="0" onChange={(event) => updateForm('tax_total', event.target.value)} step="any" type="number" value={form.tax_total} />
                </label>

                <label className="form-field">
                  <span>Discount Total</span>
                  <input min="0" onChange={(event) => updateForm('discount_total', event.target.value)} step="any" type="number" value={form.discount_total} />
                </label>
              </div>

              <div className="order-lines-heading">
                <div>
                  <p className="eyebrow">Supplier commitment</p>
                  <h3>PO Items ({form.items.length})</h3>
                </div>
              </div>

              <div className="order-edit-lines">
                {form.items.map((item, index) => (
                  <div className="procurement-item-card" key={`po-${item.purchase_requisition_item_id || index}`}>
                    <div className="procurement-item-header">
                      <div className="procurement-item-badge">
                        <span>PO Line #{index + 1}</span>
                      </div>
                      <span className="data-card-hint">Max available: {formatNumber(item.quantity)}</span>
                    </div>

                    <div className="procurement-item-row-top">
                      <div className="form-field">
                        <span>Material</span>
                        <strong style={{ fontSize: '14px', paddingTop: '8px' }}>{item.material_name || item.material_id}</strong>
                      </div>
                      <label className="form-field">
                        <span>Order Quantity *</span>
                        <input
                          max={item.quantity}
                          min="0.0001"
                          onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                          required
                          step="any"
                          type="number"
                          value={item.quantity}
                        />
                      </label>
                    </div>

                    <div className="procurement-item-row-bottom">
                      <label className="form-field">
                        <span>Unit Price ({form.currency}) *</span>
                        <input
                          min="0"
                          onChange={(event) => updateItem(index, 'unit_price', event.target.value)}
                          placeholder="0.00"
                          required
                          step="any"
                          type="number"
                          value={item.unit_price}
                        />
                      </label>
                      <div className="buyer-order-line-total-box">
                        <span>Line Subtotal</span>
                        <strong>{formatMoney((Number(item.quantity) || 0) * (Number(item.unit_price) || 0))} {form.currency}</strong>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <div className="order-preview-bar">
                <div>
                  <span>Subtotal</span>
                  <strong>{preview ? formatMoney(preview.subtotal) : '—'} {form.currency}</strong>
                </div>
                <div>
                  <span>PO Total</span>
                  <strong>{preview ? formatMoney(preview.total_amount) : '—'} {form.currency}</strong>
                </div>
                <button className="secondary-button" disabled={busy || !form.items.length} onClick={previewOrder} type="button">
                  {busy ? 'Calculating…' : 'Preview Totals'}
                </button>
              </div>

              {preview && (
                <div className="inline-preview" role="status" style={{ marginTop: '14px' }}>
                  Backend calculated: {formatMoney(preview.subtotal)} subtotal + {formatMoney(preview.tax_total)} tax - {formatMoney(preview.discount_total)} discount = <strong>{formatMoney(preview.total_amount)} {form.currency}</strong>.
                </div>
              )}

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={busy || !form.items.length} type="submit">
                  {busy ? 'Creating…' : 'Create PO Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE GOODS RECEIPT MODAL */}
      {modal === 'receipt-form' && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-labelledby="grn-modal-title" aria-modal="true" className="modal-card procurement-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Goods Receipt</p>
                <h2 id="grn-modal-title">Create GRN</h2>
              </div>
              <button aria-label="Close GRN form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {modalError && (
              <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>
                {modalError}
              </div>
            )}

            <form className="master-data-form" onSubmit={submitReceipt}>
              <div className="form-grid">
                <label className="form-field full-width">
                  <span>Receivable Purchase Order *</span>
                  <select required onChange={(event) => chooseOrder(event.target.value)} value={form.purchase_order_id}>
                    <option value="">Select PO</option>
                    {availableOrders.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.purchase_order_number} · {item.supplier?.name}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Supplier</span>
                  <input disabled style={{ backgroundColor: 'var(--slate-100)', cursor: 'not-allowed' }} value={catalog.suppliers.find((item) => String(item.id) === String(form.supplier_id))?.name || 'Select PO'} />
                </label>

                <label className="form-field">
                  <span>Warehouse *</span>
                  <select required onChange={(event) => updateForm('warehouse_id', event.target.value)} value={form.warehouse_id}>
                    <option value="">Select warehouse</option>
                    {catalog.warehouses.map((item) => (
                      <option key={item.id} value={item.id}>{item.code} · {item.name}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Warehouse Location</span>
                  <select onChange={(event) => updateForm('warehouse_location_id', event.target.value)} value={form.warehouse_location_id}>
                    <option value="">Select location</option>
                    {catalog.locations
                      .filter((item) => !form.warehouse_id || String(item.warehouse_id) === String(form.warehouse_id))
                      .map((item) => (
                        <option key={item.id} value={item.id}>{item.code} · {item.name}</option>
                      ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Receipt Date *</span>
                  <input required onChange={(event) => updateForm('receipt_date', event.target.value)} type="date" value={form.receipt_date} />
                </label>
              </div>

              <div className="order-lines-heading">
                <div>
                  <p className="eyebrow">Receipt quantities</p>
                  <h3>PO Items</h3>
                </div>
              </div>

              <div className="order-edit-lines">
                {form.items.map((item, index) => (
                  <div className="procurement-item-card" key={`grn-${item.purchase_order_item_id || index}`}>
                    <div className="procurement-item-header">
                      <div className="procurement-item-badge">
                        <span>{item.material_name || item.material_id}</span>
                      </div>
                      <span className="data-card-hint">Pending: {formatNumber(item.remaining_quantity)}</span>
                    </div>

                    <div className="form-grid" style={{ marginBottom: 0 }}>
                      <label className="form-field">
                        <span>Received Quantity *</span>
                        <input
                          max={item.remaining_quantity}
                          min="0.0001"
                          onChange={(event) => updateItem(index, 'received_quantity', event.target.value)}
                          required
                          step="any"
                          type="number"
                          value={item.received_quantity}
                        />
                      </label>
                      <label className="form-field">
                        <span>Accepted Quantity *</span>
                        <input
                          min="0"
                          onChange={(event) => updateItem(index, 'accepted_quantity', event.target.value)}
                          required
                          step="any"
                          type="number"
                          value={item.accepted_quantity}
                        />
                      </label>
                      <label className="form-field">
                        <span>Rejected Quantity</span>
                        <input
                          min="0"
                          onChange={(event) => updateItem(index, 'rejected_quantity', event.target.value)}
                          step="any"
                          type="number"
                          value={item.rejected_quantity}
                        />
                      </label>
                    </div>
                  </div>
                ))}
              </div>

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={busy || !form.items.length} type="submit">
                  {busy ? 'Saving…' : 'Save GRN Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* REQUISITION DETAILS MODAL */}
      {modal === 'requisition-details' && selected && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-modal="true" className="modal-card procurement-modal-card procurement-detail-modal" role="dialog">
            <DetailHeader eyebrow="Purchase Requisition" onClose={closeModal} status={selected.status} title={selected.requisition_number} />
            {modalError && <div className="feedback-message error-message" role="alert" style={{ margin: '16px 24px' }}>{modalError}</div>}
            <DetailSummary values={[['Request date', selected.request_date], ['Required date', selected.required_date], ['Priority', selected.priority], ['Requested by', selected.requester?.name || '—']]} />
            <DetailItems items={selected.items || []} type="requisition" />
            <WorkflowPanel
              action={requisitionAction}
              busy={busy}
              extra={selected.status === 'approved' ? <button className="primary-button" disabled={busy} onClick={() => openOrderForm(selected)} type="button">Convert to PO</button> : null}
              onAction={() => runAction('requisition', requisitionAction[0], `Purchase Requisition ${actionPastTense(requisitionAction[0])} successfully.`)}
              remarks={workflowRemarks}
              setRemarks={setWorkflowRemarks}
            />
            <HistoryList histories={selected.status_history || []} />
            <div className="modal-actions" style={{ padding: '16px 24px' }}>
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* ORDER DETAILS MODAL */}
      {modal === 'order-details' && selected && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-modal="true" className="modal-card procurement-modal-card procurement-detail-modal" role="dialog">
            <DetailHeader eyebrow="Purchase Order" onClose={closeModal} status={selected.status} title={selected.purchase_order_number} />
            {modalError && <div className="feedback-message error-message" role="alert" style={{ margin: '16px 24px' }}>{modalError}</div>}
            <DetailSummary values={[['Supplier', selected.supplier?.name || '—'], ['PO date', selected.po_date], ['Expected delivery', selected.expected_delivery_date], ['Subtotal', `${selected.currency} ${formatMoney(selected.subtotal)}`], ['Total', `${selected.currency} ${formatMoney(selected.total_amount)}`]]} />
            <DetailItems items={selected.items || []} type="order" />
            <WorkflowPanel
              action={orderAction}
              busy={busy}
              extra={['sent_to_supplier', 'partially_received'].includes(selected.status) ? <button className="secondary-button" disabled={busy} onClick={() => openReceiptForm(selected)} type="button">Create GRN</button> : null}
              onAction={() => runAction('order', orderAction[0], `Purchase Order ${actionPastTense(orderAction[0])} successfully.`)}
              remarks={workflowRemarks}
              setRemarks={setWorkflowRemarks}
            />
            <HistoryList histories={selected.status_history || []} />
            <div className="modal-actions" style={{ padding: '16px 24px' }}>
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* RECEIPT DETAILS MODAL */}
      {modal === 'receipt-details' && selected && (
        <div
          className="modal-backdrop"
          onClick={(event) => { if (event.target === event.currentTarget) closeModal() }}
          role="presentation"
        >
          <div aria-modal="true" className="modal-card procurement-modal-card procurement-detail-modal" role="dialog">
            <DetailHeader eyebrow="Goods Receipt" onClose={closeModal} status={selected.status} title={selected.receipt_number} />
            {modalError && <div className="feedback-message error-message" role="alert" style={{ margin: '16px 24px' }}>{modalError}</div>}
            <DetailSummary values={[['Purchase Order', selected.purchase_order?.purchase_order_number || '—'], ['Supplier', selected.supplier?.name || '—'], ['Warehouse', selected.warehouse?.name || '—'], ['Receipt date', selected.receipt_date]]} />
            <DetailItems items={selected.items || []} type="receipt" />
            <WorkflowPanel
              action={receiptAction}
              busy={busy}
              onAction={() => runAction('receipt', receiptAction[0], `Goods Receipt ${actionPastTense(receiptAction[0])} successfully.`)}
              remarks={workflowRemarks}
              setRemarks={setWorkflowRemarks}
            />
            <HistoryList histories={selected.status_history || []} />
            <div className="modal-actions" style={{ padding: '16px 24px' }}>
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function DetailHeader({ eyebrow, title, status, onClose }) {
  return (
    <div className="modal-header">
      <div>
        <p className="eyebrow">{eyebrow}</p>
        <h2>{title}</h2>
        <span className={statusClass(status)}>{statusLabel(status)}</span>
      </div>
      <button aria-label={`Close ${eyebrow} details`} className="icon-button" onClick={onClose} type="button">×</button>
    </div>
  )
}

function DetailSummary({ values }) {
  return (
    <div className="order-detail-summary" style={{ margin: '20px 24px' }}>
      {values.map(([label, value]) => (
        <div key={label}>
          <span>{label}</span>
          <strong>{value || '—'}</strong>
        </div>
      ))}
    </div>
  )
}

function DetailItems({ items, type }) {
  return (
    <section className="order-detail-section" style={{ margin: '20px 24px' }}>
      <div className="order-section-heading">
        <div>
          <p className="eyebrow">Document lines</p>
          <h3>{type === 'requisition' ? 'Requested materials' : type === 'order' ? 'Supplier commitment' : 'Received quantities'}</h3>
        </div>
      </div>
      <div className="table-wrap">
        <table className="master-data-table">
          <thead>
            <tr>
              <th>Material</th>
              <th>Unit</th>
              <th>Quantity</th>
              {type === 'order' && (
                <>
                  <th>Unit price</th>
                  <th>Line total</th>
                  <th>Remaining</th>
                </>
              )}
              {type === 'receipt' && (
                <>
                  <th>Accepted</th>
                  <th>Rejected</th>
                </>
              )}
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id}>
                <td>{item.material?.name || item.material_id || '—'}</td>
                <td>{item.unit?.code || '—'}</td>
                <td>{formatNumber(type === 'receipt' ? item.received_quantity : item.quantity)}</td>
                {type === 'order' && (
                  <>
                    <td>{formatMoney(item.unit_price)}</td>
                    <td>{formatMoney(item.line_total)}</td>
                    <td>{formatNumber(item.remaining_quantity)}</td>
                  </>
                )}
                {type === 'receipt' && (
                  <>
                    <td>{formatNumber(item.accepted_quantity)}</td>
                    <td>{formatNumber(item.rejected_quantity)}</td>
                  </>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}

function WorkflowPanel({ remarks, setRemarks, busy, action, onAction, extra }) {
  return (
    <section className="order-detail-section" style={{ margin: '20px 24px' }}>
      <div className="order-section-heading">
        <div>
          <p className="eyebrow">Workflow control</p>
          <h3>Status transition</h3>
        </div>
      </div>
      <label className="form-field">
        <span>Remarks</span>
        <textarea onChange={(event) => setRemarks(event.target.value)} placeholder="Optional remarks" rows="2" value={remarks} />
      </label>
      <div className="workflow-actions" style={{ marginTop: '12px' }}>
        {extra}
        {action && (
          <button className="primary-button" disabled={busy} onClick={onAction} type="button">
            {busy ? 'Working…' : action[1]}
          </button>
        )}
      </div>
    </section>
  )
}

function HistoryList({ histories }) {
  return (
    <section className="order-detail-section" style={{ margin: '20px 24px' }}>
      <div className="order-section-heading">
        <div>
          <p className="eyebrow">Traceability</p>
          <h3>Status history</h3>
        </div>
      </div>
      <div className="history-list">
        {histories.length ? (
          histories.map((item) => (
            <div className="history-row" key={item.id}>
              <span>{item.created_at ? new Date(item.created_at).toLocaleString() : '—'}</span>
              <strong>{item.previous_status ? `${statusLabel(item.previous_status)} → ` : ''}{statusLabel(item.new_status)}</strong>
              <span>{item.changed_by?.name || '—'}</span>
              <span>{item.remarks || '—'}</span>
            </div>
          ))
        ) : (
          <div className="empty-state">No status history yet.</div>
        )}
      </div>
    </section>
  )
}

export default ProcurementPage
