import api from './api'

const unwrap = ({ data }) => data
const unwrapData = ({ data }) => data.data ?? data
const invoiceUrl = (invoiceId = '') => `/finance/invoices${invoiceId ? `/${invoiceId}` : ''}`
const paymentUrl = (paymentId = '') => `/finance/payments${paymentId ? `/${paymentId}` : ''}`

const financeService = {
  async invoices(params = {}) {
    return unwrap(await api.get(invoiceUrl(), { params }))
  },

  async eligibleSalesOrders() {
    return unwrapData(await api.get(`${invoiceUrl()}/eligible-sales-orders`))
  },

  async invoice(invoiceId) {
    return unwrapData(await api.get(invoiceUrl(invoiceId)))
  },

  async createInvoice(payload) {
    return unwrapData(await api.post(invoiceUrl(), payload))
  },

  async updateInvoice(invoiceId, payload) {
    return unwrapData(await api.put(invoiceUrl(invoiceId), payload))
  },

  async issueInvoice(invoiceId, remarks = null) {
    return unwrapData(await api.post(`${invoiceUrl(invoiceId)}/issue`, { remarks }))
  },

  async cancelInvoice(invoiceId, remarks = null) {
    return unwrapData(await api.post(`${invoiceUrl(invoiceId)}/cancel`, { remarks }))
  },

  async transitionInvoice(invoiceId, status, remarks = null) {
    return unwrapData(await api.post(`${invoiceUrl(invoiceId)}/status`, { status, remarks }))
  },

  async invoiceHistory(invoiceId) {
    return unwrapData(await api.get(`${invoiceUrl(invoiceId)}/history`))
  },

  async payments(params = {}) {
    return unwrap(await api.get(paymentUrl(), { params }))
  },

  async paymentHistory(params = {}) {
    return unwrap(await api.get(`${paymentUrl()}/history`, { params }))
  },

  async payment(paymentId) {
    return unwrapData(await api.get(paymentUrl(paymentId)))
  },

  async createPayment(payload) {
    return unwrapData(await api.post(paymentUrl(), payload))
  },

  async voidPayment(paymentId, remarks = null) {
    return unwrapData(await api.post(`${paymentUrl(paymentId)}/status`, { status: 'voided', remarks }))
  },

  async paymentHistoryDetail(paymentId) {
    return unwrapData(await api.get(`${paymentUrl(paymentId)}/history`))
  },

  async receivables(params = {}) {
    return unwrapData(await api.get('/finance/receivables', { params }))
  },

  async payables(params = {}) {
    return unwrapData(await api.get('/finance/payables', { params }))
  },

  async profit(params = {}) {
    return unwrapData(await api.get('/finance/profit', { params }))
  },

  async history(params = {}) {
    return unwrap(await api.get('/finance/history', { params }))
  },
}

export default financeService
