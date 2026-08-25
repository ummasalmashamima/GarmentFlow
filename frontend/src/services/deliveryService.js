import api from './api'

const deliveryUrl = (deliveryId = '') => `/deliveries${deliveryId ? `/${deliveryId}` : ''}`
const unwrap = ({ data }) => data
const unwrapData = ({ data }) => data.data ?? data

const deliveryService = {
  async list(params = {}) {
    return unwrap(await api.get(deliveryUrl(), { params }))
  },

  async get(deliveryId) {
    return unwrapData(await api.get(deliveryUrl(deliveryId)))
  },

  async create(payload) {
    return unwrapData(await api.post(deliveryUrl(), payload))
  },

  async update(deliveryId, payload) {
    return unwrapData(await api.put(deliveryUrl(deliveryId), payload))
  },

  async dispatch(deliveryId, remarks = null) {
    return unwrapData(await api.post(`${deliveryUrl(deliveryId)}/dispatch`, { remarks }))
  },

  async transition(deliveryId, status, remarks = null) {
    return unwrapData(await api.post(`${deliveryUrl(deliveryId)}/status`, { status, remarks }))
  },

  async complete(deliveryId, remarks = null) {
    return unwrapData(await api.post(`${deliveryUrl(deliveryId)}/complete`, { remarks }))
  },

  async tracking(deliveryId, payload) {
    return unwrapData(await api.post(`${deliveryUrl(deliveryId)}/tracking`, payload))
  },

  async trackingHistory(deliveryId) {
    return unwrapData(await api.get(`${deliveryUrl(deliveryId)}/tracking-history`))
  },

  async history(params = {}) {
    return unwrap(await api.get(`${deliveryUrl()}/history`, { params }))
  },
}

export default deliveryService
