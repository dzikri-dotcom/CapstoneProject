import { BookOpen, CalendarDays, ChevronRight, CircleUser, Clock, History, ScanLine, Wallet } from 'lucide-react'
import { Link } from 'react-router'
import Alert from '@/components/ui/Alert'
import Spinner from '@/components/ui/Spinner'
import useAuth from '@/hooks/useAuth'
import useFetch from '@/hooks/useFetch'
import useNow from '@/hooks/useNow'
import { getErrorMessage } from '@/services/api'
import { getStaffDashboard } from '@/services/reportService'
import { firstName, formatDuration, formatLongDate, formatRupiah, formatShortDate } from '@/utils/format'

const shortcuts = [
  { to: '/staff/presensi', label: 'Presensi', description: 'Scan QR', icon: ScanLine },
  { to: '/staff/jadwal-shift', label: 'Jadwal shift', description: 'Shift saya', icon: CalendarDays },
  { to: '/staff/jadwal-kuliah', label: 'Jadwal kuliah', description: 'Isi jam kuliah', icon: BookOpen },
  { to: '/staff/riwayat', label: 'Riwayat', description: 'Kehadiran saya', icon: History },
  { to: '/staff/gaji', label: 'Rekap gaji', description: 'Slip & rincian', icon: Wallet },
  { to: '/staff/profil', label: 'Akun', description: 'Profil saya', icon: CircleUser },
]

/** "dalam 2 hari 3 jam" / "dalam 45 menit" menuju jam mulai shift. */
function countdown(date, time, now) {
  const [year, month, day] = date.split('-').map(Number)
  const [hour, minute] = time.split(':').map(Number)
  const diff = Math.max(0, Math.round((new Date(year, month - 1, day, hour, minute) - now) / 60000))
  const days = Math.floor(diff / 1440)
  const hours = Math.floor((diff % 1440) / 60)
  const minutes = diff % 60
  if (days) return `dalam ${days} hari${hours ? ` ${hours} jam` : ''}`
  if (hours) return `dalam ${hours} jam${minutes ? ` ${minutes} menit` : ''}`
  return `dalam ${minutes} menit`
}

function ShiftCard({ data }) {
  const now = useNow(30000)
  if (data.current) {
    const checkedIn = data.current.attendance?.check_in_time
    return (
      <Link
        to="/staff/presensi"
        className="group block rounded-xl border border-brand-200 bg-white p-4 shadow-card transition-colors hover:border-brand-300"
      >
        <p className="flex items-center gap-2 text-xs font-medium text-brand-700">
          <span className="relative flex size-2">
            <span className="absolute inset-0 animate-ping rounded-full bg-brand-400 opacity-60" />
            <span className="relative size-2 rounded-full bg-brand-600" />
          </span>
          {checkedIn ? 'Sedang bertugas' : 'Shift sedang berlangsung'}
        </p>
        <p className="mt-1.5 text-lg font-semibold text-slate-900 tabular-nums">
          {data.current.start_time}–{data.current.end_time}
          {data.current.post_name && <span className="text-sm font-normal text-slate-500"> · {data.current.post_name}</span>}
        </p>
        <p className="mt-0.5 text-[13px] text-slate-600">
          {checkedIn
            ? `Check-in ${checkedIn}. Jangan lupa check-out sebelum ${data.current.check_out_closes}.`
            : `Segera check-in — lewat ${data.current.late_after} dianggap terlambat, ditutup ${data.current.check_in_closes}.`}
        </p>
        <span className="mt-3 inline-flex items-center gap-1 text-[13px] font-medium text-brand-700">
          Buka presensi <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
        </span>
      </Link>
    )
  }

  return (
    <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-card">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-medium text-slate-500">Shift berikutnya</p>
        {data.next && (
          <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700">
            {countdown(data.next.date, data.next.start_time, now)}
          </span>
        )}
      </div>
      {data.next ? (
        <>
          <p className="mt-1.5 text-lg font-semibold text-slate-900 tabular-nums">
            {data.next.start_time}–{data.next.end_time}
            {data.next.post_name && <span className="text-sm font-normal text-slate-500"> · {data.next.post_name}</span>}
          </p>
          <p className="mt-0.5 flex items-center gap-1.5 text-[13px] text-slate-600">
            <Clock className="size-3.5 text-slate-400" />
            {formatShortDate(data.next.date)} · check-in dibuka {data.next.check_in_opens}
          </p>
        </>
      ) : (
        <p className="mt-1.5 text-sm text-slate-600">Belum ada shift terjadwal yang dipublikasikan.</p>
      )}
    </div>
  )
}

function Metric({ label, value }) {
  return (
    <div className="px-3 first:pl-0 last:pr-0">
      <p className="text-[15px] font-semibold text-slate-900 tabular-nums">{value}</p>
      <p className="text-xs text-slate-500">{label}</p>
    </div>
  )
}

export default function Home() {
  const { user } = useAuth()
  const profile = user.staff_profile
  const { data, loading, error } = useFetch(getStaffDashboard)

  return (
    <div className="space-y-4">
      <section className="pt-1 pb-2">
        <p className="text-[13px] text-slate-500">{formatLongDate()}</p>
        <h1 className="mt-0.5 text-xl font-semibold tracking-tight text-slate-900">Halo, {firstName(user.name)}</h1>
        <p className="text-[13px] text-slate-500">
          {profile?.nim} · {profile?.study_program}
        </p>
      </section>

      {!data && loading && (
        <div className="flex justify-center py-6">
          <Spinner className="size-5 text-slate-400" />
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && (
        <>
          <ShiftCard data={data} />

          <section className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-card">
            <p className="text-xs font-medium text-slate-500">Estimasi gaji {data.month.label}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">
              {formatRupiah(data.month.estimated_amount)}
            </p>
            <div className="mt-3 flex divide-x divide-slate-100 border-t border-slate-100 pt-3">
              <Metric label="jam dibayar" value={formatDuration(data.month.paid_minutes, { short: true })} />
              <Metric label="shift selesai" value={data.month.shifts_done} />
              <Metric label="shift mendatang" value={data.month.upcoming_shifts} />
            </div>
            <p className="mt-3 text-xs text-slate-500">
              Kehadiran: {data.month.on_time} tepat waktu · {data.month.late} terlambat · {data.month.absent} tidak hadir. Estimasi
              dapat berubah sampai gaji difinalkan koordinator.
            </p>
          </section>
        </>
      )}

      <section>
        <h2 className="mb-2 px-1 text-xs font-medium text-slate-500">Menu</h2>
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-card">
          {shortcuts.map(({ to, label, description, icon: Icon }) => (
            <li key={to}>
              <Link to={to} className="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50 active:bg-slate-100">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                  <Icon className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-medium text-slate-800">{label}</span>
                  <span className="block truncate text-xs text-slate-500">{description}</span>
                </span>
                <ChevronRight className="size-4 text-slate-300 transition-transform group-hover:translate-x-0.5" />
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
