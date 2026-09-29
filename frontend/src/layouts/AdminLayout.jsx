import { CalendarDays, ChevronDown, ChevronRight, KeyRound, LogOut, Menu, Search, Sparkles, X } from 'lucide-react'
import { Suspense, useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import CommandPalette from '@/components/CommandPalette'
import Logo from '@/components/Logo'
import Avatar from '@/components/ui/Avatar'
import PageLoader from '@/components/ui/PageLoader'
import useAuth from '@/hooks/useAuth'
import cn from '@/utils/cn'
import { formatLongDate } from '@/utils/format'
import { adminMenu, menuTints } from './adminMenu'

const crumbDot = {
  rose: 'bg-rose-500',
  sky: 'bg-sky-500',
  violet: 'bg-violet-500',
  emerald: 'bg-emerald-500',
  amber: 'bg-amber-500',
  pink: 'bg-pink-500',
  slate: 'bg-slate-400',
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)

/** Menu yang paling cocok dengan URL saat ini, untuk breadcrumb. */
function currentMenu(pathname) {
  let best = null
  adminMenu.forEach((group) =>
    group.items.forEach((item) => {
      const match = item.end ? pathname === item.to : pathname.startsWith(item.to)
      if (match && (!best || item.to.length > best.item.to.length)) best = { group, item }
    }),
  )
  return best
}

function Sidebar({ onNavigate, onClose }) {
  return (
    <div className="bg-shell flex h-full flex-col lg:bg-none lg:bg-transparent">
      <div className="flex h-[72px] shrink-0 items-center justify-between px-5">
        <Logo inverted />
        {onClose && (
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Tutup menu">
            <X className="size-5" />
          </button>
        )}
      </div>

      <nav className="flex-1 space-y-4 overflow-y-auto px-3 pb-3 [scrollbar-color:rgb(51_65_85)_transparent]">
        {adminMenu.map((group) => (
          <div key={group.label}>
            <p className="mb-1 px-3 text-[10.5px] font-bold tracking-[0.14em] text-white/35 uppercase">{group.label}</p>
            <ul className="space-y-0.5">
              {group.items.map(({ to, label, icon: Icon, end }) => (
                <li key={to}>
                  <NavLink
                    to={to}
                    end={end}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'group flex items-center gap-3 rounded-xl px-2 py-1.5 text-sm font-medium transition-all duration-200',
                        isActive ? 'bg-brand-gradient text-white shadow-glow' : 'text-slate-300/80 hover:bg-white/[0.06] hover:text-white',
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        <span
                          className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-lg transition duration-200',
                            isActive ? 'bg-white/20 text-white' : cn(menuTints[group.tint], 'group-hover:scale-110'),
                          )}
                        >
                          <Icon className="size-[17px]" />
                        </span>
                        {label}
                      </>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      <div className="relative m-3 hidden overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600/40 to-fuchsia-600/20 p-4 ring-1 ring-white/10 [@media(min-height:880px)]:block">
        <div className="absolute -top-6 -right-6 size-20 rounded-full bg-fuchsia-500/30 blur-2xl" aria-hidden="true" />
        <p className="relative flex items-center gap-1.5 text-xs font-semibold text-white">
          <Sparkles className="size-3.5 text-amber-300" /> Pencarian cepat
        </p>
        <p className="relative mt-1 text-xs leading-relaxed text-white/60">
          Tekan <kbd className="rounded bg-white/15 px-1 font-sans text-white">{isMac ? '⌘' : 'Ctrl'} K</kbd> untuk membuka menu
          atau mencari staff dari mana saja.
        </p>
      </div>
    </div>
  )
}

function UserMenu() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    const close = (event) => !ref.current?.contains(event.target) && setOpen(false)
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [open])

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-2.5 rounded-xl bg-white py-1 pr-2 pl-1 shadow-soft ring-1 ring-slate-200/80 transition hover:ring-slate-300"
        aria-expanded={open}
        aria-haspopup="menu"
      >
        <Avatar name={user.name} size="sm" />
        <span className="hidden text-left leading-tight md:block">
          <span className="block text-sm font-semibold text-slate-800">{user.name}</span>
          <span className="block text-xs text-slate-500">{user.role_label}</span>
        </span>
        <ChevronDown className={cn('size-4 text-slate-400 transition', open && 'rotate-180')} />
      </button>
      {open && (
        <div role="menu" className="absolute right-0 mt-2 w-56 animate-scale-in overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-1.5 shadow-lift">
          <div className="px-3 py-2">
            <p className="truncate text-sm font-semibold text-slate-800">{user.name}</p>
            <p className="truncate text-xs text-slate-500">{user.email}</p>
          </div>
          <div className="my-1 h-px bg-slate-100" />
          <Link role="menuitem" to="/admin/profil" onClick={() => setOpen(false)} className="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
            <KeyRound className="size-4 text-slate-400" /> Akun & password
          </Link>
          <button role="menuitem" type="button" onClick={handleLogout} className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50">
            <LogOut className="size-4" /> Keluar
          </button>
        </div>
      )}
    </div>
  )
}

/**
 * Layout admin: bingkai gelap dengan sidebar berwarna; konten berada di panel terang yang menjorok
 * (desktop) / laci geser (HP). Top bar berisi breadcrumb,
 * pencarian cepat (Ctrl+K), menu pengguna, dan transisi halus antarhalaman.
 */
export default function AdminLayout() {
  const location = useLocation()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const closeSidebar = () => setSidebarOpen(false)
  const crumb = currentMenu(location.pathname)
  const isDetail = crumb && location.pathname !== crumb.item.to
  const panelRef = useRef(null)

  // Di desktop panel konten punya scroll sendiri: kembali ke atas setiap pindah halaman.
  useEffect(() => {
    panelRef.current?.scrollTo(0, 0)
    window.scrollTo(0, 0)
  }, [location.pathname])

  useEffect(() => {
    const handleKey = (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        setPaletteOpen((value) => !value)
      }
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [])

  return (
    <div className="min-h-screen lg:bg-shell">
      {sidebarOpen && <div className="fixed inset-0 z-40 animate-fade-in bg-slate-950/60 backdrop-blur-sm lg:hidden" onClick={closeSidebar} aria-hidden="true" />}

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 w-72 transition-transform duration-300 ease-out lg:z-30 lg:w-[248px] lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <Sidebar onNavigate={closeSidebar} onClose={closeSidebar} />
      </aside>

      <div className="lg:py-2 lg:pr-2 lg:pl-[248px]">
        <div
          ref={panelRef}
          className="bg-mesh min-h-screen lg:h-[calc(100dvh-1rem)] lg:min-h-0 lg:overflow-y-auto lg:rounded-[26px] lg:shadow-2xl lg:shadow-black/40"
        >
          <header className="glass sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-white/80 px-4 sm:px-6">
            <button type="button" onClick={() => setSidebarOpen(true)} className="-ml-1 rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
              <Menu className="size-5" />
            </button>

            <nav aria-label="Breadcrumb" className="hidden min-w-0 items-center gap-1.5 text-sm sm:flex">
              {crumb ? (
                <>
                  <span className={cn('size-2 rounded-full', crumbDot[crumb.group.tint])} aria-hidden="true" />
                  <span className="text-slate-400">{crumb.group.label}</span>
                  <ChevronRight className="size-3.5 text-slate-300" />
                  {isDetail ? (
                    <>
                      <Link to={crumb.item.to} className="font-medium text-slate-500 hover:text-slate-900">
                        {crumb.item.label}
                      </Link>
                      <ChevronRight className="size-3.5 text-slate-300" />
                      <span className="font-semibold text-slate-900">Detail</span>
                    </>
                  ) : (
                    <span className="font-semibold text-slate-900">{crumb.item.label}</span>
                  )}
                </>
              ) : (
                <span className="font-semibold text-slate-900">Akun</span>
              )}
            </nav>

            <div className="ml-auto flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPaletteOpen(true)}
                className="group flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm text-slate-500 shadow-soft ring-1 ring-slate-200/80 transition hover:text-slate-700 hover:ring-violet-300 md:w-72"
              >
                <Search className="size-4 transition group-hover:text-violet-500" />
                <span className="hidden md:inline">Cari menu atau staff…</span>
                <kbd className="ml-auto hidden rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-500 md:inline">
                  {isMac ? '⌘' : 'Ctrl'} K
                </kbd>
              </button>
              <span className="hidden items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-medium text-slate-500 shadow-soft ring-1 ring-slate-200/80 xl:inline-flex">
                <CalendarDays className="size-3.5 text-violet-500" /> {formatLongDate()}
              </span>
              <UserMenu />
            </div>
          </header>

          <main className="px-4 py-5 sm:px-6 lg:py-6">
            <Suspense fallback={<PageLoader />}>
              <div key={location.pathname} className="animate-slide-up">
                <Outlet />
              </div>
            </Suspense>
          </main>
        </div>
      </div>

      {paletteOpen && <CommandPalette onClose={() => setPaletteOpen(false)} />}
    </div>
  )
}
