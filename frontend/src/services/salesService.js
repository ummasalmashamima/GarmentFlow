import api from './api'

const orderUrl = (orderId = '') => `/sales/orders${orderId ? `/${orderId}` : ''}`
const unwrap = ({ data }) => data
const unwrapData = ({ data }) => data.data ?? data

const salesService = {
  async list(params = {}) {
    return unwrap(await api.get(orderUrl(), { params }))
  },

  async preview(items, orderDiscountAmount = 0, orderTaxAmount = 0) {
    return unwrapData(await api.post(`${orderUrl()}/preview`, {
      items,
      order_discount_amount: orderDiscountAmount,
      order_tax_amount: orderTaxAmount,
    }))
  },

  async get(orderId) {
    return unwrapData(await api.get(orderUrl(orderId)))
  },

  async create(payload) {
    return unwrapData(await api.post(orderUrl(), payload))
  },

  async update(orderId, payload) {
    return unwrapData(await api.put(orderUrl(orderId), payload))
  },

  async submit(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/submit`, { remarks }))
  },

  async confirm(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/confirm`, { remarks }))
  },

  async cancel(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/cancel`, { remarks }))
  },

  async transition(orderId, status, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/status`, { status, remarks }))
  },

  async availability(orderId) {
    return unwrapData(await api.get(`${orderUrl(orderId)}/availability`))
  },

  async statusHistory(orderId) {
    return unwrapData(await api.get(`${orderUrl(orderId)}/status-history`))
  },

  async history(params = {}) {
    return unwrap(await api.get('/sales/history', { params }))
  },
}

export default salesService
