import { useCallback, useEffect, useMemo, useState } from 'react'
import { clearToken, getToken, setToken, UNAUTHORIZED_EVENT } from '@/services/api'
import * as authService from '@/services/authService'
import AuthContext from './AuthContext'

/**
 * Menyimpan pengguna yang sedang login.
 * status: 'loading' (cek token saat aplikasi dibuka) | 'ready' | 'error' (server tidak bisa dihubungi)
 */
export default function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [status, setStatus] = useState(() => (getToken() ? 'loading' : 'ready'))
  const [attempt, setAttempt] = useState(0)

  // Saat aplikasi dibuka dengan token tersimpan, ambil data pengguna dari /me.
  useEffect(() => {
    if (!getToken()) return

    let active = true
    authService
      .me()
      .then((data) => {
        if (!active) return
        setUser(data)
        setStatus('ready')
      })
      .catch((error) => {
        if (!active) return
        // 401 → token tidak berlaku (sudah dihapus interceptor), cukup tampilkan login.
        setStatus(error.response ? 'ready' : 'error')
      })

    return () => {
      active = false
    }
  }, [attempt])

  // Token kedaluwarsa di tengah pemakaian → kembali ke halaman login.
  useEffect(() => {
    const handleUnauthorized = () => setUser(null)
    window.addEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)
  }, [])

  const login = useCallback(async (email, password) => {
    const { token, user: loggedIn } = await authService.login(email, password)
    setToken(token)
    setUser(loggedIn)
    return loggedIn
  }, [])

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } catch {
      // Tetap keluar di sisi browser walaupun request gagal.
    }
    clearToken()
    setUser(null)
  }, [])

  const refreshUser = useCallback(async () => {
    setUser(await authService.me())
  }, [])

  const retry = useCallback(() => {
    setStatus('loading')
    setAttempt((value) => value + 1)
  }, [])

  const value = useMemo(
    () => ({ user, status, login, logout, refreshUser, retry }),
    [user, status, login, logout, refreshUser, retry],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
