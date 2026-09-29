import { Navigate, Outlet, useLocation } from 'react-router'
import useAuth from '@/hooks/useAuth'
import { ROLE_HOME } from '@/utils/constants'

/**
 * Hanya untuk pengguna yang sudah login dengan role tertentu.
 * Belum login → ke /login (halaman asal diingat). Role berbeda → ke beranda role-nya.
 */
export default function ProtectedRoute({ role }) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) return <Navigate to="/login" replace state={{ from: location }} />
  if (role && user.role !== role) return <Navigate to={ROLE_HOME[user.role]} replace />

  return <Outlet />
}
