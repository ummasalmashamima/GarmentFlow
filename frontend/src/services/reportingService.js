import api from './api'

const reportingService = {
  async report(report, params = {}) {
    const { data } = await api.get(`/reports/${report}`, { params })
    return data.data
  },
  async exportReport(report, params = {}) {
    const response = await api.get(`/reports/${report}/export`, { params, responseType: 'blob' })
    return response
  },
  async dashboard(key, params = {}) {
    const { data } = await api.get(`/dashboards/${key}`, { params })
    return data.data
  },
  async alerts(params = {}) {
    const { data } = await api.get('/alerts', { params })
    return data.data
  },
  async refreshAlerts() {
    const { data } = await api.post('/alerts/refresh')
    return data.data
  },
  async setAlertState(id, read) {
    const { data } = await api.put(`/alerts/${id}/state`, { read })
    return data.data
  },
}

export default reportingService
