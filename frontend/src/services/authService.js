import { TOKEN_STORAGE_KEY } from '../constants/auth'
import api from './api'

const authService = {
  async login(credentials) {
    const { data } = await api.post('/auth/login', credentials)
    sessionStorage.setItem(TOKEN_STORAGE_KEY, data.token)
    return data.user
  },

  async me() {
    const { data } = await api.get('/auth/me')
    return data.data ?? data
  },

  async logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      sessionStorage.removeItem(TOKEN_STORAGE_KEY)
    }
  },
}

export default authService
