import { History } from 'lucide-react'
import { useState } from 'react'
import AttendanceBadges from '@/components/attendance/AttendanceBadges'
import Alert from '@/components/ui/Alert'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Select from '@/components/ui/Select'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getMyAttendanceHistory } from '@/services/attendanceService'
import { formatDuration, formatShortDate } from '@/utils/format'

const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' })

/** 6 bulan terakhir: [{ value: '2026-09', label: 'September 2026' }, ...] */
function recentMonths() {
  const now = new Date()
  return Array.from({ length: 6 }, (_, index) => {
    const date = new Date(now.getFullYear(), now.getMonth() - index, 1)
    return {
      value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
      label: monthFormatter.format(date),
    }
  })
}

const statTones = {
  blue: 'bg-sky-500',
  amber: 'bg-amber-500',
  red: 'bg-red-500',
  violet: 'bg-slate-400',
}

function Stat({ label, value, tone = 'violet' }) {
  return (
    <div className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-card">
      <p className="text-lg font-semibold text-slate-900 tabular-nums">{value}</p>
      <p className="flex items-center gap-1.5 text-[11px] text-slate-500">
        <span className={`size-1.5 rounded-full ${statTones[tone]}`} aria-hidden="true" />
        {label}
      </p>
    </div>
  )
}

export default function AttendanceHistory() {
  const months = recentMonths()
  const [month, setMonth] = useState(months[0].value)
  const { data, loading, error } = useFetch(() => getMyAttendanceHistory(month), [month])
  const items = data?.data ?? []
  const summary = data?.summary

  return (
    <>
      <PageHeader title="Riwayat presensi" description="Kehadiran Anda pada shift yang sudah berjalan." />

      <Select className="mb-4" aria-label="Bulan" options={months} value={month} onChange={(event) => setMonth(event.target.value)} />

      {summary && (
        <div className="mb-5 grid grid-cols-4 gap-2">
          <Stat label="Tepat waktu" value={summary.on_time} tone="blue" />
          <Stat label="Terlambat" value={summary.late} tone="amber" />
          <Stat label="Tidak hadir" value={summary.absent} tone="red" />
          <Stat label="Jam hadir" value={formatDuration(summary.worked_minutes, { short: true })} />
        </div>
      )}

      {!data && loading && (
        <div className="flex justify-center py-12">
          <Spinner className="size-6 text-brand-600" />
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && items.length === 0 && (
        <div className="rounded-xl border border-slate-200/80 bg-white shadow-card">
          <EmptyState icon={History} title="Belum ada riwayat" description="Belum ada shift yang berjalan pada bulan ini." />
        </div>
      )}

      {items.length > 0 && (
        <ul className="space-y-2">
          {items.map((item) => (
            <li key={item.assignment_id} className="rounded-xl border border-slate-200/80 bg-white shadow-card p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-medium text-slate-900">{formatShortDate(item.date)}</p>
                  <p className="text-sm text-slate-500 tabular-nums">
                    Shift {item.start_time}–{item.end_time}
                  </p>
                </div>
                <AttendanceBadges item={item} />
              </div>
              {item.attendance?.check_in_time && (
                <p className="mt-2 text-xs text-slate-600 tabular-nums">
                  Masuk {item.attendance.check_in_time} · Pulang {item.attendance.check_out_time ?? '—'}
                  {item.attendance.worked_minutes !== null && ` · ${formatDuration(item.attendance.worked_minutes)}`}
                </p>
              )}
              {item.attendance?.correction_note && (
                <p className="mt-1 text-xs text-slate-500">Catatan koordinator: {item.attendance.correction_note}</p>
              )}
            </li>
          ))}
        </ul>
      )}
    </>
  )
}
