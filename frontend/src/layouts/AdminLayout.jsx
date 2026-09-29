import { ChevronRight, ChevronsUpDown, KeyRound, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Search, X } from 'lucide-react'
import { Suspense, useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import CommandPalette from '@/components/CommandPalette'
import Logo from '@/components/Logo'
import Avatar from '@/components/ui/Avatar'
import PageLoader from '@/components/ui/PageLoader'
import useAuth from '@/hooks/useAuth'
import cn from '@/utils/cn'
import { adminMenu } from './adminMenu'

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)
const COLLAPSE_KEY = 'admin.sidebarCollapsed'

function readCollapsed() {
  try {
    return localStorage.getItem(COLLAPSE_KEY) === '1'
  } catch {
    return false
  }
}

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

function UserMenu({ collapsed }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    const close = (event) => !ref.current?.contains(event.target) && setOpen(false)
    const onKey = (event) => event.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', onKey)
    }
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
        className={cn(
          'flex w-full items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-slate-100',
          open && 'bg-slate-100',
          collapsed && 'justify-center',
        )}
        aria-expanded={open}
        aria-haspopup="menu"
        title={collapsed ? user.name : undefined}
      >
        <Avatar name={user.name} size="sm" />
        {!collapsed && (
          <>
            <span className="min-w-0 flex-1 leading-tight">
              <span className="block truncate text-[13px] font-medium text-slate-800">{user.name}</span>
              <span className="block truncate text-xs text-slate-500">{user.role_label}</span>
            </span>
            <ChevronsUpDown className="size-3.5 shrink-0 text-slate-400" />
          </>
        )}
      </button>
      {open && (
        <div
          role="menu"
          className="absolute bottom-full left-0 z-50 mb-1.5 w-60 animate-scale-in rounded-lg border border-slate-200 bg-white p-1 shadow-pop"
        >
          <div className="px-2.5 py-2">
            <p className="truncate text-[13px] font-medium text-slate-800">{user.name}</p>
            <p className="truncate text-xs text-slate-500">{user.email}</p>
          </div>
          <div className="my-1 h-px bg-slate-100" />
          <Link
            role="menuitem"
            to="/admin/profil"
            onClick={() => setOpen(false)}
            className="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-slate-700 hover:bg-slate-100"
          >
            <KeyRound className="size-4 text-slate-400" /> Akun & password
          </Link>
          <button
            role="menuitem"
            type="button"
            onClick={handleLogout}
            className="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-red-600 hover:bg-red-50"
          >
            <LogOut className="size-4" /> Keluar
          </button>
        </div>
      )}
    </div>
  )
}

function Sidebar({ collapsed, onToggleCollapse, onNavigate, onClose, onSearch }) {
  return (
    <div className="flex h-full flex-col">
      <div className={cn('flex h-14 shrink-0 items-center gap-2 px-3', collapsed ? 'justify-center' : 'justify-between')}>
        <Link to="/admin" onClick={onNavigate} className="min-w-0 rounded-md px-1">
          <Logo compact iconOnly={collapsed} />
        </Link>
        {onClose ? (
          <button type="button" onClick={onClose} className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 lg:hidden" aria-label="Tutup menu">
            <X className="size-4" />
          </button>
        ) : (
          !collapsed && (
            <button
              type="button"
              onClick={onToggleCollapse}
              className="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
              aria-label="Ciutkan sidebar"
              title="Ciutkan sidebar"
            >
              <PanelLeftClose className="size-4" />
            </button>
          )
        )}
      </div>

      <div className="px-3 pb-2">
        <button
          type="button"
          onClick={onSearch}
          className={cn(
            'flex h-8 w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[13px] text-slate-400 transition-colors hover:border-slate-300 hover:bg-white hover:text-slate-600',
            collapsed && 'justify-center px-0',
          )}
          aria-label="Cari menu atau staff"
          title={collapsed ? `Cari (${isMac ? '⌘' : 'Ctrl'} K)` : undefined}
        >
          <Search className="size-3.5 shrink-0" />
          {!collapsed && (
            <>
              <span className="flex-1 text-left">Cari…</span>
              <kbd className="rounded border border-slate-200 bg-white px-1 font-sans text-[10px] text-slate-400">{isMac ? '⌘' : 'Ctrl'} K</kbd>
            </>
          )}
        </button>
      </div>

      <nav className="flex-1 space-y-4 overflow-y-auto px-3 py-2">
        {adminMenu.map((group, index) => (
          <div key={group.label}>
            {collapsed ? (
              index > 0 && <div className="mx-auto mb-1.5 h-px w-5 bg-slate-200" aria-hidden="true" />
            ) : (
              <p className="mb-1 px-2 text-[11px] font-medium text-slate-400">{group.label}</p>
            )}
            <ul className="space-y-px">
              {group.items.map(({ to, label, icon: Icon, end }) => (
                <li key={to}>
                  <NavLink
                    to={to}
                    end={end}
                    onClick={onNavigate}
                    title={collapsed ? label : undefined}
                    aria-label={collapsed ? label : undefined}
                    className={({ isActive }) =>
                      cn(
                        'group relative flex h-8 items-center gap-2.5 rounded-lg px-2 text-[13px] font-medium transition-colors',
                        collapsed && 'justify-center px-0',
                        isActive
                          ? 'bg-brand-50 text-brand-700'
                          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {isActive && !collapsed && <span className="absolute top-2 bottom-2 -left-3 w-0.5 rounded-r-full bg-brand-500" aria-hidden="true" />}
                        <Icon className={cn('size-4 shrink-0', isActive ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600')} />
                        {!collapsed && <span className="truncate">{label}</span>}
                      </>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      <div className="border-t border-slate-100 p-2">
        {collapsed && !onClose && (
          <button
            type="button"
            onClick={onToggleCollapse}
            className="mb-1 flex h-8 w-full items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
            aria-label="Lebarkan sidebar"
            title="Lebarkan sidebar"
          >
            <PanelLeftOpen className="size-4" />
          </button>
        )}
        <UserMenu collapsed={collapsed} />
      </div>
    </div>
  )
}

/**
 * Layout admin: sidebar terang yang bisa diciutkan (desktop) / laci geser (HP), dan panel konten
 * yang menempel di sebelahnya. Top bar berisi breadcrumb; pencarian cepat lewat Ctrl/⌘ + K.
 */
export default function AdminLayout() {
  const location = useLocation()
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(readCollapsed)
  const panelRef = useRef(null)
  const crumb = currentMenu(location.pathname)
  const isDetail = crumb && location.pathname !== crumb.item.to
  const closeDrawer = () => setDrawerOpen(false)

  function toggleCollapse() {
    setCollapsed((value) => {
      try {
        localStorage.setItem(COLLAPSE_KEY, value ? '0' : '1')
      } catch {
        // Penyimpanan diblokir: pilihan hanya berlaku selama sesi ini.
      }
      return !value
    })
  }

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

  const openSearch = () => {
    closeDrawer()
    setPaletteOpen(true)
  }

  return (
    <div className="min-h-screen bg-canvas lg:bg-white">
      {/* Laci menu (HP) */}
      {drawerOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 animate-fade-in bg-slate-900/40" onClick={closeDrawer} aria-hidden="true" />
          <aside className="absolute inset-y-0 left-0 w-72 animate-drawer-in bg-white shadow-pop">
            <Sidebar onNavigate={closeDrawer} onClose={closeDrawer} onSearch={openSearch} />
          </aside>
        </div>
      )}

      {/* Sidebar tetap (desktop) */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-30 hidden transition-[width] duration-200 ease-out lg:block',
          collapsed ? 'w-[60px]' : 'w-60',
        )}
      >
        <Sidebar collapsed={collapsed} onToggleCollapse={toggleCollapse} onSearch={openSearch} />
      </aside>

      <div className={cn('transition-[padding] duration-200 ease-out lg:py-2 lg:pr-2', collapsed ? 'lg:pl-[60px]' : 'lg:pl-60')}>
        <div
          ref={panelRef}
          className="min-h-screen bg-canvas lg:h-[calc(100dvh-1rem)] lg:min-h-0 lg:overflow-y-auto lg:rounded-xl lg:border lg:border-slate-200"
        >
          <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200/70 bg-canvas/85 px-4 backdrop-blur-md sm:px-6">
            <button
              type="button"
              onClick={() => setDrawerOpen(true)}
              className="-ml-1.5 rounded-md p-1.5 text-slate-600 hover:bg-slate-200/60 lg:hidden"
              aria-label="Buka menu"
            >
              <Menu className="size-5" />
            </button>

            <nav aria-label="Breadcrumb" className="flex min-w-0 items-center gap-1.5 text-[13px]">
              {crumb ? (
                <>
                  <span className="hidden text-slate-400 sm:inline">{crumb.group.label}</span>
                  <ChevronRight className="hidden size-3.5 text-slate-300 sm:block" />
                  {isDetail ? (
                    <>
                      <Link to={crumb.item.to} className="truncate text-slate-500 hover:text-slate-900">
                        {crumb.item.label}
                      </Link>
                      <ChevronRight className="size-3.5 text-slate-300" />
                      <span className="font-medium text-slate-900">Detail</span>
                    </>
                  ) : (
                    <span className="truncate font-medium text-slate-900">{crumb.item.label}</span>
                  )}
                </>
              ) : (
                <span className="font-medium text-slate-900">Akun</span>
              )}
            </nav>

            <div className="ml-auto flex items-center gap-1">
              <button
                type="button"
                onClick={openSearch}
                className="rounded-md p-1.5 text-slate-500 hover:bg-slate-200/60 lg:hidden"
                aria-label="Cari menu atau staff"
              >
                <Search className="size-4" />
              </button>
            </div>
          </header>

          <main className="px-4 py-6 sm:px-6 lg:px-8">
            <Suspense fallback={<PageLoader />}>
              <div key={location.pathname} className="animate-rise">
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
