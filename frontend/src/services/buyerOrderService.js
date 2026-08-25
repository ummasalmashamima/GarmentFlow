import api from './api'

const orderUrl = (orderId = '') => `/buyer-orders${orderId ? `/${orderId}` : ''}`
const itemUrl = (orderId, itemId = '') => `${orderUrl(orderId)}/items${itemId ? `/${itemId}` : ''}`
const unwrap = ({ data }) => data
const unwrapData = ({ data }) => data.data ?? data

const buyerOrderService = {
  async list(params = {}) {
    return unwrap(await api.get(orderUrl(), { params }))
  },

  async preview(items) {
    return unwrapData(await api.post(`${orderUrl()}/preview`, { items }))
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

  async remove(orderId) {
    return unwrap(await api.delete(orderUrl(orderId)))
  },

  async submit(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/submit`, { remarks }))
  },

  async approve(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/approve`, { remarks }))
  },

  async reject(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/reject`, { remarks }))
  },

  async confirm(orderId, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/confirm`, { remarks }))
  },

  async transition(orderId, status, remarks = null) {
    return unwrapData(await api.post(`${orderUrl(orderId)}/status`, { status, remarks }))
  },

  async items(orderId) {
    return unwrapData(await api.get(itemUrl(orderId)))
  },

  async createItem(orderId, payload) {
    return unwrapData(await api.post(itemUrl(orderId), payload))
  },

  async updateItem(orderId, itemId, payload) {
    return unwrapData(await api.put(itemUrl(orderId, itemId), payload))
  },

  async removeItem(orderId, itemId) {
    return unwrap(await api.delete(itemUrl(orderId, itemId)))
  },

  async history(orderId) {
    return unwrapData(await api.get(`${orderUrl(orderId)}/history`))
  },
}

export default buyerOrderService
