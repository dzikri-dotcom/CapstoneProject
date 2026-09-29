import { ROLE_HOME } from '@/utils/constants'

/**
 * Tujuan setelah login: kembali ke halaman yang tadi dibuka (misal link QR presensi)
 * jika halaman itu milik role pengguna, selain itu ke beranda role-nya.
 */
export function homeFor(user, from) {
  const home = ROLE_HOME[user.role] ?? '/'
  if (from?.pathname?.startsWith(home)) return `${from.pathname}${from.search ?? ''}`
  return home
}
