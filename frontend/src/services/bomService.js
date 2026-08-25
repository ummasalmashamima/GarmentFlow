import api from './api'

const bomUrl = (bomId = '') => `/boms${bomId ? `/${bomId}` : ''}`
const versionUrl = (bomId, versionId = '') => `${bomUrl(bomId)}/versions${versionId ? `/${versionId}` : ''}`
const itemUrl = (bomId, versionId, itemId = '') => `${versionUrl(bomId, versionId)}/items${itemId ? `/${itemId}` : ''}`

const unwrap = ({ data }) => data
const unwrapData = ({ data }) => data.data ?? data

const bomService = {
  async list(params = {}) {
    const response = await api.get(bomUrl(), { params })
    return unwrap(response)
  },

  async get(bomId) {
    return unwrapData(await api.get(bomUrl(bomId)))
  },

  async create(payload) {
    return unwrapData(await api.post(bomUrl(), payload))
  },

  async update(bomId, payload) {
    return unwrapData(await api.put(bomUrl(bomId), payload))
  },

  async remove(bomId) {
    return unwrap(await api.delete(bomUrl(bomId)))
  },

  async activate(bomId, payload = {}) {
    return unwrapData(await api.post(`${bomUrl(bomId)}/activate`, payload))
  },

  async deactivate(bomId) {
    return unwrapData(await api.post(`${bomUrl(bomId)}/deactivate`))
  },

  async versions(bomId) {
    return unwrapData(await api.get(versionUrl(bomId)))
  },

  async createVersion(bomId, payload) {
    return unwrapData(await api.post(versionUrl(bomId), payload))
  },

  async updateVersion(bomId, versionId, payload) {
    return unwrapData(await api.put(versionUrl(bomId, versionId), payload))
  },

  async activateVersion(bomId, versionId) {
    return unwrapData(await api.post(`${versionUrl(bomId, versionId)}/activate`))
  },

  async deactivateVersion(bomId, versionId) {
    return unwrapData(await api.post(`${versionUrl(bomId, versionId)}/deactivate`))
  },

  async items(bomId, versionId) {
    return unwrapData(await api.get(itemUrl(bomId, versionId)))
  },

  async createItem(bomId, versionId, payload) {
    return unwrapData(await api.post(itemUrl(bomId, versionId), payload))
  },

  async updateItem(bomId, versionId, itemId, payload) {
    return unwrapData(await api.put(itemUrl(bomId, versionId, itemId), payload))
  },

  async removeItem(bomId, versionId, itemId) {
    return unwrap(await api.delete(itemUrl(bomId, versionId, itemId)))
  },

  async calculate(bomId, versionId, orderQuantity) {
    return unwrapData(await api.post(`${versionUrl(bomId, versionId)}/calculate`, { order_quantity: orderQuantity }))
  },
}

export default bomService
