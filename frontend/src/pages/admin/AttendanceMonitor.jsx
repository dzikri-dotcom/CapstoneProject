import { ChevronLeft, ChevronRight, ClipboardCheck, PencilLine, QrCode } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import AttendanceBadges from '@/components/attendance/AttendanceBadges'
import CorrectionModal from '@/components/attendance/CorrectionModal'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import EmptyState from '@/components/ui/EmptyState'
import fieldClass from '@/components/ui/fieldClass'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { listAttendances } from '@/services/attendanceService'
import cn from '@/utils/cn'
import { formatLongDate } from '@/utils/format'
import { addDaysISO, todayISO } from '@/utils/time'

const FILTERS = [
  { key: 'total', label: 'Semua shift', match: () => true },
  { key: 'on_time', label: 'Tepat waktu', match: (item) => item.attendance?.status === 'on_time' },
  { key: 'late', label: 'Terlambat', match: (item) => item.attendance?.status === 'late' },
  { key: 'absent', label: 'Tidak hadir', match: (item) => ['absent', 'missed'].includes(item.state) },
  { key: 'in_progress', label: 'Sedang bertugas', match: (item) => item.state === 'in_progress' },
  { key: 'missing_checkout', label: 'Belum check-out', match: (item) => item.state === 'missing_checkout' },
  { key: 'waiting', label: 'Menunggu check-in', match: (item) => ['upcoming', 'open'].includes(item.state) },
]

function TimeCell({ time, method }) {
  if (!time) return <span className="text-slate-400">—</span>
  return (
    <span className="tabular-nums">
      {time}
      {method === 'manual' && <span className="ml-1 text-xs text-slate-400">(manual)</span>}
    </span>
  )
}

export default function AttendanceMonitor() {
  const [date, setDate] = useState(todayISO())
  const [filter, setFilter] = useState('total')
  const [version, setVersion] = useState(0)
  const [correcting, setCorrecting] = useState(null)
  const { data, loading, error } = useFetch(() => listAttendances({ date }), [date, version])

  const items = data?.data ?? []
  const summary = data?.summary
  const visible = items.filter(FILTERS.find((item) => item.key === filter).match)

  return (
    <>
      <PageHeader
        title="Monitoring Presensi"
        description="Pantau check-in/check-out staff per tanggal dan koreksi presensi secara manual bila diperlukan."
        actions={
          <Link
            to="/admin/qr"
            className="inline-flex items-center gap-2 rounded-xl bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition hover:shadow-glow active:scale-[0.97]"
          >
            <QrCode className="size-4" /> Buka layar QR
          </Link>
        }
      />

      <div className="mb-6 flex flex-wrap items-center gap-2">
        <Button variant="secondary" size="icon" onClick={() => setDate(addDaysISO(date, -1))} aria-label="Hari sebelumnya">
          <ChevronLeft className="size-4" />
        </Button>
        <div className="w-44">
          <input
            type="date"
            aria-label="Tanggal"
            value={date}
            onChange={(event) => event.target.value && setDate(event.target.value)}
            className={cn(fieldClass(false), 'py-2')}
          />
        </div>
        <Button variant="secondary" size="icon" onClick={() => setDate(addDaysISO(date, 1))} aria-label="Hari berikutnya">
          <ChevronRight className="size-4" />
        </Button>
        {date !== todayISO() && (
          <Button variant="ghost" size="sm" onClick={() => setDate(todayISO())}>
            Hari ini
          </Button>
        )}
        <span className="ml-1 text-sm font-medium text-slate-600">{formatLongDate(date)}</span>
      </div>

      {summary && (
        <div className="mb-4 flex flex-wrap gap-2">
          {FILTERS.map((item) => (
            <button
              key={item.key}
              type="button"
              onClick={() => setFilter(item.key)}
              className={cn(
                'rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition',
                filter === item.key ? 'bg-brand-gradient text-white ring-transparent shadow-glow' : 'bg-white text-slate-600 ring-slate-200 hover:ring-slate-400',
              )}
            >
              {item.label}
              <span className={cn('ml-1.5 tabular-nums', filter === item.key ? 'text-white/70' : 'text-slate-400')}>
                {summary[item.key]}
              </span>
            </button>
          ))}
        </div>
      )}

      <div className="rounded-2xl border border-slate-200/70 bg-white shadow-soft">
        {error && !data && (
          <div className="p-4">
            <Alert variant="error">{getErrorMessage(error)}</Alert>
          </div>
        )}
        {!data && loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
            <Spinner className="size-5 text-brand-600" /> Memuat presensi…
          </div>
        )}
        {data && visible.length === 0 && (
          <EmptyState
            icon={ClipboardCheck}
            title={items.length ? 'Tidak ada data untuk filter ini' : 'Tidak ada shift pada tanggal ini'}
            description={items.length ? undefined : 'Hanya shift dari jadwal yang sudah dipublikasikan yang tampil di sini.'}
          />
        )}

        {visible.length > 0 && (
          <div className={cn('transition-opacity', loading && 'opacity-60')}>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
                  <tr>
                    <th className="px-4 py-3 font-medium">Shift</th>
                    <th className="px-4 py-3 font-medium">Staff</th>
                    <th className="px-4 py-3 font-medium">Check-in</th>
                    <th className="px-4 py-3 font-medium">Check-out</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    <th className="px-4 py-3 font-medium">Catatan koreksi</th>
                    <th className="px-4 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {visible.map((item) => (
                    <tr key={item.assignment_id} className="hover:bg-slate-50/60">
                      <td className="px-4 py-3 font-medium whitespace-nowrap text-slate-700 tabular-nums">
                        {item.start_time}–{item.end_time}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap text-slate-800">{item.staff?.name}</td>
                      <td className="px-4 py-3">
                        <TimeCell time={item.attendance?.check_in_time} method={item.attendance?.check_in_method} />
                      </td>
                      <td className="px-4 py-3">
                        <TimeCell time={item.attendance?.check_out_time} method={item.attendance?.check_out_method} />
                      </td>
                      <td className="px-4 py-3">
                        <AttendanceBadges item={item} />
                      </td>
                      <td className="max-w-64 px-4 py-3 text-xs text-slate-500">
                        {item.attendance?.correction_note ? (
                          <>
                            {item.attendance.correction_note}
                            {item.attendance.corrected_by && <span className="block text-slate-400">— {item.attendance.corrected_by}</span>}
                          </>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <Button variant="ghost" size="sm" icon={PencilLine} onClick={() => setCorrecting(item)}>
                          Koreksi
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <ul className="divide-y divide-slate-100 md:hidden">
              {visible.map((item) => (
                <li key={item.assignment_id} className="space-y-2 p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-medium text-slate-900">{item.staff?.name}</p>
                      <p className="text-xs text-slate-500 tabular-nums">
                        Shift {item.start_time}–{item.end_time} · masuk {item.attendance?.check_in_time ?? '—'} · pulang{' '}
                        {item.attendance?.check_out_time ?? '—'}
                      </p>
                    </div>
                    <Button variant="ghost" size="sm" icon={PencilLine} onClick={() => setCorrecting(item)}>
                      Koreksi
                    </Button>
                  </div>
                  <AttendanceBadges item={item} />
                  {item.attendance?.correction_note && <p className="text-xs text-slate-500">{item.attendance.correction_note}</p>}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {correcting && (
        <CorrectionModal
          item={correcting}
          onClose={() => setCorrecting(null)}
          onSaved={() => {
            setCorrecting(null)
            setVersion((value) => value + 1)
          }}
        />
      )}
    </>
  )
}
