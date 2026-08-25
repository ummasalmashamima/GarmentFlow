import api from './api'

const unwrap = (response) => response.data?.data ?? response.data
const list = async (url, params = {}) => (await api.get(url, { params })).data

const productionService = {
  plans: {
    list: (params) => list('/production/plans', params),
    get: async (id) => unwrap(await api.get(`/production/plans/${id}`)),
    create: async (payload) => unwrap(await api.post('/production/plans', payload)),
    update: async (id, payload) => unwrap(await api.put(`/production/plans/${id}`, payload)),
    approve: async (id) => unwrap(await api.post(`/production/plans/${id}/approve`)),
    status: async (id, status) => unwrap(await api.post(`/production/plans/${id}/status`, { status })),
  },
  orders: {
    list: (params) => list('/production/orders', params),
    get: async (id) => unwrap(await api.get(`/production/orders/${id}`)),
    create: async (payload) => unwrap(await api.post('/production/orders', payload)),
    availability: async (id) => unwrap(await api.get(`/production/orders/${id}/availability`)),
    start: async (id) => unwrap(await api.post(`/production/orders/${id}/start`)),
    status: async (id, status) => unwrap(await api.post(`/production/orders/${id}/status`, { status })),
    consume: async (id, payload) => unwrap(await api.post(`/production/orders/${id}/consume`, payload)),
    progress: async (id, payload) => unwrap(await api.post(`/production/orders/${id}/progress`, payload)),
    complete: async (id, payload) => unwrap(await api.post(`/production/orders/${id}/complete`, payload)),
  },
  consumptions: {
    list: (params) => list('/production/consumptions', params),
  },
  progress: {
    list: (params) => list('/production/progress', params),
  },
  finishedGoods: {
    list: (params) => list('/production/finished-goods', params),
    get: async (id) => unwrap(await api.get(`/production/finished-goods/${id}`)),
  },
  history: {
    list: (params) => list('/production/history', params),
  },
}

export default productionService
