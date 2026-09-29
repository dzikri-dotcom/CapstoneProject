import { CalendarPlus, CornerDownLeft, Search, UserRound, Wallet, QrCode, KeyRound } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router'
import Spinner from '@/components/ui/Spinner'
import useDebounce from '@/hooks/useDebounce'
import useFetch from '@/hooks/useFetch'
import { adminMenu } from '@/layouts/adminMenu'
import { listStaff } from '@/services/staffService'
import cn from '@/utils/cn'

const QUICK_ACTIONS = [
  { group: 'Aksi cepat', label: 'Buka layar QR presensi', to: '/admin/qr', icon: QrCode },
  { group: 'Aksi cepat', label: 'Buat & generate jadwal shift', to: '/admin/jadwal', icon: CalendarPlus },
  { group: 'Aksi cepat', label: 'Hitung gaji periode ini', to: '/admin/penggajian', icon: Wallet },
  { group: 'Aksi cepat', label: 'Ganti password akun saya', to: '/admin/profil', icon: KeyRound },
]

const PAGES = adminMenu.flatMap((group) =>
  group.items.map((item) => ({ group: 'Halaman', label: item.label, hint: group.label, to: item.to, icon: item.icon })),
)

const noStaff = () => Promise.resolve({ data: [] })

/**
 * Palet perintah (Ctrl/⌘ + K): cari halaman, aksi cepat, atau staff lalu tekan Enter.
 */
export default function CommandPalette({ onClose }) {
  const navigate = useNavigate()
  const inputRef = useRef(null)
  const listRef = useRef(null)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  const search = useDebounce(query.trim(), 250)
  const { data: staff, loading } = useFetch(
    () => (search.length >= 2 ? listStaff({ search, per_page: 5 }) : noStaff()),
    [search],
  )

  const needle = query.trim().toLowerCase()
  const matches = (item) => !needle || `${item.label} ${item.hint ?? ''}`.toLowerCase().includes(needle)
  const results = [
    ...QUICK_ACTIONS.filter(matches),
    ...PAGES.filter(matches),
    ...(search.length >= 2 ? staff?.data ?? [] : []).map((member) => ({
      group: 'Staff',
      label: member.name,
      hint: `${member.nim} · ${member.study_program}`,
      to: `/admin/staff/${member.id}`,
      icon: UserRound,
    })),
  ]

  useEffect(() => {
    inputRef.current?.focus()
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previous
    }
  }, [])

  useEffect(() => {
    listRef.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' })
  }, [active])

  function go(item) {
    onClose()
    navigate(item.to)
  }

  function handleKey(event) {
    if (event.key === 'Escape') onClose()
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActive((index) => Math.min(index + 1, results.length - 1))
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActive((index) => Math.max(index - 1, 0))
    }
    if (event.key === 'Enter' && results[active]) go(results[active])
  }

  let lastGroup = null

  return createPortal(
    <div className="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[12vh]" onKeyDown={handleKey}>
      <div className="absolute inset-0 animate-fade-in bg-slate-950/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Cari menu atau staff"
        className="relative w-full max-w-xl animate-scale-in overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10"
      >
        <div className="flex items-center gap-3 border-b border-slate-100 px-4">
          <Search className="size-5 shrink-0 text-slate-400" />
          <input
            ref={inputRef}
            value={query}
            onChange={(event) => {
              setQuery(event.target.value)
              setActive(0)
            }}
            placeholder="Cari halaman, aksi, atau nama/NIM staff…"
            className="h-14 flex-1 bg-transparent text-[15px] text-slate-800 outline-none placeholder:text-slate-400"
            role="combobox"
            aria-expanded="true"
            aria-controls="command-results"
          />
          {loading && search.length >= 2 && <Spinner className="size-4 text-slate-400" />}
          <kbd className="rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">Esc</kbd>
        </div>

        <ul id="command-results" ref={listRef} role="listbox" className="max-h-[50vh] overflow-y-auto p-2">
          {results.length === 0 && <li className="px-3 py-10 text-center text-sm text-slate-500">Tidak ada hasil untuk “{query}”.</li>}
          {results.map((item, index) => {
            const heading = item.group !== lastGroup ? item.group : null
            lastGroup = item.group
            const Icon = item.icon
            const isActive = index === active
            return (
              <li key={`${item.group}-${item.to}-${item.label}`}>
                {heading && <p className="px-3 pt-3 pb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{heading}</p>}
                <button
                  type="button"
                  role="option"
                  aria-selected={isActive}
                  data-active={isActive}
                  onMouseMove={() => setActive(index)}
                  onClick={() => go(item)}
                  className={cn(
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition',
                    isActive ? 'bg-brand-gradient text-white shadow-glow' : 'text-slate-700',
                  )}
                >
                  <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-lg', isActive ? 'bg-white/15' : 'bg-slate-100 text-slate-500')}>
                    <Icon className="size-4" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{item.label}</span>
                    {item.hint && <span className={cn('block truncate text-xs', isActive ? 'text-white/60' : 'text-slate-400')}>{item.hint}</span>}
                  </span>
                  {isActive && <CornerDownLeft className="size-4 text-white/60" />}
                </button>
              </li>
            )
          })}
        </ul>

        <div className="flex items-center gap-4 border-t border-slate-100 bg-slate-50/80 px-4 py-2.5 text-[11px] text-slate-500">
          <span><kbd className="font-sans font-semibold">↑↓</kbd> pilih</span>
          <span><kbd className="font-sans font-semibold">Enter</kbd> buka</span>
          <span className="ml-auto">Ketik minimal 2 huruf untuk mencari staff</span>
        </div>
      </div>
    </div>,
    document.body,
  )
}
