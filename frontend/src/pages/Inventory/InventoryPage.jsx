import { useCallback, useEffect, useState } from 'react'
import masterDataService from '../../services/masterDataService'
import inventoryService from '../../services/inventoryService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const emptyMovement = () => ({ warehouse_id: '', warehouse_location_id: '', item_type: 'material', item_id: '', unit_id: '', quantity: '', remarks: '' })
const emptyTransfer = () => ({ source_warehouse_id: '', source_location_id: '', destination_warehouse_id: '', destination_location_id: '', quantity: '', item_type: 'material', item_id: '', unit_id: '', transfer_date: today(), remarks: '' })
const emptyAdjustment = () => ({ direction: 'IN', warehouse_id: '', warehouse_location_id: '', quantity: '', item_type: 'material', item_id: '', unit_id: '', adjustment_date: today(), reason: '', remarks: '' })

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]
  return firstValidationError || response?.message || error.message || 'Unable to complete the request. Please try again.'
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

function statusLabel(value) {
  return (value || 'unknown').replaceAll('_', ' ')
}

function itemLabel(record) {
  if (record?.material) return `${record.material.code} · ${record.material.name}`
  if (record?.product_variant) return `${record.product_variant.sku} · ${record.product_variant.variant_name || 'Variant'}`
  if (record?.product) return `${record.product.code} · ${record.product.name}`
  if (record?.material_id) return `Material #${record.material_id}`
  if (record?.product_variant_id) return `Variant #${record.product_variant_id}`
  if (record?.product_id) return `Product #${record.product_id}`
  return 'Unidentified item'
}

function Pagination({ meta, onPage }) {
  return <div className="pagination-bar"><span>{meta.total || 0} records · page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={(meta.current_page || 1) <= 1} onClick={() => onPage((meta.current_page || 1) - 1)} type="button">Previous</button><button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1)} onClick={() => onPage((meta.current_page || 1) + 1)} type="button">Next</button></div></div>
}

function ItemFields({ value, catalog, onChange, includeQuantity = true }) {
  const options = value.item_type === 'material' ? catalog.materials : value.item_type === 'product' ? catalog.products : catalog.variants
  const selected = options.find((item) => String(item.id) === String(value.item_id))
  const parentProduct = value.item_type === 'product_variant' ? catalog.products.find((item) => String(item.id) === String(selected?.product_id)) : null
  const update = (name, next) => onChange({ ...value, [name]: next })
  const selectItem = (next) => {
    const nextSelected = options.find((item) => String(item.id) === String(next))
    const nextParentProduct = value.item_type === 'product_variant' ? catalog.products.find((item) => String(item.id) === String(nextSelected?.product_id)) : null
    onChange({ ...value, item_id: next, unit_id: nextSelected?.unit_id || nextParentProduct?.unit_id || value.unit_id })
  }
  return <>
    <label className="form-field"><span>Item type</span><select onChange={(event) => onChange({ ...value, item_type: event.target.value, item_id: '', unit_id: '' })} value={value.item_type}><option value="material">Raw material</option><option value="product">Product</option><option value="product_variant">Product variant</option></select></label>
    <label className="form-field"><span>Item</span><select onChange={(event) => selectItem(event.target.value)} required value={value.item_id}><option value="">Select item</option>{options.map((item) => <option key={item.id} value={item.id}>{item.code || item.sku} · {item.name || item.variant_name || item.product?.name}</option>)}</select></label>
    <label className="form-field"><span>Unit</span><select onChange={(event) => update('unit_id', event.target.value)} required value={value.unit_id}><option value="">Select unit</option>{catalog.units.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}{item.id === selected?.unit_id || item.id === parentProduct?.unit_id ? ' · item unit' : ''}</option>)}</select></label>
    {includeQuantity && <label className="form-field"><span>Quantity</span><input min="0.0001" onChange={(event) => update('quantity', event.target.value)} required step="0.0001" type="number" value={value.quantity} /></label>}
  </>
}

function InventoryPage() {
  const [tab, setTab] = useState('balances')
  const [pages, setPages] = useState({ balances: emptyPage, history: emptyPage, transfers: emptyPage, adjustments: emptyPage })
  const [summary, setSummary] = useState({ balance_count: 0, quantity_on_hand: 0, quantity_reserved: 0, quantity_available: 0 })
  const [queries, setQueries] = useState({
    balances: { search: '', warehouse_id: '', warehouse_location_id: '', item_type: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' },
    history: { search: '', warehouse_id: '', transaction_type: '', transaction_date_from: '', transaction_date_to: '', page: 1, per_page: 15, sort: 'id', direction: 'desc' },
    transfers: { search: '', source_warehouse_id: '', destination_warehouse_id: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' },
    adjustments: { search: '', adjustment_direction: '', warehouse_id: '', page: 1, per_page: 10, sort: 'id', direction_sort: 'desc' },
  })
  const [catalog, setCatalog] = useState({ materials: [], products: [], variants: [], units: [], warehouses: [], locations: [] })
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [modal, setModal] = useState(null)
  const [selected, setSelected] = useState(null)
  const [form, setForm] = useState(emptyMovement)

  const query = queries[tab]
  const records = pages[tab]?.data || []
  const meta = pages[tab]?.meta || emptyPage.meta
  const locationsFor = (warehouseId) => catalog.locations.filter((location) => !warehouseId || String(location.warehouse_id) === String(warehouseId))

  const loadList = useCallback(async () => {
    setLoading(true); setError('')
    try {
      const response = tab === 'balances' ? await inventoryService.balances.list(queries.balances) : tab === 'history' ? await inventoryService.movements.history(queries.history) : tab === 'transfers' ? await inventoryService.transfers.list(queries.transfers) : await inventoryService.adjustments.list(queries.adjustments)
      setPages((current) => ({ ...current, [tab]: response }))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setLoading(false) }
  }, [queries, tab])

  useEffect(() => { Promise.resolve().then(loadList) }, [loadList])
  useEffect(() => {
    let active = true
    Promise.all([
      masterDataService.options('materials'),
      masterDataService.options('products'),
      masterDataService.options('product-variants'),
      masterDataService.options('units'),
      masterDataService.options('warehouses'),
      masterDataService.options('warehouse-locations'),
    ]).then(([materials, products, variants, units, warehouses, locations]) => {
      if (active) setCatalog({ materials, products, variants, units, warehouses, locations })
    }).catch((requestError) => { if (active) setError(errorMessage(requestError)) })
    return () => { active = false }
  }, [])
  useEffect(() => {
    inventoryService.balances.summary(queries.balances).then(setSummary).catch(() => {})
  }, [queries.balances])

  const updateQuery = (changes) => setQueries((current) => ({ ...current, [tab]: { ...current[tab], ...changes, page: changes.page ?? 1 } }))
  const toggleSort = (column) => setQueries((current) => ({ ...current, [tab]: { ...current[tab], sort: column, direction: current[tab].sort === column && current[tab].direction === 'asc' ? 'desc' : 'asc', page: 1 } }))
  const sortLabel = (column, label) => `${label}${query.sort === column ? ` ${query.direction === 'asc' ? '↑' : '↓'}` : ''}`
  const closeModal = () => { setModal(null); setSelected(null); setForm(emptyMovement()); setError('') }
  const refresh = async () => {
    await loadList()
    try {
      setSummary(await inventoryService.balances.summary(queries.balances))
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  const openMovement = (direction) => { setForm(emptyMovement()); setModal(direction); setError(''); setNotice('') }
  const openTransfer = () => { setForm(emptyTransfer()); setModal('transfer'); setError(''); setNotice('') }
  const openAdjustment = () => { setForm(emptyAdjustment()); setModal('adjustment'); setError(''); setNotice('') }
  const openBalance = async (record) => { setBusy(true); setError(''); try { setSelected(await inventoryService.balances.get(record.id)); setModal('balance-detail') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) } }
  const openTransferDetail = async (record) => { setBusy(true); setError(''); try { setSelected(await inventoryService.transfers.get(record.id)); setModal('transfer-detail') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) } }
  const openAdjustmentDetail = async (record) => { setBusy(true); setError(''); try { setSelected(await inventoryService.adjustments.get(record.id)); setModal('adjustment-detail') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) } }

  const itemPayload = (value) => ({
    ...(value.item_type === 'material' ? { material_id: Number(value.item_id) } : value.item_type === 'product' ? { product_id: Number(value.item_id) } : { product_variant_id: Number(value.item_id) }),
    unit_id: Number(value.unit_id),
    quantity: Number(value.quantity),
  })

  const submitMovement = async (event, direction) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { ...itemPayload(form), warehouse_id: Number(form.warehouse_id), warehouse_location_id: form.warehouse_location_id ? Number(form.warehouse_location_id) : null, remarks: form.remarks || null }
      await (direction === 'stock-in' ? inventoryService.movements.stockIn(payload) : inventoryService.movements.stockOut(payload))
      closeModal(); setNotice(`${direction === 'stock-in' ? 'Stock in' : 'Stock out'} posted successfully.`); await refresh()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const submitTransfer = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { source_warehouse_id: Number(form.source_warehouse_id), source_location_id: form.source_location_id ? Number(form.source_location_id) : null, destination_warehouse_id: Number(form.destination_warehouse_id), destination_location_id: form.destination_location_id ? Number(form.destination_location_id) : null, transfer_date: form.transfer_date, remarks: form.remarks || null, items: [itemPayload(form)] }
      await inventoryService.transfers.create(payload); closeModal(); setNotice('Atomic stock transfer posted successfully.'); setTab('transfers'); await loadList()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const submitAdjustment = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const payload = { direction: form.direction, warehouse_id: Number(form.warehouse_id), warehouse_location_id: form.warehouse_location_id ? Number(form.warehouse_location_id) : null, adjustment_date: form.adjustment_date, reason: form.reason, remarks: form.remarks || null, items: [itemPayload(form)] }
      await inventoryService.adjustments.create(payload); closeModal(); setNotice('Authorized stock adjustment posted successfully.'); setTab('adjustments'); await loadList()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const renderFilters = () => {
    if (tab === 'balances') return <><label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value, warehouse_location_id: '' })} value={query.warehouse_id}><option value="">All warehouses</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="filter-field"><span>Location</span><select onChange={(event) => updateQuery({ warehouse_location_id: event.target.value })} value={query.warehouse_location_id}><option value="">All locations</option>{locationsFor(query.warehouse_id).map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="filter-field"><span>Item type</span><select onChange={(event) => updateQuery({ item_type: event.target.value })} value={query.item_type}><option value="">All items</option><option value="material">Materials</option><option value="product">Products</option><option value="product_variant">Variants</option></select></label></>
    if (tab === 'history') return <><label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}><option value="">All warehouses</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="filter-field"><span>Movement</span><select onChange={(event) => updateQuery({ transaction_type: event.target.value })} value={query.transaction_type}><option value="">All movement types</option>{['STOCK_IN', 'STOCK_OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'].map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}</select></label><label className="filter-field"><span>From</span><input onChange={(event) => updateQuery({ transaction_date_from: event.target.value })} type="date" value={query.transaction_date_from} /></label><label className="filter-field"><span>To</span><input onChange={(event) => updateQuery({ transaction_date_to: `${event.target.value} 23:59:59` })} type="date" value={query.transaction_date_to?.slice(0, 10) || ''} /></label></>
    if (tab === 'transfers') return <><label className="filter-field"><span>Source</span><select onChange={(event) => updateQuery({ source_warehouse_id: event.target.value })} value={query.source_warehouse_id}><option value="">All sources</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="filter-field"><span>Destination</span><select onChange={(event) => updateQuery({ destination_warehouse_id: event.target.value })} value={query.destination_warehouse_id}><option value="">All destinations</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label></>
    return <><label className="filter-field"><span>Direction</span><select onChange={(event) => updateQuery({ adjustment_direction: event.target.value })} value={query.adjustment_direction}><option value="">All directions</option><option value="IN">Inbound</option><option value="OUT">Outbound</option></select></label><label className="filter-field"><span>Warehouse</span><select onChange={(event) => updateQuery({ warehouse_id: event.target.value })} value={query.warehouse_id}><option value="">All warehouses</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label></>
  }

  const renderTable = () => {
    if (tab === 'balances') return <table className="master-data-table inventory-table"><thead><tr><th><button onClick={() => toggleSort('stock_key')} type="button">{sortLabel('stock_key', 'Stock key')}</button></th><th>Warehouse / location</th><th>Item</th><th><button onClick={() => toggleSort('quantity_on_hand')} type="button">{sortLabel('quantity_on_hand', 'On hand')}</button></th><th>Reserved</th><th>Available</th></tr></thead><tbody>{records.map((record) => <tr key={record.id} onClick={() => openBalance(record)}><td><strong>{record.stock_key}</strong><small>{statusLabel(record.status)}</small></td><td>{record.warehouse?.code || record.warehouse_id}<small>{record.warehouse_location?.code || 'Warehouse total'}</small></td><td>{itemLabel(record)}<small>{record.unit?.code || `Unit #${record.unit_id}`}</small></td><td>{formatNumber(record.quantity_on_hand)}</td><td>{formatNumber(record.quantity_reserved)}</td><td><strong className="inventory-available">{formatNumber(record.quantity_available)}</strong></td></tr>)}</tbody></table>
    if (tab === 'history') return <table className="master-data-table inventory-table"><thead><tr><th>Transaction</th><th>Movement</th><th>Item</th><th>Warehouse</th><th>Quantity</th><th>Date</th></tr></thead><tbody>{records.map((record) => <tr key={record.id}><td><strong>{record.transaction_number}</strong><small>{record.reference_type ? `${record.reference_type} #${record.reference_id}` : 'Manual movement'}</small></td><td><span className={`status-pill inventory-${record.transaction_type?.toLowerCase()}`}>{statusLabel(record.transaction_type)}</span></td><td>{itemLabel(record)}</td><td>{record.warehouse?.code || record.warehouse_id}<small>{record.warehouse_location?.code || 'Warehouse total'}</small></td><td>{formatNumber(record.quantity)} {record.unit?.code || ''}</td><td>{formatDate(record.transaction_date)}</td></tr>)}</tbody></table>
    if (tab === 'transfers') return <table className="master-data-table inventory-table"><thead><tr><th>Transfer</th><th>Route</th><th>Status</th><th>Transferred by</th><th>Date</th></tr></thead><tbody>{records.map((record) => <tr key={record.id} onClick={() => openTransferDetail(record)}><td><strong>{record.transfer_number}</strong><small>{record.items_count || 'View lines'}</small></td><td>{record.source_warehouse?.code || record.source_warehouse_id} → {record.destination_warehouse?.code || record.destination_warehouse_id}<small>{record.source_location?.code || 'Warehouse'} → {record.destination_location?.code || 'Warehouse'}</small></td><td><span className="status-pill active">{statusLabel(record.status)}</span></td><td>{record.transferor?.name || `User #${record.transferred_by}`}</td><td>{formatDate(record.transfer_date)}</td></tr>)}</tbody></table>
    return <table className="master-data-table inventory-table"><thead><tr><th>Adjustment</th><th>Direction</th><th>Warehouse</th><th>Reason</th><th>Adjusted by</th><th>Date</th></tr></thead><tbody>{records.map((record) => <tr key={record.id} onClick={() => openAdjustmentDetail(record)}><td><strong>{record.adjustment_number}</strong></td><td><span className={`status-pill ${record.direction === 'IN' ? 'active' : 'inactive'}`}>{record.direction}</span></td><td>{record.warehouse?.code || record.warehouse_id}<small>{record.warehouse_location?.code || 'Warehouse total'}</small></td><td>{record.reason}</td><td>{record.adjuster?.name || `User #${record.adjusted_by}`}</td><td>{formatDate(record.adjustment_date)}</td></tr>)}</tbody></table>
  }

  const detailTitle = modal === 'balance-detail' ? 'Stock detail' : modal === 'transfer-detail' ? 'Transfer detail' : 'Adjustment detail'
  const locations = locationsFor(form.warehouse_id)
  const sourceLocations = locationsFor(form.source_warehouse_id)
  const destinationLocations = locationsFor(form.destination_warehouse_id)

  return <div className="master-data-page inventory-page">
    <div className="page-intro master-data-intro">
      <div>
        <p className="eyebrow">Phase 7 · Inventory & Warehouse Management</p>
        <h1>Inventory Control Center</h1>
        <p>Control warehouse stock through auditable movements, atomic transfers, accepted Goods Receipt integration, and reasoned adjustments.</p>
      </div>
      <div className="inventory-header-actions">
        <button className="primary-button" onClick={() => openMovement('stock-in')} type="button">Stock In</button>
        <button className="secondary-button" onClick={() => openMovement('stock-out')} type="button">Stock Out</button>
        <button className="secondary-button" onClick={openTransfer} type="button">Transfer</button>
        <button className="secondary-button" onClick={openAdjustment} type="button">Adjust</button>
      </div>
    </div>

    <div className="summary-grid inventory-summary-grid">
      <div className="summary-card inventory-summary-card">
        <span>Controlled Balances</span>
        <strong>{formatNumber(summary.balance_count)}</strong>
        <small>Active warehouse, location & item keys</small>
      </div>
      <div className="summary-card inventory-summary-card">
        <span>Quantity On Hand</span>
        <strong>{formatNumber(summary.quantity_on_hand)}</strong>
        <small>Total physical inventory in warehouse</small>
      </div>
      <div className="summary-card inventory-summary-card">
        <span>Quantity Reserved</span>
        <strong>{formatNumber(summary.quantity_reserved)}</strong>
        <small>Allocated to active orders & production</small>
      </div>
      <div className="summary-card inventory-summary-card summary-card-accent">
        <span>Available to Promise</span>
        <strong>{formatNumber(summary.quantity_available)}</strong>
        <small>Net free stock (On Hand − Reserved)</small>
      </div>
    </div>
    {error && <div className="feedback-message error-message">{error}</div>}{notice && <div className="feedback-message success-message">{notice}</div>}
    <div className="planning-tabs inventory-tabs">{[['balances', 'Inventory'], ['history', 'Transactions'], ['transfers', 'Transfers'], ['adjustments', 'Adjustments']].map(([value, label]) => <button className={tab === value ? 'active' : ''} key={value} onClick={() => setTab(value)} type="button">{label}</button>)}</div>
    <div className="master-data-toolbar inventory-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateQuery({ search: event.target.value })} placeholder="Search references, items, or warehouses" value={query.search} /></label>{renderFilters()}<span className="record-count">{meta.total || 0} records</span></div>
    <div className="data-card"><div className="data-card-header"><div><h2>{tab === 'balances' ? 'Warehouse stock' : tab === 'history' ? 'Inventory transaction history' : tab === 'transfers' ? 'Atomic stock transfers' : 'Authorized stock adjustments'}</h2><p className="data-card-hint">{tab === 'balances' ? 'The canonical controlled balance source of truth.' : 'Every movement is retained as an immutable ledger record.'}</p></div></div><div className="table-wrap">{loading ? <div className="planning-loading">Loading inventory records…</div> : records.length === 0 ? <div className="empty-state planning-empty">No inventory records match the selected filters.</div> : renderTable()}</div><Pagination meta={meta} onPage={(page) => updateQuery({ page })} /></div>

    {modal === 'stock-in' || modal === 'stock-out' ? <div className="modal-backdrop"><div className="modal-card inventory-modal-card"><div className="modal-header"><div><p className="eyebrow">Audited movement</p><h2>{modal === 'stock-in' ? 'Post stock in' : 'Post stock out'}</h2></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={(event) => submitMovement(event, modal)}><div className="form-grid"><label className="form-field"><span>Warehouse</span><select onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value, warehouse_location_id: '' }))} required value={form.warehouse_id}><option value="">Select warehouse</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Location</span><select onChange={(event) => setForm((current) => ({ ...current, warehouse_location_id: event.target.value }))} value={form.warehouse_location_id}><option value="">Warehouse total</option>{locations.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><ItemFields catalog={catalog} onChange={setForm} value={form} /><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setForm((current) => ({ ...current, remarks: event.target.value }))} rows="3" value={form.remarks} /></label></div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Posting…' : 'Post movement'}</button></div></form></div></div> : null}

    {modal === 'transfer' ? <div className="modal-backdrop"><div className="modal-card inventory-modal-card"><div className="modal-header"><div><p className="eyebrow">Atomic warehouse operation</p><h2>Transfer stock</h2></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitTransfer}><div className="form-grid"><label className="form-field"><span>Source warehouse</span><select onChange={(event) => setForm((current) => ({ ...current, source_warehouse_id: event.target.value, source_location_id: '' }))} required value={form.source_warehouse_id}><option value="">Select source</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Source location</span><select onChange={(event) => setForm((current) => ({ ...current, source_location_id: event.target.value }))} value={form.source_location_id}><option value="">Warehouse total</option>{sourceLocations.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Destination warehouse</span><select onChange={(event) => setForm((current) => ({ ...current, destination_warehouse_id: event.target.value, destination_location_id: '' }))} required value={form.destination_warehouse_id}><option value="">Select destination</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Destination location</span><select onChange={(event) => setForm((current) => ({ ...current, destination_location_id: event.target.value }))} value={form.destination_location_id}><option value="">Warehouse total</option>{destinationLocations.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><ItemFields catalog={catalog} onChange={setForm} value={form} /><label className="form-field"><span>Transfer date</span><input onChange={(event) => setForm((current) => ({ ...current, transfer_date: event.target.value }))} required type="date" value={form.transfer_date} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setForm((current) => ({ ...current, remarks: event.target.value }))} rows="3" value={form.remarks} /></label></div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Transferring…' : 'Post atomic transfer'}</button></div></form></div></div> : null}

    {modal === 'adjustment' ? <div className="modal-backdrop"><div className="modal-card inventory-modal-card"><div className="modal-header"><div><p className="eyebrow">Separate adjustment privilege</p><h2>Adjust stock</h2></div><button className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitAdjustment}><div className="form-grid"><label className="form-field"><span>Direction</span><select onChange={(event) => setForm((current) => ({ ...current, direction: event.target.value }))} value={form.direction}><option value="IN">Adjustment in</option><option value="OUT">Adjustment out</option></select></label><label className="form-field"><span>Adjustment date</span><input onChange={(event) => setForm((current) => ({ ...current, adjustment_date: event.target.value }))} required type="date" value={form.adjustment_date} /></label><label className="form-field"><span>Warehouse</span><select onChange={(event) => setForm((current) => ({ ...current, warehouse_id: event.target.value, warehouse_location_id: '' }))} required value={form.warehouse_id}><option value="">Select warehouse</option>{catalog.warehouses.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><label className="form-field"><span>Location</span><select onChange={(event) => setForm((current) => ({ ...current, warehouse_location_id: event.target.value }))} value={form.warehouse_location_id}><option value="">Warehouse total</option>{locations.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.name}</option>)}</select></label><ItemFields catalog={catalog} onChange={setForm} value={form} /><label className="form-field full-width"><span>Reason (required)</span><textarea minLength="3" onChange={(event) => setForm((current) => ({ ...current, reason: event.target.value }))} required rows="3" value={form.reason} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setForm((current) => ({ ...current, remarks: event.target.value }))} rows="2" value={form.remarks} /></label></div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy} type="submit">{busy ? 'Posting…' : 'Post adjustment'}</button></div></form></div></div> : null}

    {selected && (modal === 'balance-detail' || modal === 'transfer-detail' || modal === 'adjustment-detail') ? <div className="modal-backdrop"><div className="modal-card inventory-detail-modal"><div className="modal-header"><div><p className="eyebrow">Immutable operational record</p><h2>{detailTitle}</h2></div><button className="icon-button" onClick={closeModal} type="button">×</button></div>{modal === 'balance-detail' ? <><dl className="details-list"><div><dt>Stock key</dt><dd>{selected.stock_key}</dd></div><div><dt>Warehouse</dt><dd>{selected.warehouse?.name || selected.warehouse_id}</dd></div><div><dt>Item</dt><dd>{itemLabel(selected)}</dd></div><div><dt>Unit</dt><dd>{selected.unit?.code || selected.unit_id}</dd></div><div><dt>On hand</dt><dd>{formatNumber(selected.quantity_on_hand)}</dd></div><div><dt>Available</dt><dd>{formatNumber(selected.quantity_available)}</dd></div></dl><div className="inventory-detail-body"><h3>Recent movements</h3><div className="history-list">{(selected.transactions || []).map((transaction) => <div className="history-row" key={transaction.id}><strong>{transaction.transaction_type}</strong><span>{formatNumber(transaction.quantity)}</span><span>{formatDate(transaction.transaction_date)}</span><span>{transaction.remarks || '—'}</span></div>)}</div></div></> : modal === 'transfer-detail' ? <><dl className="details-list"><div><dt>Transfer</dt><dd>{selected.transfer_number}</dd></div><div><dt>Status</dt><dd>{statusLabel(selected.status)}</dd></div><div><dt>Route</dt><dd>{selected.source_warehouse?.name || selected.source_warehouse_id} → {selected.destination_warehouse?.name || selected.destination_warehouse_id}</dd></div><div><dt>Date</dt><dd>{formatDate(selected.transfer_date)}</dd></div></dl><div className="inventory-detail-body"><h3>Transfer lines</h3>{(selected.items || []).map((item) => <div className="inventory-line-card" key={item.id}><strong>{itemLabel(item)} · {formatNumber(item.quantity)} {item.unit?.code || ''}</strong><small>TRANSFER_OUT and TRANSFER_IN are committed atomically.</small></div>)}</div></> : <><dl className="details-list"><div><dt>Adjustment</dt><dd>{selected.adjustment_number}</dd></div><div><dt>Direction</dt><dd>{selected.direction}</dd></div><div><dt>Warehouse</dt><dd>{selected.warehouse?.name || selected.warehouse_id}</dd></div><div><dt>Reason</dt><dd>{selected.reason}</dd></div></dl><div className="inventory-detail-body"><h3>Adjustment lines</h3>{(selected.items || []).map((item) => <div className="inventory-line-card" key={item.id}><strong>{itemLabel(item)} · {formatNumber(item.quantity)} {item.unit?.code || ''}</strong><small>Ledger transaction retained for audit history.</small></div>)}</div></>}</div></div> : null}
  </div>
}

export default InventoryPage
