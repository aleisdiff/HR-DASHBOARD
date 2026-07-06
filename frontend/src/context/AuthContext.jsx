/* eslint-disable react-refresh/only-export-components */
import PropTypes from 'prop-types'
import { createContext, useContext, useEffect, useState } from 'react'
import api, { ensureCsrfCookie } from '../lib/axios.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  const fetchUser = async () => {
    const response = await api.get('/me')
    setUser(response.data.user)
    return response.data.user
  }

  const login = async (credentials) => {
    await ensureCsrfCookie()
    await api.post('/login', credentials)
    return fetchUser()
  }

  const logout = async () => {
    await api.post('/logout')
    setUser(null)
  }

  useEffect(() => {
    let isActive = true

    const bootstrap = async () => {
      try {
        const response = await api.get('/me')

        if (isActive) {
          setUser(response.data.user)
        }
      } catch {
        if (isActive) {
          setUser(null)
        }
      } finally {
        if (isActive) {
          setLoading(false)
        }
      }
    }

    bootstrap()

    return () => {
      isActive = false
    }
  }, [])

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        logout,
        refreshUser: fetchUser,
        isAuthenticated: Boolean(user),
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

AuthProvider.propTypes = {
  children: PropTypes.node.isRequired,
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }

  return context
}