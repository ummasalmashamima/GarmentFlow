import { useCallback, useEffect, useState } from 'react'
import financeService from '../../services/financeService'

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
const today = () => new Date().toISOString().slice(0, 10)
const dueDate = () => { const date = new Date(); date.setDate(date.getDate() + 30); return date.toISOString().slice(0, 10) }
const emptyInvoiceForm = () => ({ sales_order_id: '', invoice_date: today(), due_date: dueDate(), remarks: '' })
const emptyPaymentForm = () => ({ invoice_id: '', payment_date: today(), amount: '', payment_method: 'bank_transfer', reference_number: '', idempotency_key: `finance-ui-${Date.now()}`, remarks: '' })
const invoiceStatuses = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled']

function errorMessage(error) {
  const response = error.response?.data
  const firstValidationError = response?.errors && Object.values(response.errors)[0]?.[0]
  return firstValidationError || response?.message || 'Unable to complete the request. Please try again.'
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 })
}

function statusLabel(status) {
  return (status || '—').replaceAll('_', ' ')
}

function statusClass(status) {
  return `status-pill status-${(status || 'draft').replaceAll('_', '-')}`
}

function partyLabel(record) {
  return record?.buyer?.name || record?.customer?.name || record?.buyer?.code || record?.customer?.code || '—'
}

function Pagination({ meta, loading, onPage }) {
  return <div className="pagination-bar"><span>Page {meta.current_page || 1} of {meta.last_page || 1}</span><div><button className="secondary-button" disabled={(meta.current_page || 1) <= 1 || loading} onClick={() => onPage((meta.current_page || 1) - 1)} type="button">Previous</button><button className="secondary-button" disabled={(meta.current_page || 1) >= (meta.last_page || 1) || loading} onClick={() => onPage((meta.current_page || 1) + 1)} type="button">Next</button></div></div>
}

function FinancePage() {
  const [tab, setTab] = useState('invoices')
  const [invoicePage, setInvoicePage] = useState(emptyPage)
  const [paymentPage, setPaymentPage] = useState(emptyPage)
  const [historyPage, setHistoryPage] = useState(emptyPage)
  const [invoiceQuery, setInvoiceQuery] = useState({ search: '', status: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [paymentQuery, setPaymentQuery] = useState({ search: '', status: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [historyQuery, setHistoryQuery] = useState({ search: '', module: '', action: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' })
  const [eligibleOrders, setEligibleOrders] = useState([])
  const [invoiceSource, setInvoiceSource] = useState(null)
  const [selectedInvoice, setSelectedInvoice] = useState(null)
  const [invoiceHistory, setInvoiceHistory] = useState([])
  const [invoiceForm, setInvoiceForm] = useState(emptyInvoiceForm)
  const [paymentForm, setPaymentForm] = useState(emptyPaymentForm)
  const [summary, setSummary] = useState(null)
  const [summaryLoading, setSummaryLoading] = useState(false)
  const [modal, setModal] = useState(null)
  const [loading, setLoading] = useState(true)
  const [paymentLoading, setPaymentLoading] = useState(false)
  const [historyLoading, setHistoryLoading] = useState(false)
  const [eligibleLoading, setEligibleLoading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const loadInvoices = useCallback(async () => {
    setLoading(true)
    setError('')
    try { setInvoicePage(await financeService.invoices(invoiceQuery)) } catch (requestError) { setError(errorMessage(requestError)) } finally { setLoading(false) }
  }, [invoiceQuery])

  const loadPayments = useCallback(async () => {
    setPaymentLoading(true)
    setError('')
    try { setPaymentPage(await financeService.payments(paymentQuery)) } catch (requestError) { setError(errorMessage(requestError)) } finally { setPaymentLoading(false) }
  }, [paymentQuery])

  const loadHistory = useCallback(async () => {
    setHistoryLoading(true)
    setError('')
    try { setHistoryPage(await financeService.history(historyQuery)) } catch (requestError) { setError(errorMessage(requestError)) } finally { setHistoryLoading(false) }
  }, [historyQuery])

  const loadEligibleOrders = useCallback(async () => {
    setEligibleLoading(true)
    try { setEligibleOrders(await financeService.eligibleSalesOrders()) } catch (requestError) { setError(errorMessage(requestError)) } finally { setEligibleLoading(false) }
  }, [])

  useEffect(() => { Promise.resolve().then(loadInvoices) }, [loadInvoices])
  useEffect(() => {
    if (tab !== 'payments') return undefined
    Promise.resolve().then(loadPayments)
    return undefined
  }, [loadPayments, tab])
  useEffect(() => {
    if (tab !== 'history') return undefined
    Promise.resolve().then(loadHistory)
    return undefined
  }, [historyQuery, loadHistory, tab])
  useEffect(() => {
    if (!['receivables', 'payables', 'profit'].includes(tab)) return undefined
    let active = true
    const request = tab === 'receivables' ? financeService.receivables() : tab === 'payables' ? financeService.payables() : financeService.profit()
    request.then((result) => { if (active) setSummary(result) }).catch((requestError) => { if (active) setError(errorMessage(requestError)) }).finally(() => { if (active) setSummaryLoading(false) })
    return () => { active = false }
  }, [tab])

  const updateQuery = (setter, changes) => setter((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const toggleSort = (setter, currentQuery, column) => setter({ ...currentQuery, sort: column, direction: currentQuery.sort === column && currentQuery.direction === 'asc' ? 'desc' : 'asc', page: 1 })
  const openCreateInvoice = () => {
    setInvoiceForm(emptyInvoiceForm())
    setInvoiceSource(null)
    setError('')
    setNotice('')
    setModal('invoice-form')
    loadEligibleOrders()
  }
  const closeModal = () => {
    setModal(null)
    setSelectedInvoice(null)
    setInvoiceSource(null)
    setInvoiceHistory([])
    setPaymentForm(emptyPaymentForm())
  }
  const chooseOrder = (value) => {
    const source = eligibleOrders.find((order) => String(order.id) === String(value)) || null
    setInvoiceForm((current) => ({ ...current, sales_order_id: value }))
    setInvoiceSource(source)
  }
  const openInvoice = async (invoice) => {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const detail = await financeService.invoice(invoice.id)
      const history = await financeService.invoiceHistory(invoice.id)
      setSelectedInvoice(detail)
      setInvoiceHistory(history.audit_logs || [])
      setModal('invoice-detail')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const submitInvoice = async (event) => {
    event.preventDefault()
    if (!invoiceSource) { setError('Select an eligible delivered or completed Sales Order.') ; return }
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const items = (invoiceSource.items || []).map((item) => ({
        sales_order_item_id: item.id,
        quantity: Number(item.quantity ?? item.delivered_quantity ?? 0),
        unit_price: Number(item.unit_price || 0),
        discount_amount: Number(item.discount_amount || 0),
        tax_amount: Number(item.tax_amount || 0),
      })).filter((item) => item.quantity > 0)
      const created = await financeService.createInvoice({ ...invoiceForm, sales_order_id: Number(invoiceForm.sales_order_id), items })
      await loadInvoices()
      await loadEligibleOrders()
      setSelectedInvoice(created)
      setNotice('Invoice draft created successfully. Totals were calculated by the backend.')
      setModal('invoice-detail')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const invoiceAction = async (action, status = null) => {
    if (!selectedInvoice) return
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const result = action === 'status' ? await financeService.transitionInvoice(selectedInvoice.id, status, null) : await financeService[action](selectedInvoice.id, null)
      setSelectedInvoice(result)
      await loadInvoices()
      setNotice(`Invoice ${statusLabel(result.status)} successfully.`)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const openPayment = (invoice = null) => {
    setPaymentForm({ ...emptyPaymentForm(), invoice_id: invoice?.id || '', amount: invoice?.due_amount || '' })
    setError('')
    setNotice('')
    setModal('payment-form')
  }
  const submitPayment = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const created = await financeService.createPayment({ ...paymentForm, invoice_id: Number(paymentForm.invoice_id), amount: Number(paymentForm.amount) })
      await loadInvoices()
      if (selectedInvoice && selectedInvoice.id === created.invoice_id) {
        setSelectedInvoice(await financeService.invoice(created.invoice_id))
      }
      if (tab === 'payments') await loadPayments()
      setNotice('Payment recorded successfully and invoice status was recalculated by the backend.')
      setModal(selectedInvoice ? 'invoice-detail' : null)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const updateHistoryQuery = (changes) => updateQuery(setHistoryQuery, changes)
  const invoiceRecords = invoicePage.data || []
  const paymentRecords = paymentPage.data || []
  const historyRecords = historyPage.data || []
  const invoiceMeta = invoicePage.meta || emptyPage.meta
  const paymentMeta = paymentPage.meta || emptyPage.meta
  const historyMeta = historyPage.meta || emptyPage.meta
  const payableInvoices = invoiceRecords.filter((invoice) => ['issued', 'partially_paid', 'overdue'].includes(invoice.status) && Number(invoice.due_amount) > 0)
  const changeTab = (value) => {
    setTab(value)
    setError('')
    setNotice('')
    if (['receivables', 'payables', 'profit'].includes(value)) setSummaryLoading(true)
  }

  return <div className="master-data-page finance-page">
    <div className="page-intro master-data-intro"><div><p className="eyebrow">Phase 11 · Finance Management</p><h1>Finance</h1><p>Issue invoices from eligible Sales Orders, record auditable payments, and review receivables, payables, and transparent profit calculations.</p></div><button className="primary-button" onClick={openCreateInvoice} type="button">Create Invoice</button></div>
    <div className="production-tabs" role="tablist" aria-label="Finance views">{[['invoices', 'Invoices'], ['payments', 'Payments'], ['receivables', 'Receivables'], ['payables', 'Payables'], ['profit', 'Profit Summary'], ['history', 'Finance History']].map(([value, label]) => <button aria-selected={tab === value} className={`secondary-button ${tab === value ? 'active-tab' : ''}`} key={value} onClick={() => changeTab(value)} role="tab" type="button">{label}</button>)}</div>
    {error && <div className="feedback-message error-message" role="alert">{error}</div>}{notice && <div className="feedback-message success-message" role="status">{notice}</div>}

    {tab === 'invoices' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Accounts receivable register</p><h2>Invoices</h2></div><span className="data-card-hint">Only delivered or completed Sales Orders can be invoiced</span></div><div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateQuery(setInvoiceQuery, { search: event.target.value })} placeholder="Invoice, Sales Order, or party" value={invoiceQuery.search} /></label><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery(setInvoiceQuery, { status: event.target.value })} value={invoiceQuery.status}><option value="">All statuses</option>{invoiceStatuses.map((status) => <option key={status} value={status}>{statusLabel(status)}</option>)}</select></label><span className="record-count">{invoiceMeta.total || 0} invoices</span></div>{loading ? <div className="empty-state">Loading invoices…</div> : invoiceRecords.length === 0 ? <div className="empty-state">No invoices match the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr>{[['invoice_number', 'Invoice number'], ['invoice_date', 'Invoice date'], ['due_date', 'Due date'], ['total_amount', 'Total'], ['paid_amount', 'Paid'], ['due_amount', 'Due'], ['status', 'Status']].map(([column, label]) => <th key={column}><button onClick={() => toggleSort(setInvoiceQuery, invoiceQuery, column)} type="button">{label}{invoiceQuery.sort === column ? ` ${invoiceQuery.direction === 'asc' ? '↑' : '↓'}` : ''}</button></th>)}<th>Party</th><th>Sales Order</th><th /></tr></thead><tbody>{invoiceRecords.map((invoice) => <tr key={invoice.id} onClick={() => openInvoice(invoice)}><td><strong>{invoice.invoice_number}</strong></td><td>{invoice.invoice_date || '—'}</td><td>{invoice.due_date || '—'}</td><td>{formatMoney(invoice.total_amount)}</td><td>{formatMoney(invoice.paid_amount)}</td><td>{formatMoney(invoice.due_amount)}</td><td><span className={statusClass(invoice.status)}>{statusLabel(invoice.status)}</span></td><td>{partyLabel(invoice)}</td><td>{invoice.sales_order?.sales_order_number || '—'}</td><td className="table-actions" onClick={(event) => event.stopPropagation()}><button className="text-button" onClick={() => openInvoice(invoice)} type="button">Open</button></td></tr>)}</tbody></table></div>}<Pagination meta={invoiceMeta} loading={loading} onPage={(page) => updateQuery(setInvoiceQuery, { page })} /></section>}

    {tab === 'payments' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Collection register</p><h2>Payments</h2></div><button className="primary-button" onClick={() => openPayment()} type="button">Record Payment</button></div><div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateQuery(setPaymentQuery, { search: event.target.value })} placeholder="Payment, invoice, method, or reference" value={paymentQuery.search} /></label><label className="filter-field"><span>Status</span><select onChange={(event) => updateQuery(setPaymentQuery, { status: event.target.value })} value={paymentQuery.status}><option value="">All statuses</option><option value="received">Received</option><option value="voided">Voided</option></select></label><span className="record-count">{paymentMeta.total || 0} payments</span></div>{paymentLoading ? <div className="empty-state">Loading payments…</div> : paymentRecords.length === 0 ? <div className="empty-state">No payments match the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Payment number</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Received by</th></tr></thead><tbody>{paymentRecords.map((payment) => <tr key={payment.id}><td><strong>{payment.payment_number}</strong></td><td>{payment.invoice?.invoice_number || '—'}</td><td>{payment.payment_date || '—'}</td><td>{formatMoney(payment.amount)}</td><td>{statusLabel(payment.payment_method)}</td><td>{payment.reference_number || '—'}</td><td><span className={statusClass(payment.status)}>{statusLabel(payment.status)}</span></td><td>{payment.receiver?.name || '—'}</td></tr>)}</tbody></table></div>}<Pagination meta={paymentMeta} loading={paymentLoading} onPage={(page) => updateQuery(setPaymentQuery, { page })} /></section>}

    {tab === 'receivables' && <SummaryCard title="Accounts Receivable" eyebrow="Customer and buyer exposure" loading={summaryLoading}>{summary && <><div className="order-detail-summary"><div><span>Total invoiced</span><strong>{formatMoney(summary.total_invoiced)}</strong></div><div><span>Total paid</span><strong>{formatMoney(summary.total_paid)}</strong></div><div><span>Total outstanding</span><strong>{formatMoney(summary.total_outstanding)}</strong></div><div><span>Overdue amount</span><strong>{formatMoney(summary.overdue_amount)}</strong></div><div><span>Partial invoices</span><strong>{formatNumber(summary.partially_paid_invoice_count)}</strong></div><div><span>Invoice count</span><strong>{formatNumber(summary.invoice_count)}</strong></div></div><SummaryTable title="Party breakdown" columns={['Party', 'Invoices', 'Invoiced', 'Paid', 'Outstanding']} rows={(summary.party_breakdown || []).map((row) => [row.party_name || row.party_code || '—', row.invoice_count, formatMoney(row.total_invoiced), formatMoney(row.total_paid), formatMoney(row.total_outstanding)])} /></>}</SummaryCard>}
    {tab === 'payables' && <SummaryCard title="Accounts Payable" eyebrow="Derived from existing Procurement data" loading={summaryLoading}>{summary && <><div className="order-detail-summary"><div><span>Eligible POs</span><strong>{formatNumber(summary.purchase_order_count)}</strong></div><div><span>Total payable</span><strong>{formatMoney(summary.total_payable)}</strong></div><div><span>Goods received value</span><strong>{formatMoney(summary.goods_received_value)}</strong></div><div><span>Outstanding payable</span><strong>{formatMoney(summary.outstanding_payable)}</strong></div></div><div className="inline-preview">{summary.limitation}</div><SummaryTable title="Supplier breakdown" columns={['Supplier', 'POs', 'Payable', 'Received', 'Outstanding']} rows={(summary.supplier_breakdown || []).map((row) => [row.supplier_name || row.supplier_code || '—', row.purchase_order_count, formatMoney(row.total_payable), formatMoney(row.goods_received_value), formatMoney(row.outstanding_payable)])} /></>}</SummaryCard>}
    {tab === 'profit' && <SummaryCard title="Profit Summary" eyebrow="Invoice revenue minus available cost data" loading={summaryLoading}>{summary && <><div className="order-detail-summary"><div><span>Gross sales</span><strong>{formatMoney(summary.gross_sales)}</strong></div><div><span>COGS</span><strong>{formatMoney(summary.cost_of_goods_sold)}</strong></div><div><span>Gross profit</span><strong>{summary.gross_profit === null ? 'Unavailable' : formatMoney(summary.gross_profit)}</strong></div><div><span>Profit margin</span><strong>{summary.profit_margin === null ? 'Unavailable' : `${formatMoney(summary.profit_margin)}%`}</strong></div><div><span>Cost complete</span><strong>{summary.cost_data_complete ? 'Yes' : 'No'}</strong></div><div><span>Unpriced lines</span><strong>{formatNumber(summary.unpriced_line_count)}</strong></div></div>{summary.limitation && <div className="inline-preview">{summary.limitation}</div>}{(summary.missing_cost_items || []).length > 0 && <SummaryTable title="Missing cost items" columns={['Invoice', 'Product', 'SKU', 'Quantity']} rows={summary.missing_cost_items.map((row) => [row.invoice_number, row.product_code || '—', row.product_variant_sku || '—', formatNumber(row.quantity)])} />}</>}</SummaryCard>}

    {tab === 'history' && <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">Shared audit register</p><h2>Finance History</h2></div><span className="data-card-hint">Invoice and payment actions are immutable and auditable</span></div><div className="master-data-toolbar"><label className="search-field"><span>Search</span><input onChange={(event) => updateHistoryQuery({ search: event.target.value })} placeholder="Search module, action, or record" value={historyQuery.search} /></label><label className="filter-field"><span>Module</span><select onChange={(event) => updateHistoryQuery({ module: event.target.value })} value={historyQuery.module}><option value="">All modules</option><option value="invoices">Invoices</option><option value="invoice-items">Invoice Items</option><option value="payments">Payments</option></select></label><label className="filter-field"><span>Action</span><input onChange={(event) => updateHistoryQuery({ action: event.target.value })} placeholder="e.g. created" value={historyQuery.action} /></label></div>{historyLoading ? <div className="empty-state">Loading Finance History…</div> : historyRecords.length === 0 ? <div className="empty-state">No Finance History matches the current filters.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr><th>Time</th><th>Module</th><th>Action</th><th>Record</th><th>Actor</th><th>Changes</th></tr></thead><tbody>{historyRecords.map((entry) => <tr key={entry.id}><td>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</td><td>{entry.module}</td><td>{statusLabel(entry.action)}</td><td>{entry.record_type?.split('\\').pop() || 'Record'} #{entry.record_id}</td><td>{entry.user?.name || entry.user?.email || '—'}</td><td>{entry.new_values?.status ? `${entry.old_values?.status || '—'} → ${entry.new_values.status}` : 'Recorded change'}</td></tr>)}</tbody></table></div>}<Pagination meta={historyMeta} loading={historyLoading} onPage={(page) => updateHistoryQuery({ page })} /></section>}

    <button className="secondary-button back-link" onClick={() => window.history.back()} type="button">← Back</button>

    {modal === 'invoice-form' && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card" role="dialog" aria-modal="true" aria-labelledby="invoice-form-title"><div className="modal-header"><div><p className="eyebrow">Accounts receivable</p><h2 id="invoice-form-title">Create Invoice</h2></div><button aria-label="Close invoice form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitInvoice}><div className="inline-preview">Invoice totals are calculated on the backend. Only delivered or completed Sales Orders with no existing invoice are listed.</div><div className="form-grid"><label className="form-field full-width"><span>Eligible Sales Order *</span><select onChange={(event) => chooseOrder(event.target.value)} required value={invoiceForm.sales_order_id}><option value="">{eligibleLoading ? 'Loading eligible orders…' : 'Select Sales Order'}</option>{eligibleOrders.map((order) => <option key={order.id} value={order.id}>{order.sales_order_number} · {partyLabel(order)} · delivered {formatNumber(order.delivered_quantity)}</option>)}</select></label><label className="form-field"><span>Invoice date *</span><input onChange={(event) => setInvoiceForm((current) => ({ ...current, invoice_date: event.target.value }))} required type="date" value={invoiceForm.invoice_date} /></label><label className="form-field"><span>Due date *</span><input onChange={(event) => setInvoiceForm((current) => ({ ...current, due_date: event.target.value }))} required type="date" value={invoiceForm.due_date} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setInvoiceForm((current) => ({ ...current, remarks: event.target.value }))} rows="2" value={invoiceForm.remarks} /></label></div>{invoiceSource && <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Delivered source quantities</p><h3>{invoiceSource.sales_order_number}</h3></div><span className={statusClass(invoiceSource.status)}>{statusLabel(invoiceSource.status)}</span></div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Product</th><th>Variant</th><th>Delivered quantity</th><th>Unit</th><th>Unit price</th></tr></thead><tbody>{(invoiceSource.items || []).map((item) => <tr key={item.id}><td>{item.product?.name || '—'}</td><td>{item.product_variant?.sku || 'Product level'}</td><td>{formatNumber(item.delivered_quantity)}</td><td>{item.unit?.code || item.unit?.symbol || '—'}</td><td>{formatMoney(item.unit_price)}</td></tr>)}</tbody></table></div></section>}<div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || !invoiceSource} type="submit">{busy ? 'Creating…' : 'Create draft invoice'}</button></div></form></div></div>}

    {modal === 'payment-form' && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card" role="dialog" aria-modal="true" aria-labelledby="payment-form-title"><div className="modal-header"><div><p className="eyebrow">Accounts receivable</p><h2 id="payment-form-title">Record Payment</h2></div><button aria-label="Close payment form" className="icon-button" onClick={closeModal} type="button">×</button></div><form className="master-data-form" onSubmit={submitPayment}><div className="inline-preview">Payments are locked transactionally, cannot exceed due amount, and update invoice status automatically.</div><div className="form-grid"><label className="form-field full-width"><span>Invoice *</span><select onChange={(event) => { const invoice = payableInvoices.find((item) => String(item.id) === event.target.value); setPaymentForm((current) => ({ ...current, invoice_id: event.target.value, amount: invoice?.due_amount || '' })) }} required value={paymentForm.invoice_id}><option value="">Select outstanding invoice</option>{payableInvoices.map((invoice) => <option key={invoice.id} value={invoice.id}>{invoice.invoice_number} · due {formatMoney(invoice.due_amount)} · {partyLabel(invoice)}</option>)}</select></label><label className="form-field"><span>Payment date *</span><input onChange={(event) => setPaymentForm((current) => ({ ...current, payment_date: event.target.value }))} required type="date" value={paymentForm.payment_date} /></label><label className="form-field"><span>Amount *</span><input min="0.0001" onChange={(event) => setPaymentForm((current) => ({ ...current, amount: event.target.value }))} required step="0.0001" type="number" value={paymentForm.amount} /></label><label className="form-field"><span>Payment method *</span><select onChange={(event) => setPaymentForm((current) => ({ ...current, payment_method: event.target.value }))} required value={paymentForm.payment_method}><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="card">Card</option><option value="mobile_money">Mobile money</option><option value="other">Other</option></select></label><label className="form-field"><span>Reference number</span><input onChange={(event) => setPaymentForm((current) => ({ ...current, reference_number: event.target.value }))} value={paymentForm.reference_number} /></label><label className="form-field full-width"><span>Remarks</span><textarea onChange={(event) => setPaymentForm((current) => ({ ...current, remarks: event.target.value }))} rows="2" value={paymentForm.remarks} /></label></div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Cancel</button><button className="primary-button" disabled={busy || !paymentForm.invoice_id} type="submit">{busy ? 'Recording…' : 'Record payment'}</button></div></form></div></div>}

    {modal === 'invoice-detail' && selectedInvoice && <div className="modal-backdrop" role="presentation"><div className="modal-card order-modal-card order-detail-modal" role="dialog" aria-modal="true" aria-labelledby="invoice-detail-title"><div className="modal-header"><div><p className="eyebrow">Invoice detail</p><h2 id="invoice-detail-title">{selectedInvoice.invoice_number}</h2><p>{partyLabel(selectedInvoice)} · {selectedInvoice.sales_order?.sales_order_number || '—'}</p></div><button aria-label="Close invoice details" className="icon-button" onClick={closeModal} type="button">×</button></div><div className="order-detail-summary"><div><span>Status</span><strong><span className={statusClass(selectedInvoice.status)}>{statusLabel(selectedInvoice.status)}</span></strong></div><div><span>Invoice date</span><strong>{selectedInvoice.invoice_date || '—'}</strong></div><div><span>Due date</span><strong>{selectedInvoice.due_date || '—'}</strong></div><div><span>Subtotal</span><strong>{formatMoney(selectedInvoice.subtotal)}</strong></div><div><span>Total</span><strong>{formatMoney(selectedInvoice.total_amount)}</strong></div><div><span>Paid</span><strong>{formatMoney(selectedInvoice.paid_amount)}</strong></div><div><span>Due</span><strong>{formatMoney(selectedInvoice.due_amount)}</strong></div></div><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Invoice items</p><h3>Backend-calculated lines</h3></div></div><div className="table-wrap"><table className="master-data-table"><thead><tr><th>Product</th><th>Variant</th><th>Quantity</th><th>Unit price</th><th>Discount</th><th>Tax</th><th>Line total</th></tr></thead><tbody>{(selectedInvoice.items || []).map((item) => <tr key={item.id}><td>{item.product?.name || '—'}</td><td>{item.product_variant?.sku || 'Product level'}</td><td>{formatNumber(item.quantity)}</td><td>{formatMoney(item.unit_price)}</td><td>{formatMoney(item.discount_amount)}</td><td>{formatMoney(item.tax_amount)}</td><td>{formatMoney(item.line_total)}</td></tr>)}</tbody></table></div></section><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Payment history</p><h3>{(selectedInvoice.payments || []).length} payment records</h3></div><button className="secondary-button" disabled={selectedInvoice.status === 'draft' || selectedInvoice.status === 'paid' || selectedInvoice.status === 'cancelled'} onClick={() => openPayment(selectedInvoice)} type="button">Record payment</button></div>{(selectedInvoice.payments || []).length === 0 ? <div className="empty-state">No payments recorded.</div> : <div className="history-list">{selectedInvoice.payments.map((payment) => <div className="history-row" key={payment.id}><span>{payment.payment_date}</span><strong>{formatMoney(payment.amount)}</strong><span>{statusLabel(payment.payment_method)}</span><span>{payment.reference_number || 'No reference'}</span><span>{statusLabel(payment.status)}</span></div>)}</div>}</section><section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Audit trail</p><h3>Invoice history</h3></div></div><div className="history-list">{invoiceHistory.map((entry) => <div className="history-row" key={entry.id}><span>{entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'}</span><strong>{statusLabel(entry.action)}</strong><span>{entry.user?.name || entry.user?.email || '—'}</span><span>{entry.new_values?.status ? `${entry.old_values?.status || '—'} → ${entry.new_values.status}` : 'Recorded change'}</span></div>)}</div></section><div className="workflow-actions">{selectedInvoice.status === 'draft' && <button className="primary-button" disabled={busy} onClick={() => invoiceAction('issueInvoice')} type="button">Issue invoice</button>}{['issued', 'partially_paid', 'overdue'].includes(selectedInvoice.status) && <button className="primary-button" disabled={busy} onClick={() => openPayment(selectedInvoice)} type="button">Record payment</button>}{['issued', 'partially_paid', 'overdue'].includes(selectedInvoice.status) && <button className="secondary-button danger-text" disabled={busy} onClick={() => invoiceAction('cancelInvoice')} type="button">Cancel invoice</button>}{selectedInvoice.stored_status === 'issued' && selectedInvoice.status === 'overdue' && <button className="secondary-button" disabled={busy} onClick={() => invoiceAction('status', 'overdue')} type="button">Record overdue status</button>}{selectedInvoice.status === 'cancelled' && <span className="completed-note">Cancelled invoices cannot receive payments.</span>}{selectedInvoice.status === 'paid' && <span className="completed-note">Paid invoices are closed to additional payments.</span>}</div><div className="modal-actions"><button className="secondary-button" onClick={closeModal} type="button">Close</button></div></div></div>}
  </div>
}

function SummaryCard({ title, eyebrow, loading, children }) {
  return <section className="data-card"><div className="data-card-header"><div><p className="eyebrow">{eyebrow}</p><h2>{title}</h2></div><span className="data-card-hint">Backend summary</span></div>{loading ? <div className="empty-state">Loading {title}…</div> : children}</section>
}

function SummaryTable({ title, columns, rows }) {
  return <section className="order-detail-section"><div className="order-section-heading"><div><p className="eyebrow">Breakdown</p><h3>{title}</h3></div></div>{rows.length === 0 ? <div className="empty-state">No records are available for this summary.</div> : <div className="table-wrap"><table className="master-data-table"><thead><tr>{columns.map((column) => <th key={column}>{column}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr key={`${title}-${index}`}>{row.map((value, valueIndex) => <td key={`${title}-${index}-${valueIndex}`}>{value}</td>)}</tr>)}</tbody></table></div>}</section>
}

export default FinancePage
