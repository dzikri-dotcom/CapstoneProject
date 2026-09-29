import { AlertTriangle, ArrowRight, CalendarClock, CalendarRange, QrCode, UserCheck, Users, Wallet } from 'lucide-react'
import { Link } from 'react-router'
import AttendanceBadges from '@/components/attendance/AttendanceBadges'
import { CHART } from '@/components/charts/chartTheme'
import Alert from '@/components/ui/Alert'
import Avatar from '@/components/ui/Avatar'
import Card from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import Skeleton from '@/components/ui/Skeleton'
import StatCard from '@/components/ui/StatCard'
import useAuth from '@/hooks/useAuth'
import useFetch from '@/hooks/useFetch'
import useNow from '@/hooks/useNow'
import { getErrorMessage } from '@/services/api'
import { getAdminDashboard } from '@/services/reportService'
import { firstName, formatDateRange, formatLongDate, formatShortDate } from '@/utils/format'

function CardLink({ to, children }) {
  return (
    <Link to={to} className="group inline-flex items-center gap-1 text-[13px] font-medium text-slate-500 transition-colors hover:text-slate-900">
      {children} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
    </Link>
  )
}

function AttendanceBreakdown({ month }) {
  const parts = [
    ['Tepat waktu', month.on_time, CHART.onTime],
    ['Terlambat', month.late, CHART.late],
    ['Tidak hadir', month.absent, CHART.absent],
  ]

  if (!month.total) {
    return <p className="text-sm text-slate-500">Belum ada shift yang berjalan bulan ini.</p>
  }

  return (
    <div>
      <p className="text-3xl font-semibold tracking-tight text-slate-900 tabular-nums">{month.rate.toLocaleString('id-ID')}%</p>
      <p className="text-sm text-slate-500">tingkat kehadiran dari {month.total} shift</p>
      <div className="mt-4 flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Komposisi kehadiran bulan ini">
        {parts.map(([label, value, color]) =>
          value ? <span key={label} style={{ width: `${(value / month.total) * 100}%`, background: color }} /> : null,
        )}
      </div>
      <ul className="mt-3 space-y-1 text-sm">
        {parts.map(([label, value, color]) => (
          <li key={label} className="flex items-center gap-2">
            <span className="size-2.5 rounded-sm" style={{ background: color }} />
            <span className="text-slate-600">{label}</span>
            <span className="ml-auto font-medium text-slate-800 tabular-nums">{value}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

function greeting(hour) {
  if (hour < 11) return 'pagi'
  if (hour < 15) return 'siang'
  if (hour < 18) return 'sore'
  return 'malam'
}

const secondaryLink =
  'inline-flex h-9 items-center gap-2 rounded-lg bg-white px-3.5 text-sm font-medium text-slate-700 shadow-card ring-1 ring-slate-200 ring-inset transition-colors hover:bg-slate-50 hover:text-slate-900'

const clockFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' })

function Hero({ name, data }) {
  const now = useNow(1000)

  return (
    <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p className="flex items-center gap-2 text-[13px] text-slate-500">
          {formatLongDate(now)}
          <span className="size-1 rounded-full bg-slate-300" aria-hidden="true" />
          <span className="font-medium text-slate-700 tabular-nums">{clockFormatter.format(now)} WIB</span>
        </p>
        <h1 className="mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
          Selamat {greeting(now.getHours())}, {firstName(name)}
        </h1>
        {data ? (
          <p className="mt-1 text-sm text-slate-500">
            {data.on_duty.length} staff sedang bertugas · {data.today_shift_count} shift hari ini ·{' '}
            <span className={data.understaffed.length ? 'font-medium text-amber-700' : undefined}>
              {data.understaffed.length} slot kurang staff
            </span>
          </p>
        ) : (
          <Skeleton className="mt-2 h-4 w-72" />
        )}
      </div>
      <div className="flex flex-wrap gap-2">
        <Link to="/admin/jadwal" className={secondaryLink}>
          <CalendarRange className="size-4 text-slate-400" /> Jadwal shift
        </Link>
        <Link to="/admin/penggajian" className={secondaryLink}>
          <Wallet className="size-4 text-slate-400" /> Penggajian
        </Link>
        <Link
          to="/admin/qr"
          className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-600 px-3.5 text-sm font-medium text-white shadow-card transition-colors hover:bg-brand-700"
        >
          <QrCode className="size-4" /> Buka layar QR
        </Link>
      </div>
    </div>
  )
}

export default function Dashboard() {
  const { user } = useAuth()
  const { data, loading, error } = useFetch(getAdminDashboard)

  if (!data) {
    return (
      <>
        <Hero name={user.name} />
        {loading ? (
          <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            {[1, 2, 3, 4].map((key) => (
              <Skeleton key={key} className="h-[106px] rounded-xl" />
            ))}
          </div>
        ) : (
          <Alert variant="error">{getErrorMessage(error)}</Alert>
        )}
      </>
    )
  }

  return (
    <>
      <Hero name={user.name} data={data} />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <StatCard icon={Users} label="Staff aktif" value={data.active_staff} tone="blue" />
        <StatCard icon={CalendarClock} label="Shift hari ini" value={data.today_shift_count} hint="slot yang dipublikasikan" tone="violet" />
        <StatCard icon={UserCheck} label="Sedang bertugas" value={data.on_duty.length} tone="green" />
        <StatCard icon={AlertTriangle} label="Slot kurang staff" value={data.understaffed.length} hint="14 hari ke depan" tone={data.understaffed.length ? 'amber' : 'gray'} />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card title="Sedang bertugas" actions={<CardLink to="/admin/presensi">Monitoring</CardLink>}>
            {data.on_duty.length ? (
              <ul className="grid gap-3 sm:grid-cols-2">
                {data.on_duty.map((staff) => (
                  <li key={staff.name} className="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                    <Avatar name={staff.name} size="sm" />
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-900">{staff.name}</p>
                      <p className="text-xs text-slate-500 tabular-nums">
                        Shift {staff.shift} · masuk {staff.check_in_time}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-slate-500">Tidak ada staff yang sedang check-in saat ini.</p>
            )}
          </Card>

          <Card title="Shift hari ini" bodyClassName="p-0">
            {data.today_slots.length ? (
              <ul className="divide-y divide-slate-100">
                {data.today_slots.map((slot) => (
                  <li key={slot.start_time} className="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-start">
                    <p className="w-28 shrink-0 pt-0.5 font-medium text-slate-700 tabular-nums">
                      {slot.start_time}–{slot.end_time}
                    </p>
                    <ul className="flex-1 space-y-1.5">
                      {slot.staff.map((item) => (
                        <li key={item.assignment_id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                          <span className="text-slate-800">{item.staff?.name}</span>
                          <AttendanceBadges item={item} />
                        </li>
                      ))}
                      {slot.staff.length < slot.required_staff && (
                        <li className="text-xs font-medium text-red-600">Kurang {slot.required_staff - slot.staff.length} staff</li>
                      )}
                    </ul>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyState icon={CalendarClock} title="Tidak ada shift hari ini" description="Belum ada jadwal dipublikasikan untuk hari ini." />
            )}
          </Card>
        </div>

        <div className="space-y-6">
          <Card title="Kehadiran bulan ini" actions={<CardLink to="/admin/laporan/kehadiran">Rekap</CardLink>}>
            <AttendanceBreakdown month={data.month_attendance} />
          </Card>

          <Card title="Slot kurang staff" actions={<CardLink to="/admin/jadwal">Jadwal</CardLink>}>
            {data.understaffed.length ? (
              <ul className="space-y-2">
                {data.understaffed.slice(0, 6).map((slot) => (
                  <li key={slot.id}>
                    <Link
                      to={`/admin/jadwal/${slot.schedule_period_id}`}
                      className="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                    >
                      <span className="text-slate-700">
                        {formatShortDate(slot.date)} · {slot.start_time}–{slot.end_time}
                      </span>
                      <span className="text-xs font-medium text-red-600">kurang {slot.missing}</span>
                    </Link>
                  </li>
                ))}
                {data.understaffed.length > 6 && <li className="px-2 text-xs text-slate-500">+{data.understaffed.length - 6} slot lainnya</li>}
              </ul>
            ) : (
              <p className="text-sm text-slate-500">Semua slot 14 hari ke depan sudah terisi.</p>
            )}
          </Card>

          <Card title="Penjadwalan">
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Periode terbaru</dt>
                <dd className="text-right font-medium text-slate-800">
                  {data.latest_period ? formatDateRange(data.latest_period.start_date, data.latest_period.end_date) : '—'}
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Antrian berikutnya mulai dari</dt>
                <dd className="text-right font-medium text-slate-800">{data.next_pointer ?? 'staff pertama'}</dd>
              </div>
            </dl>
          </Card>
        </div>
      </div>
    </>
  )
}
