import { CalendarDays, CircleUser, House, ScanLine, Wallet } from 'lucide-react'
import { Suspense } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import Logo from '@/components/Logo'
import Avatar from '@/components/ui/Avatar'
import PageLoader from '@/components/ui/PageLoader'
import useAuth from '@/hooks/useAuth'
import cn from '@/utils/cn'

const navItems = [
  { to: '/staff', label: 'Beranda', icon: House, end: true },
  { to: '/staff/jadwal-shift', label: 'Jadwal', icon: CalendarDays },
  { to: '/staff/presensi', label: 'Presensi', icon: ScanLine, primary: true },
  { to: '/staff/gaji', label: 'Gaji', icon: Wallet },
  { to: '/staff/profil', label: 'Akun', icon: CircleUser },
]

/**
 * Layout staff dirancang untuk HP: header ringkas + menu navigasi di bawah layar.
 */
export default function StaffLayout() {
  const { user } = useAuth()
  const location = useLocation()

  return (
    <div className="min-h-screen bg-canvas">
      <header className="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur-md">
        <div className="mx-auto flex h-14 max-w-xl items-center justify-between px-4">
          <Logo compact />
          <Link to="/staff/profil" aria-label="Akun saya" className="rounded-full transition-opacity hover:opacity-80">
            <Avatar name={user.name} size="sm" />
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-xl px-4 pt-5 pb-28">
        <Suspense fallback={<PageLoader />}>
          <div key={location.pathname} className="animate-rise">
            <Outlet />
          </div>
        </Suspense>
      </main>

      <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md">
        <ul className="mx-auto grid h-16 max-w-md grid-cols-5">
          {navItems.map(({ to, label, icon: Icon, end, primary }) => (
            <li key={to}>
              <NavLink
                to={to}
                end={end}
                className={({ isActive }) =>
                  cn(
                    'relative flex h-full flex-col items-center justify-center gap-1 text-[11px] font-medium transition-colors',
                    isActive ? 'text-slate-900' : 'text-slate-400 hover:text-slate-600',
                  )
                }
              >
                {({ isActive }) =>
                  primary ? (
                    <>
                      <span
                        className={cn(
                          '-mt-5 flex size-12 items-center justify-center rounded-full bg-brand-600 text-white shadow-pop ring-4 ring-white transition-transform active:scale-95',
                          isActive && 'bg-brand-700',
                        )}
                      >
                        <Icon className="size-5" />
                      </span>
                      {label}
                    </>
                  ) : (
                    <>
                      <span
                        className={cn('absolute top-0 h-0.5 w-8 rounded-b-full bg-brand-600 transition-opacity', isActive ? 'opacity-100' : 'opacity-0')}
                        aria-hidden="true"
                      />
                      <Icon className="size-5" strokeWidth={isActive ? 2.2 : 1.8} />
                      {label}
                    </>
                  )
                }
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>
    </div>
  )
}
