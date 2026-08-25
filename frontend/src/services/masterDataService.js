import api from './api'

const resourceUrl = (resource) => `/master-data/${resource}`

const masterDataService = {
  async list(resource, params = {}) {
    const { data } = await api.get(resourceUrl(resource), { params })
    return data
  },

  async get(resource, id) {
    const { data } = await api.get(`${resourceUrl(resource)}/${id}`)
    return data.data ?? data
  },

  async options(resource) {
    const { data } = await api.get(`${resourceUrl(resource)}/options`)
    return data.data ?? data
  },

  async create(resource, payload) {
    const { data } = await api.post(resourceUrl(resource), payload)
    return data.data ?? data
  },

  async update(resource, id, payload) {
    const { data } = await api.put(`${resourceUrl(resource)}/${id}`, payload)
    return data.data ?? data
  },

  async remove(resource, id) {
    const { data } = await api.delete(`${resourceUrl(resource)}/${id}`)
    return data
  },
}

export default masterDataService
