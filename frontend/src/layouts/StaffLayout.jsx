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
 * Layout staff dirancang untuk HP: header kaca + menu navigasi melayang di bawah layar.
 */
export default function StaffLayout() {
  const { user } = useAuth()
  const location = useLocation()

  return (
    <div className="bg-mesh min-h-screen">
      <header className="glass sticky top-0 z-30 border-b border-slate-200/60">
        <div className="mx-auto flex h-16 max-w-xl items-center justify-between px-4">
          <Logo compact />
          <Link to="/staff/profil" aria-label="Akun saya" className="rounded-full ring-2 ring-white transition hover:ring-brand-200">
            <Avatar name={user.name} size="sm" />
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-xl px-4 pt-5 pb-32">
        <Suspense fallback={<PageLoader />}>
          <div key={location.pathname} className="animate-slide-up">
            <Outlet />
          </div>
        </Suspense>
      </main>

      <nav className="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <ul className="glass mx-auto grid max-w-md grid-cols-5 rounded-3xl border border-white/70 shadow-lift ring-1 ring-slate-900/5">
          {navItems.map(({ to, label, icon: Icon, end, primary }) => (
            <li key={to}>
              <NavLink
                to={to}
                end={end}
                className={({ isActive }) =>
                  cn(
                    'relative flex flex-col items-center gap-1 pt-2.5 pb-2 text-[11px] font-semibold transition',
                    isActive ? 'text-rose-600' : 'text-slate-400 hover:text-slate-700',
                  )
                }
              >
                {({ isActive }) =>
                  primary ? (
                    <>
                      <span
                        className={cn(
                          'bg-brand-gradient -mt-7 flex size-14 items-center justify-center rounded-2xl text-white shadow-glow-lg ring-4 ring-white transition active:scale-95',
                          isActive && 'scale-105',
                        )}
                      >
                        <Icon className="size-6" />
                      </span>
                      {label}
                    </>
                  ) : (
                    <>
                      <span className={cn('flex h-7 w-12 items-center justify-center rounded-full transition-all duration-300', isActive && 'bg-gradient-to-r from-rose-100 to-orange-100')}>
                        <Icon className={cn('size-5 transition', isActive && 'scale-110')} />
                      </span>
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
