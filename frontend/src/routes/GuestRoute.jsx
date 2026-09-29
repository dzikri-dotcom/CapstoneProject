import { Navigate, Outlet, useLocation } from 'react-router'
import useAuth from '@/hooks/useAuth'
import { homeFor } from './redirect'

/**
 * Hanya untuk pengguna yang belum login (halaman login).
 * Begitu login berhasil, otomatis diarahkan ke halaman tujuan.
 */
export default function GuestRoute() {
  const { user } = useAuth()
  const location = useLocation()

  if (user) return <Navigate to={homeFor(user, location.state?.from)} replace />

  return <Outlet />
}
