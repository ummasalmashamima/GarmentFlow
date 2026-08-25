import { createContext, useContext, useEffect, useState } from 'react'
import { TOKEN_STORAGE_KEY } from '../constants/auth'
import authService from '../services/authService'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // Restore the session on load (and after a full page refresh) using the
  // Sanctum token already stored by authService, instead of trusting
  // unauthenticated data cached in localStorage.
  useEffect(() => {
    let active = true

    async function restoreSession() {
      const token = sessionStorage.getItem(TOKEN_STORAGE_KEY)

      if (!token) {
        if (active) setLoading(false)
        return
      }

      try {
        const currentUser = await authService.me()
        if (active) setUser(currentUser)
      } catch (error) {
        sessionStorage.removeItem(TOKEN_STORAGE_KEY)
      } finally {
        if (active) setLoading(false)
      }
    }

    restoreSession()

    return () => { active = false }
  }, [])

  // If any request comes back 401, api.js clears the token and fires this
  // event so every part of the app reacts by dropping the stale session.
  useEffect(() => {
    const handleUnauthorized = () => setUser(null)

    window.addEventListener('garmentflow:unauthorized', handleUnauthorized)

    return () => window.removeEventListener('garmentflow:unauthorized', handleUnauthorized)
  }, [])

  const login = async (credentials) => {
    const authenticatedUser = await authService.login(credentials)
    setUser(authenticatedUser)
    return authenticatedUser
  }

  const logout = async () => {
    try {
      await authService.logout()
    } finally {
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        logout,
        isAuthenticated: !!user,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}

export default AuthContext
