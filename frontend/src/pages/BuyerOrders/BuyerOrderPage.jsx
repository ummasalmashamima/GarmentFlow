import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import buyerOrderService from '../../services/buyerOrderService'
import masterDataService from '../../services/masterDataService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }

const today = () => new Date().toISOString().slice(0, 10)

const defaultDeliveryDate = () => {
  const d = new Date()
  d.setDate(d.getDate() + 14)
  return d.toISOString().slice(0, 10)
}

const emptyItem = () => ({ product_id: '', product_variant_id: '', quantity: '', unit_price: '', remarks: '' })

const emptyForm = () => ({
  buyer_id: '',
  order_date: today(),
  delivery_date: defaultDeliveryDate(),
  remarks: '',
  items: [emptyItem()],
})

function errorMessage(error) {
  const response = error?.response?.data
  if (response?.errors && typeof response.errors === 'object') {
    const errorList = Object.values(response.errors).flat()
    if (errorList.length > 0) return errorList.join(' | ')
  }

  return response?.message || error?.message || 'Unable to complete the request. Please try again.'
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

function BuyerOrderPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(emptyPage)
  const [query, setQuery] = useState({
    search: '',
    status: '',
    buyer_id: '',
    order_date_from: '',
    order_date_to: '',
    delivery_date_from: '',
    delivery_date_to: '',
    page: 1,
    per_page: 10,
    sort: 'id',
    direction: 'desc',
  })
  const [catalog, setCatalog] = useState({ buyers: [], products: [], variants: [] })
  const [catalogLoading, setCatalogLoading] = useState(true)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
  const [notice, setNotice] = useState('')
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [modal, setModal] = useState(null)
  const [orderForm, setOrderForm] = useState(emptyForm)
  const [preview, setPreview] = useState(null)
  const [workflowRemarks, setWorkflowRemarks] = useState('')

  const loadOrders = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const response = await buyerOrderService.list(query)
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
      if (active) loadOrders()
    })

    return () => { active = false }
  }, [loadOrders])

  useEffect(() => {
    let active = true

    Promise.resolve().then(() => {
      if (!active) return
      setCatalogLoading(true)

      return Promise.all([
        masterDataService.options('buyers'),
        masterDataService.options('products'),
        masterDataService.options('product-variants'),
      ]).then(([buyers, products, variants]) => {
        if (active) {
          setCatalog({ buyers: buyers || [], products: products || [], variants: variants || [] })
        }
      }).catch((requestError) => {
        if (active) setError(errorMessage(requestError))
      }).finally(() => {
        if (active) setCatalogLoading(false)
      })
    })

    return () => { active = false }
  }, [])

  const records = page.data || []
  const meta = page.meta || emptyPage.meta

  const updateQuery = (changes) => setQuery((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  const toggleSort = (column) => setQuery((current) => ({
    ...current,
    sort: column,
    direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
    page: 1,
  }))

  const setFormValue = (name, value) => {
    setModalError('')
    setOrderForm((current) => ({ ...current, [name]: value }))
  }

  const setLineValue = (index, name, value) => {
    setModalError('')
    setOrderForm((current) => {
      const newItems = current.items.map((item, itemIndex) => {
        if (itemIndex !== index) return item

        const updated = { ...item, [name]: value }
        if (name === 'product_id') {
          const matchingVariants = catalog.variants.filter((v) => String(v.product_id) === String(value))
          updated.product_variant_id = matchingVariants.length === 1 ? String(matchingVariants[0].id) : ''
        }
        return updated
      })
      return { ...current, items: newItems }
    })
  }

  const openCreate = () => {
    setSelectedOrder(null)
    setOrderForm(emptyForm())
    setPreview(null)
    setWorkflowRemarks('')
    setError('')
    setModalError('')
    setNotice('')
    setModal('form')
  }

  const openDetails = async (order) => {
    setBusy(true)
    setError('')

    try {
      const detail = await buyerOrderService.get(order.id)
      setSelectedOrder(detail)
      setWorkflowRemarks('')
      setModal('details')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const openEdit = async (order) => {
    setBusy(true)
    setError('')
    setModalError('')

    try {
      const detail = order.items && order.items.length ? order : await buyerOrderService.get(order.id)
      if (detail.status !== 'draft') {
        setError('Only draft orders can be edited.')
        return
      }

      setSelectedOrder(detail)
      setOrderForm({
        buyer_id: String(detail.buyer_id || ''),
        order_date: detail.order_date || today(),
        delivery_date: detail.delivery_date || defaultDeliveryDate(),
        remarks: detail.remarks || '',
        items: detail.items && detail.items.length ? detail.items.map((item) => ({
          product_id: String(item.product_id || ''),
          product_variant_id: String(item.product_variant_id || ''),
          quantity: item.quantity !== undefined && item.quantity !== null ? String(item.quantity) : '',
          unit_price: item.unit_price !== undefined && item.unit_price !== null ? String(item.unit_price) : '',
          remarks: item.remarks || '',
        })) : [emptyItem()],
      })
      setPreview(null)
      setNotice('')
      setModal('form')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const closeModal = () => {
    setModal(null)
    setSelectedOrder(null)
    setPreview(null)
    setWorkflowRemarks('')
    setModalError('')
  }

  const addLine = () => {
    setOrderForm((current) => ({ ...current, items: [...current.items, emptyItem()] }))
  }

  const removeLine = (index) => {
    setOrderForm((current) => ({
      ...current,
      items: current.items.length <= 1 ? current.items : current.items.filter((_, itemIndex) => itemIndex !== index),
    }))
  }

  const normalizedItems = () => orderForm.items.map((item) => ({
    product_id: Number(item.product_id),
    product_variant_id: Number(item.product_variant_id),
    quantity: Number(item.quantity),
    unit_price: Number(item.unit_price),
    remarks: item.remarks ? item.remarks.trim() : null,
  }))

  const liveTotalQuantity = orderForm.items.reduce((sum, it) => sum + (Number(it.quantity) || 0), 0)
  const liveTotalAmount = orderForm.items.reduce((sum, it) => sum + ((Number(it.quantity) || 0) * (Number(it.unit_price) || 0)), 0)

  const previewTotals = async (event) => {
    event?.preventDefault?.()
    setModalError('')

    const incomplete = orderForm.items.some(
      (it) => !it.product_id || !it.product_variant_id || !(Number(it.quantity) > 0) || Number(it.unit_price) < 0 || it.unit_price === '',
    )
    if (incomplete) {
      setModalError('Please ensure each line item has a selected Product, Variant, Quantity (> 0), and Unit Price (>= 0) before calculating backend preview.')
      return
    }

    setBusy(true)
    try {
      const res = await buyerOrderService.preview(normalizedItems())
      setPreview(res)
    } catch (requestError) {
      setModalError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const submitForm = async (event) => {
    event.preventDefault()
    setModalError('')
    setError('')
    setNotice('')

    if (!orderForm.buyer_id) {
      setModalError('Please select a Buyer.')
      return
    }

    if (!orderForm.order_date) {
      setModalError('Please specify an Order date.')
      return
    }

    if (!orderForm.delivery_date) {
      setModalError('Please specify a Delivery date.')
      return
    }

    if (orderForm.delivery_date < orderForm.order_date) {
      setModalError('Delivery date must be equal to or after the order date.')
      return
    }

    if (!orderForm.items.length) {
      setModalError('At least one order line is required.')
      return
    }

    for (let i = 0; i < orderForm.items.length; i++) {
      const item = orderForm.items[i]
      if (!item.product_id) {
        setModalError(`Line #${i + 1}: Please select a Product.`)
        return
      }
      if (!item.product_variant_id) {
        setModalError(`Line #${i + 1}: Please select a Product Variant.`)
        return
      }
      if (!(Number(item.quantity) > 0)) {
        setModalError(`Line #${i + 1}: Quantity must be greater than 0.`)
        return
      }
      if (Number(item.unit_price) < 0 || item.unit_price === '') {
        setModalError(`Line #${i + 1}: Unit price must be 0 or greater.`)
        return
      }
    }

    setBusy(true)
    try {
      const payload = {
        buyer_id: Number(orderForm.buyer_id),
        order_date: orderForm.order_date,
        delivery_date: orderForm.delivery_date,
        remarks: orderForm.remarks ? orderForm.remarks.trim() : null,
        items: normalizedItems(),
      }
      const detail = selectedOrder
        ? await buyerOrderService.update(selectedOrder.id, payload)
        : await buyerOrderService.create(payload)

      setSelectedOrder(detail)
      await loadOrders()
      setNotice(selectedOrder ? 'Buyer Order draft updated successfully.' : 'Buyer Order draft created successfully.')
      setModal('details')
      setPreview(null)
      setModalError('')
    } catch (requestError) {
      setModalError(errorMessage(requestError))
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
      let result
      if (action === 'transition') {
        result = await buyerOrderService.transition(selectedOrder.id, status, workflowRemarks || null)
      } else {
        result = await buyerOrderService[action](selectedOrder.id, workflowRemarks || null)
      }
      setSelectedOrder(result)
      await loadOrders()
      setWorkflowRemarks('')
      setNotice(successMessage)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const removeOrder = async (order = selectedOrder) => {
    if (!order || order.status !== 'draft' || !window.confirm(`Delete ${order.order_number}?`)) return
    setBusy(true)
    setError('')
    setNotice('')

    try {
      await buyerOrderService.remove(order.id)
      closeModal()
      await loadOrders()
      setNotice('Buyer Order deleted successfully.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const workflowAction = () => {
    if (!selectedOrder) return null
    if (selectedOrder.status === 'draft') return { action: 'submit', label: 'Submit for approval', message: 'Buyer Order submitted for approval.' }
    if (selectedOrder.status === 'pending_approval') return { action: 'approve', label: 'Approve order', message: 'Buyer Order approved and ready for confirmation.' }
    if (selectedOrder.status === 'submitted') return { action: 'confirm', label: 'Confirm order', message: 'Buyer Order confirmed for planning.' }
    const nextStatus = {
      confirmed: 'planning',
      planning: 'in_production',
      in_production: 'ready',
      ready: 'shipped',
      shipped: 'delivered',
      delivered: 'completed',
    }[selectedOrder.status]
    return nextStatus ? { action: 'transition', status: nextStatus, label: `Move to ${statusLabel(nextStatus)}`, message: `Buyer Order moved to ${statusLabel(nextStatus)}.` } : null
  }

  const action = workflowAction()
  const metaSortLabel = (column, label) => `${label}${query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}`

  return (
    <div className="master-data-page buyer-orders-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 4 · Buyer order management</p>
          <h1>Buyer Orders</h1>
          <p>Capture buyer demand, route approvals, and prepare confirmed orders for future planning.</p>
        </div>
        <button className="primary-button" onClick={openCreate} type="button">Create Order</button>
      </div>

      <div className="master-data-toolbar buyer-order-toolbar">
        <label className="search-field">
          <span>Search</span>
          <input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search order number or buyer" value={query.search} />
        </label>
        <label className="filter-field">
          <span>Status</span>
          <select onChange={(event) => updateQuery({ status: event.target.value })} value={query.status}>
            <option value="">All statuses</option>
            {['draft', 'submitted', 'pending_approval', 'confirmed', 'planning', 'in_production', 'ready', 'shipped', 'delivered', 'completed'].map((status) => (
              <option key={status} value={status}>{statusLabel(status)}</option>
            ))}
          </select>
        </label>
        <label className="filter-field">
          <span>Buyer</span>
          <select onChange={(event) => updateQuery({ buyer_id: event.target.value })} value={query.buyer_id}>
            <option value="">All buyers</option>
            {catalog.buyers.map((buyer) => <option key={buyer.id} value={buyer.id}>{buyer.code} · {buyer.name}</option>)}
          </select>
        </label>
        <span className="record-count">{meta.total || 0} orders</span>
      </div>

      <div className="buyer-order-date-filters">
        <label className="filter-field"><span>Order from</span><input onChange={(event) => updateQuery({ order_date_from: event.target.value })} type="date" value={query.order_date_from} /></label>
        <label className="filter-field"><span>Order to</span><input onChange={(event) => updateQuery({ order_date_to: event.target.value })} type="date" value={query.order_date_to} /></label>
        <label className="filter-field"><span>Delivery from</span><input onChange={(event) => updateQuery({ delivery_date_from: event.target.value })} type="date" value={query.delivery_date_from} /></label>
        <label className="filter-field"><span>Delivery to</span><input onChange={(event) => updateQuery({ delivery_date_to: event.target.value })} type="date" value={query.delivery_date_to} /></label>
      </div>

      {error && <div className="feedback-message error-message" role="alert">{error}</div>}
      {notice && <div className="feedback-message success-message" role="status">{notice}</div>}

      <section aria-busy={loading} className="data-card">
        <div className="data-card-header">
          <div><p className="eyebrow">Commercial register</p><h2>Buyer Order register</h2></div>
          <span className="data-card-hint">Open an order to manage its workflow and history</span>
        </div>

        {loading ? <div className="empty-state">Loading Buyer Orders…</div> : records.length === 0 ? <div className="empty-state">No Buyer Orders match the current filters.</div> : (
          <div className="table-wrap">
            <table className="master-data-table buyer-order-table">
              <thead><tr>
                {['order_number', 'order_date', 'delivery_date', 'total_quantity', 'total_amount', 'status'].map((column) => (
                  <th key={column}>
                    <button onClick={() => toggleSort(column)} type="button">
                      {metaSortLabel(column, column === 'order_number' ? 'Order number' : column.replaceAll('_', ' '))}
                    </button>
                  </th>
                ))}
                <th>Buyer</th><th>Created by</th><th><span className="sr-only">Actions</span></th>
              </tr></thead>
              <tbody>{records.map((order) => (
                <tr key={order.id} onClick={() => openDetails(order)}>
                  <td><strong>{order.order_number}</strong></td>
                  <td>{order.order_date || '—'}</td>
                  <td>{order.delivery_date || '—'}</td>
                  <td>{formatNumber(order.total_quantity)}</td>
                  <td>{formatMoney(order.total_amount)}</td>
                  <td><span className={statusClass(order.status)}>{statusLabel(order.status)}</span></td>
                  <td>{order.buyer?.name || '—'}</td>
                  <td>{order.creator?.name || '—'}</td>
                  <td className="table-actions" onClick={(event) => event.stopPropagation()}>
                    {order.status === 'draft' && <button className="text-button" onClick={() => openEdit(order)} type="button">Edit</button>}
                    <button className="text-button" onClick={() => openDetails(order)} type="button">Open</button>
                    {order.status === 'draft' && <button className="text-button danger-text" onClick={() => removeOrder(order)} type="button">Delete</button>}
                  </td>
                </tr>
              ))}</tbody>
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

      {/* CREATE & EDIT FORM MODAL */}
      {modal === 'form' && (
        <div className="modal-backdrop" onClick={(event) => { if (event.target === event.currentTarget) closeModal() }} role="presentation">
          <div aria-labelledby="buyer-order-form-title" aria-modal="true" className="modal-card order-modal-card" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Buyer Order draft</p>
                <h2 id="buyer-order-form-title">{selectedOrder ? `Edit draft ${selectedOrder.order_number}` : 'Create Buyer Order'}</h2>
              </div>
              <button aria-label="Close Buyer Order form" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            {catalogLoading && <div className="inline-preview" role="status">Loading master data catalog options…</div>}
            {modalError && <div className="feedback-message error-message" role="alert" style={{ marginBottom: '16px' }}>{modalError}</div>}

            <form className="master-data-form" onSubmit={submitForm}>
              <div className="form-grid">
                <label className="form-field">
                  <span>Buyer *</span>
                  <select
                    disabled={catalogLoading}
                    name="buyer_id"
                    onChange={(event) => setFormValue('buyer_id', event.target.value)}
                    required
                    value={orderForm.buyer_id}
                  >
                    <option value="">{catalogLoading ? 'Loading buyers…' : 'Select buyer'}</option>
                    {catalog.buyers.map((buyer) => (
                      <option key={buyer.id} value={buyer.id}>{buyer.code} · {buyer.name}</option>
                    ))}
                  </select>
                </label>

                <label className="form-field">
                  <span>Order date *</span>
                  <input
                    name="order_date"
                    onChange={(event) => setFormValue('order_date', event.target.value)}
                    required
                    type="date"
                    value={orderForm.order_date}
                  />
                </label>

                <label className="form-field">
                  <span>Delivery date *</span>
                  <input
                    min={orderForm.order_date || today()}
                    name="delivery_date"
                    onChange={(event) => setFormValue('delivery_date', event.target.value)}
                    required
                    type="date"
                    value={orderForm.delivery_date}
                  />
                </label>

                <label className="form-field full-width">
                  <span>Remarks</span>
                  <textarea
                    name="remarks"
                    onChange={(event) => setFormValue('remarks', event.target.value)}
                    placeholder="Optional order notes or instructions"
                    rows="2"
                    value={orderForm.remarks}
                  />
                </label>
              </div>

              <div className="order-lines-heading">
                <div>
                  <p className="eyebrow">Order lines</p>
                  <h3>Products, variants & pricing</h3>
                </div>
                <button className="secondary-button" onClick={addLine} type="button">+ Add line</button>
              </div>

              <div className="order-edit-lines">
                {orderForm.items.map((item, index) => {
                  const productVariants = catalog.variants.filter((variant) => String(variant.product_id) === String(item.product_id))
                  const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)

                  return (
                    <div className="buyer-order-item-card" key={`line-${index}`}>
                      <div className="buyer-order-item-header">
                        <div className="buyer-order-item-badge">
                          <span>Line #{index + 1}</span>
                        </div>
                        <button
                          aria-label={`Remove line ${index + 1}`}
                          className="icon-button danger-text"
                          disabled={orderForm.items.length <= 1}
                          onClick={() => removeLine(index)}
                          title="Remove line"
                          type="button"
                        >
                          ×
                        </button>
                      </div>

                      <div className="buyer-order-item-row-top">
                        <label className="form-field">
                          <span>Product *</span>
                          <select
                            onChange={(event) => setLineValue(index, 'product_id', event.target.value)}
                            required
                            value={item.product_id}
                          >
                            <option value="">{catalogLoading ? 'Loading products…' : 'Select product'}</option>
                            {catalog.products.map((product) => (
                              <option key={product.id} value={product.id}>{product.code} · {product.name}</option>
                            ))}
                          </select>
                        </label>

                        <label className="form-field">
                          <span>Variant *</span>
                          <select
                            onChange={(event) => setLineValue(index, 'product_variant_id', event.target.value)}
                            required
                            value={item.product_variant_id}
                          >
                            <option value="">
                              {!item.product_id ? 'Select product first' : productVariants.length === 0 ? 'No variants available' : 'Select variant'}
                            </option>
                            {productVariants.map((variant) => (
                              <option key={variant.id} value={variant.id}>
                                {variant.code || variant.sku}{variant.name || variant.variant_name ? ` · ${variant.name || variant.variant_name}` : ''}
                              </option>
                            ))}
                          </select>
                        </label>
                      </div>

                      <div className="buyer-order-item-row-bottom">
                        <label className="form-field">
                          <span>Quantity *</span>
                          <input
                            min="0.0001"
                            onChange={(event) => setLineValue(index, 'quantity', event.target.value)}
                            placeholder="e.g. 100"
                            required
                            step="any"
                            type="number"
                            value={item.quantity}
                          />
                        </label>

                        <label className="form-field">
                          <span>Unit price *</span>
                          <input
                            min="0"
                            onChange={(event) => setLineValue(index, 'unit_price', event.target.value)}
                            placeholder="e.g. 25.50"
                            required
                            step="any"
                            type="number"
                            value={item.unit_price}
                          />
                        </label>

                        <div className="buyer-order-line-total-box">
                          <span>Line Total</span>
                          <strong>{formatMoney(lineTotal)}</strong>
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>

              <div className="order-preview-bar">
                <div className="preview-stats">
                  <div className="preview-stat">
                    <span className="stat-label">Total Quantity</span>
                    <strong className="stat-value">{preview ? formatNumber(preview.total_quantity) : formatNumber(liveTotalQuantity)}</strong>
                  </div>
                  <div className="preview-stat">
                    <span className="stat-label">Total Amount</span>
                    <strong className="stat-value">{preview ? formatMoney(preview.total_amount) : formatMoney(liveTotalAmount)}</strong>
                  </div>
                </div>
                <button className="secondary-button" disabled={busy || catalogLoading} onClick={previewTotals} type="button">
                  {busy ? 'Calculating…' : 'Preview server total'}
                </button>
              </div>

              {preview && (
                <div className="inline-preview" role="status">
                  ✓ Backend verified: {formatNumber(preview.total_quantity)} units for {formatMoney(preview.total_amount)} total.
                </div>
              )}

              <div className="modal-actions">
                <button className="secondary-button" onClick={closeModal} type="button">Cancel</button>
                <button className="primary-button" disabled={busy || catalogLoading} type="submit">
                  {busy ? 'Saving…' : selectedOrder ? 'Save changes' : 'Create order draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DETAILS & WORKFLOW MODAL */}
      {modal === 'details' && selectedOrder && (
        <div className="modal-backdrop" onClick={(event) => { if (event.target === event.currentTarget) closeModal() }} role="presentation">
          <div aria-labelledby="buyer-order-detail-title" aria-modal="true" className="modal-card order-modal-card order-detail-modal" role="dialog">
            <div className="modal-header">
              <div>
                <p className="eyebrow">Buyer Order detail</p>
                <h2 id="buyer-order-detail-title">{selectedOrder.order_number}</h2>
                <p>{selectedOrder.buyer?.name || 'Buyer'} · ordered {selectedOrder.order_date}</p>
              </div>
              <button aria-label="Close Buyer Order details" className="icon-button" onClick={closeModal} type="button">×</button>
            </div>

            <div className="order-detail-summary">
              <div><span>Buyer</span><strong>{selectedOrder.buyer?.name || '—'}</strong></div>
              <div><span>Delivery date</span><strong>{selectedOrder.delivery_date || '—'}</strong></div>
              <div><span>Status</span><strong><span className={statusClass(selectedOrder.status)}>{statusLabel(selectedOrder.status)}</span></strong></div>
              <div><span>Created by</span><strong>{selectedOrder.creator?.name || '—'}</strong></div>
              <div><span>Total quantity</span><strong>{formatNumber(selectedOrder.total_quantity)}</strong></div>
              <div><span>Total amount</span><strong>{formatMoney(selectedOrder.total_amount)}</strong></div>
            </div>

            {error && <div className="feedback-message error-message" role="alert">{error}</div>}
            {notice && <div className="feedback-message success-message" role="status">{notice}</div>}

            <section className="order-detail-section">
              <div className="order-section-heading">
                <div><p className="eyebrow">Order items</p><h3>Products and variants</h3></div>
                {selectedOrder.status === 'draft' && (
                  <button className="secondary-button" onClick={() => openEdit(selectedOrder)} type="button">Edit draft</button>
                )}
              </div>
              <div className="table-wrap">
                <table className="master-data-table">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Variant</th>
                      <th>Quantity</th>
                      <th>Unit price</th>
                      <th>Item total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(selectedOrder.items || []).map((item) => (
                      <tr key={item.id}>
                        <td>{item.product?.code ? `${item.product.code} · ${item.product.name}` : item.product?.name || '—'}</td>
                        <td>{item.product_variant?.sku || item.product_variant?.variant_name || '—'}</td>
                        <td>{formatNumber(item.quantity)}</td>
                        <td>{formatMoney(item.unit_price)}</td>
                        <td>{formatMoney(item.item_total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section className="order-detail-section">
              <div className="order-section-heading">
                <div><p className="eyebrow">Workflow control</p><h3>Status management</h3></div>
                {selectedOrder.status === 'draft' && (
                  <button className="text-button danger-text" onClick={() => removeOrder(selectedOrder)} type="button">Delete draft</button>
                )}
              </div>
              <label className="form-field">
                <span>Workflow remarks</span>
                <textarea
                  onChange={(event) => setWorkflowRemarks(event.target.value)}
                  placeholder="Optional remarks for this action"
                  rows="2"
                  value={workflowRemarks}
                />
              </label>
              <div className="workflow-actions">
                {selectedOrder.status === 'pending_approval' && (
                  <>
                    <button className="secondary-button" disabled={busy} onClick={() => runAction('reject', 'Buyer Order rejected and returned to draft.')} type="button">Reject</button>
                    <button className="primary-button" disabled={busy} onClick={() => runAction('approve', 'Buyer Order approved and ready for confirmation.')} type="button">Approve</button>
                  </>
                )}
                {action && action.action !== 'approve' && action.action !== 'reject' && (
                  <button className="primary-button" disabled={busy} onClick={() => runAction(action.action, action.message, action.status)} type="button">
                    {action.label}
                  </button>
                )}
                {selectedOrder.status === 'completed' && <span className="completed-note">Completed orders are read-only.</span>}
              </div>
            </section>

            {selectedOrder.latest_approval && (
              <section className="order-detail-section">
                <div className="order-section-heading">
                  <div><p className="eyebrow">Approval</p><h3>Latest approval decision</h3></div>
                </div>
                <div className="approval-card">
                  <span className={statusClass(selectedOrder.latest_approval.status)}>{statusLabel(selectedOrder.latest_approval.status)}</span>
                  <span>{selectedOrder.latest_approval.reviewer?.name ? `Reviewed by ${selectedOrder.latest_approval.reviewer.name}` : 'Awaiting reviewer'}</span>
                  <span>{selectedOrder.latest_approval.remarks || 'No remarks recorded.'}</span>
                </div>
              </section>
            )}

            {selectedOrder.planning_input && (
              <section className="order-detail-section">
                <div className="order-section-heading">
                  <div><p className="eyebrow">Planning handoff</p><h3>Prepared input</h3></div>
                </div>
                <div className="planning-input-card">
                  <strong>{statusLabel(selectedOrder.planning_input.status)}</strong>
                  <span>{formatNumber(selectedOrder.planning_input.total_quantity)} units ready for later planning</span>
                  <span>Prepared by {selectedOrder.planning_input.prepared_by?.name || '—'}</span>
                </div>
              </section>
            )}

            <section className="order-detail-section">
              <div className="order-section-heading">
                <div><p className="eyebrow">Order history</p><h3>Status transitions</h3></div>
              </div>
              <div className="history-list">
                {(selectedOrder.status_history || []).map((history) => (
                  <div className="history-row" key={history.id}>
                    <span>{history.created_at ? new Date(history.created_at).toLocaleString() : '—'}</span>
                    <strong>{history.previous_status ? `${statusLabel(history.previous_status)} → ` : ''}{statusLabel(history.new_status)}</strong>
                    <span>{history.changed_by?.name || 'System'}</span>
                    <span>{history.remarks || '—'}</span>
                  </div>
                ))}
              </div>
            </section>

            <div className="modal-actions">
              <button className="secondary-button" onClick={closeModal} type="button">Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default BuyerOrderPage
