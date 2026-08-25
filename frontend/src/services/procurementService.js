import api from './api'

const unwrap = (response) => response.data?.data ?? response.data
const list = async (url, params = {}) => (await api.get(url, { params })).data

const procurementService = {
  requisitions: {
    list: (params) => list('/procurement/requisitions', params),
    get: async (id) => unwrap(await api.get(`/procurement/requisitions/${id}`)),
    create: async (payload) => unwrap(await api.post('/procurement/requisitions', payload)),
    update: async (id, payload) => unwrap(await api.put(`/procurement/requisitions/${id}`, payload)),
    submit: async (id, remarks) => unwrap(await api.post(`/procurement/requisitions/${id}/submit`, { remarks })),
    approve: async (id, remarks) => unwrap(await api.post(`/procurement/requisitions/${id}/approve`, { remarks })),
    reject: async (id, remarks) => unwrap(await api.post(`/procurement/requisitions/${id}/reject`, { remarks })),
    convert: async (id, payload) => unwrap(await api.post(`/procurement/requisitions/${id}/convert`, payload)),
    history: async (id) => unwrap(await api.get(`/procurement/requisitions/${id}/history`)),
  },
  orders: {
    list: (params) => list('/procurement/purchase-orders', params),
    get: async (id) => unwrap(await api.get(`/procurement/purchase-orders/${id}`)),
    preview: async (payload) => unwrap(await api.post('/procurement/purchase-orders/preview', payload)),
    create: async (payload) => unwrap(await api.post('/procurement/purchase-orders', payload)),
    update: async (id, payload) => unwrap(await api.put(`/procurement/purchase-orders/${id}`, payload)),
    submit: async (id, remarks) => unwrap(await api.post(`/procurement/purchase-orders/${id}/submit`, { remarks })),
    approve: async (id, remarks) => unwrap(await api.post(`/procurement/purchase-orders/${id}/approve`, { remarks })),
    send: async (id, remarks) => unwrap(await api.post(`/procurement/purchase-orders/${id}/send`, { remarks })),
    cancel: async (id, remarks) => unwrap(await api.post(`/procurement/purchase-orders/${id}/cancel`, { remarks })),
    close: async (id, remarks) => unwrap(await api.post(`/procurement/purchase-orders/${id}/close`, { remarks })),
    history: async (id) => unwrap(await api.get(`/procurement/purchase-orders/${id}/history`)),
  },
  receipts: {
    list: (params) => list('/procurement/goods-receipts', params),
    get: async (id) => unwrap(await api.get(`/procurement/goods-receipts/${id}`)),
    create: async (payload) => unwrap(await api.post('/procurement/goods-receipts', payload)),
    receive: async (id, remarks) => unwrap(await api.post(`/procurement/goods-receipts/${id}/receive`, { remarks })),
    accept: async (id, remarks) => unwrap(await api.post(`/procurement/goods-receipts/${id}/accept`, { remarks })),
    post: async (id, remarks) => unwrap(await api.post(`/procurement/goods-receipts/${id}/post`, { remarks })),
    history: async (id) => unwrap(await api.get(`/procurement/goods-receipts/${id}/history`)),
  },
  history: (params) => list('/procurement/history', params),
}

export default procurementService
