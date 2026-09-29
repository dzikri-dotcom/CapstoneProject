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
  { to: '/staff/presensi', label: 'Presensi', description: 'Scan QR', icon: ScanLine, tint: 'from-rose-500 to-orange-500 shadow-rose-500/30' },
  { to: '/staff/jadwal-shift', label: 'Jadwal shift', description: 'Shift saya', icon: CalendarDays, tint: 'from-violet-500 to-fuchsia-500 shadow-violet-500/30' },
  { to: '/staff/jadwal-kuliah', label: 'Jadwal kuliah', description: 'Isi jam kuliah', icon: BookOpen, tint: 'from-sky-500 to-indigo-500 shadow-sky-500/30' },
  { to: '/staff/riwayat', label: 'Riwayat', description: 'Kehadiran saya', icon: History, tint: 'from-emerald-500 to-teal-500 shadow-emerald-500/30' },
  { to: '/staff/gaji', label: 'Rekap gaji', description: 'Slip & rincian', icon: Wallet, tint: 'from-amber-400 to-orange-500 shadow-amber-500/30' },
  { to: '/staff/profil', label: 'Akun', description: 'Profil saya', icon: CircleUser, tint: 'from-pink-500 to-rose-500 shadow-pink-500/30' },
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
      <Link to="/staff/presensi" className="block rounded-2xl border-2 border-brand-200 bg-white p-4 shadow-xs">
        <p className="text-xs font-semibold tracking-wide text-brand-700 uppercase">{checkedIn ? 'Sedang bertugas' : 'Shift sedang berlangsung'}</p>
        <p className="mt-1 text-xl font-semibold text-slate-900 tabular-nums">
          {data.current.start_time}–{data.current.end_time}
        </p>
        <p className="mt-1 text-sm text-slate-600">
          {checkedIn
            ? `Check-in ${checkedIn}. Jangan lupa check-out sebelum ${data.current.check_out_closes}.`
            : `Segera check-in — lewat ${data.current.late_after} dianggap terlambat.`}
        </p>
        <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">
          Buka presensi <ChevronRight className="size-4" />
        </span>
      </Link>
    )
  }

  return (
    <div className="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-soft">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Shift berikutnya</p>
        {data.next && (
          <span className="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
            {countdown(data.next.date, data.next.start_time, now)}
          </span>
        )}
      </div>
      {data.next ? (
        <>
          <p className="mt-1 text-xl font-semibold text-slate-900 tabular-nums">
            {data.next.start_time}–{data.next.end_time}
          </p>
          <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-600">
            <Clock className="size-4 text-slate-400" />
            {formatShortDate(data.next.date)} · check-in dibuka {data.next.check_in_opens}
          </p>
        </>
      ) : (
        <p className="mt-1 text-sm text-slate-600">Belum ada shift terjadwal yang dipublikasikan.</p>
      )}
    </div>
  )
}

export default function Home() {
  const { user } = useAuth()
  const profile = user.staff_profile
  const { data, loading, error } = useFetch(getStaffDashboard)

  return (
    <div className="space-y-5">
      <section className="bg-brand-gradient relative overflow-hidden rounded-[26px] p-5 text-white shadow-glow-lg">
        <div className="bg-dots absolute inset-0 [mask-image:linear-gradient(to_left,black,transparent)]" aria-hidden="true" />
        <div className="absolute -top-16 -right-10 size-48 animate-float rounded-full bg-fuchsia-600/50 blur-3xl" aria-hidden="true" />
        <div className="absolute -bottom-20 -left-10 size-40 animate-float rounded-full bg-amber-300/40 blur-3xl [animation-delay:-6s]" aria-hidden="true" />
        <div className="absolute -right-12 -bottom-12 size-40 rounded-full border-[22px] border-white/10" aria-hidden="true" />
        <div className="relative">
          <p className="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/25">{formatLongDate()}</p>
          <h1 className="mt-3 text-2xl font-extrabold tracking-tight">Halo, {firstName(user.name)}! 👋</h1>
          <p className="mt-1 text-sm text-white/85">
            {profile?.nim} · {profile?.study_program}
          </p>
        </div>
      </section>

      {!data && loading && (
        <div className="flex justify-center py-6">
          <Spinner className="size-5 text-brand-600" />
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && (
        <>
          <ShiftCard data={data} />

          <section className="relative overflow-hidden rounded-[22px] bg-gradient-to-br from-violet-600 via-indigo-600 to-sky-500 p-4 text-white shadow-lg shadow-indigo-500/30">
            <div className="bg-dots absolute inset-0 opacity-60 [mask-image:linear-gradient(to_left,black,transparent)]" aria-hidden="true" />
            <div className="absolute -top-12 -right-12 size-36 rounded-full bg-white/15" aria-hidden="true" />
            <p className="relative text-xs font-semibold tracking-wide text-white/75 uppercase">Estimasi gaji {data.month.label}</p>
            <p className="relative mt-1 text-3xl font-extrabold tracking-tight tabular-nums">
              {formatRupiah(data.month.estimated_amount)}
            </p>
            <div className="relative mt-3 grid grid-cols-3 gap-2 text-center">
              <div className="rounded-xl bg-white/15 p-2 ring-1 ring-white/20 backdrop-blur">
                <p className="font-bold tabular-nums">{formatDuration(data.month.paid_minutes, { short: true })}</p>
                <p className="text-[11px] text-white/75">jam dibayar</p>
              </div>
              <div className="rounded-xl bg-white/15 p-2 ring-1 ring-white/20 backdrop-blur">
                <p className="font-bold tabular-nums">{data.month.shifts_done}</p>
                <p className="text-[11px] text-white/75">shift selesai</p>
              </div>
              <div className="rounded-xl bg-white/15 p-2 ring-1 ring-white/20 backdrop-blur">
                <p className="font-bold tabular-nums">{data.month.upcoming_shifts}</p>
                <p className="text-[11px] text-white/75">shift mendatang</p>
              </div>
            </div>
            <p className="relative mt-3 text-xs text-white/75">
              Kehadiran: {data.month.on_time} tepat waktu · {data.month.late} terlambat · {data.month.absent} tidak hadir. Estimasi
              dapat berubah sampai gaji difinalkan koordinator.
            </p>
          </section>
        </>
      )}

      <section>
        <h2 className="mb-3 text-sm font-bold text-slate-800">Menu cepat</h2>
        <div className="stagger grid grid-cols-2 gap-3">
          {shortcuts.map(({ to, label, description, icon: Icon, tint }) => (
            <Link
              key={to}
              to={to}
              className="group flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-200/60 transition duration-300 hover:-translate-y-0.5 hover:shadow-lift active:scale-[0.98]"
            >
              <span className={`flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md transition duration-300 group-hover:scale-110 group-hover:rotate-6 ${tint}`}>
                <Icon className="size-5" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-slate-800">{label}</span>
                <span className="block truncate text-xs text-slate-500">{description}</span>
              </span>
            </Link>
          ))}
        </div>
      </section>
    </div>
  )
}
