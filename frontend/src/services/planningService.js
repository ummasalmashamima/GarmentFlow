import api from './api'

function unwrap(response) {
  return response.data?.data ?? response.data
}

const planningService = {
  listForecasts: (params = {}) => api.get('/planning/forecasts', { params }).then((response) => response.data),
  previewForecast: (payload) => api.post('/planning/forecasts/preview', payload).then(unwrap),
  createForecast: (payload) => api.post('/planning/forecasts', payload).then(unwrap),
  getForecast: (id) => api.get(`/planning/forecasts/${id}`).then(unwrap),
  updateForecast: (id, payload) => api.put(`/planning/forecasts/${id}`, payload).then(unwrap),
  activateForecast: (id) => api.post(`/planning/forecasts/${id}/activate`).then(unwrap),

  listSupplyPlans: (params = {}) => api.get('/planning/supply-plans', { params }).then((response) => response.data),
  previewSupplyPlan: (payload) => api.post('/planning/supply-plans/preview', payload).then(unwrap),
  generateSupplyPlans: (payload) => api.post('/planning/supply-plans/generate', payload).then((response) => response.data),
  getSupplyPlan: (id) => api.get(`/planning/supply-plans/${id}`).then(unwrap),
  recalculateSupplyPlan: (id, payload) => api.post(`/planning/supply-plans/${id}/recalculate`, payload).then(unwrap),

  listMaterialRequirements: (params = {}) => api.get('/planning/material-requirements', { params }).then((response) => response.data),
  previewMaterialRequirements: (payload) => api.post('/planning/material-requirements/preview', payload).then(unwrap),
  generateMaterialRequirements: (payload) => api.post('/planning/material-requirements/generate', payload).then(unwrap),
  getMaterialRequirementRun: (id) => api.get(`/planning/material-requirements/${id}`).then(unwrap),
}

export default planningService
