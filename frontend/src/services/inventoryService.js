import api from './api'

const unwrap = (response) => response.data?.data ?? response.data
const list = async (url, params = {}) => (await api.get(url, { params })).data

const inventoryService = {
  balances: {
    list: (params) => list('/inventory', params),
    get: async (id) => unwrap(await api.get(`/inventory/${id}`)),
    summary: async (params) => unwrap(await api.get('/inventory/summary', { params })),
    warehouse: (id, params) => list(`/inventory/warehouse/${id}/stock`, params),
    location: (id, params) => list(`/inventory/location/${id}/stock`, params),
  },
  movements: {
    stockIn: async (payload) => unwrap(await api.post('/inventory/stock-in', payload)),
    stockOut: async (payload) => unwrap(await api.post('/inventory/stock-out', payload)),
    history: (params) => list('/inventory/history', params),
  },
  transfers: {
    list: (params) => list('/inventory/transfers', params),
    get: async (id) => unwrap(await api.get(`/inventory/transfers/${id}`)),
    create: async (payload) => unwrap(await api.post('/inventory/transfers', payload)),
    history: async (id) => unwrap(await api.get(`/inventory/transfers/${id}/history`)),
  },
  adjustments: {
    list: (params) => list('/inventory/adjustments', params),
    get: async (id) => unwrap(await api.get(`/inventory/adjustments/${id}`)),
    create: async (payload) => unwrap(await api.post('/inventory/adjustments', payload)),
    history: async (id) => unwrap(await api.get(`/inventory/adjustments/${id}/history`)),
  },
}

export default inventoryService
