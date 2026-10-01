import { AlertTriangle, Download, ScanSearch, SkipForward, UserCheck } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useParams } from 'react-router'
import Alert from '@/components/ui/Alert'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import PageHeader from '@/components/ui/PageHeader'
import SegmentedControl from '@/components/ui/SegmentedControl'
import Spinner from '@/components/ui/Spinner'
import StatCard from '@/components/ui/StatCard'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getPeriodLogs } from '@/services/scheduleService'
import cn from '@/utils/cn'
import { formatDateRange, formatShortDate } from '@/utils/format'

const FILTERS = [
  { value: 'all', label: 'Semua slot' },
  { value: 'skipped', label: 'Ada staff dilewati' },
  { value: 'understaffed', label: 'Kurang staff' },
]

const ACTION_BADGE = {
  assigned: 'green',
  skipped: 'amber',
  understaffed: 'red',
}

/** Unduh log sebagai CSV (pemisah titik koma agar langsung rapi di Excel berbahasa Indonesia). */
function downloadCsv(logs, period) {
  const header = ['Langkah', 'Tanggal', 'Hari', 'Jam', 'Pos', 'Posisi pointer', 'Staff', 'Hasil', 'Alasan', 'Keterangan']
  const rows = logs.map((log) => [
    log.step,
    log.slot?.date ?? '',
    log.slot?.day_label ?? '',
    log.slot ? `${log.slot.start_time}-${log.slot.end_time}` : '',
    log.slot?.post_name ?? '',
    log.pointer_position ?? '',
    log.staff?.name ?? '',
    log.action_label,
    log.reason_label ?? '',
    log.message,
  ])
  const escape = (value) => `"${String(value).replaceAll('"', '""')}"`
  const csv = [header, ...rows].map((row) => row.map(escape).join(';')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['﻿', csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `log-round-robin_${period.start_date}_${period.end_date}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

export default function SchedulerLog() {
  const { id } = useParams()
  const { data, loading, error } = useFetch(() => getPeriodLogs(id), [id])
  const [filter, setFilter] = useState('all')

  const groups = useMemo(() => {
    if (!data) return []
    const map = new Map()
    data.data
      .filter((log) => log.slot)
      .forEach((log) => {
        if (!map.has(log.slot.id)) map.set(log.slot.id, { slot: log.slot, steps: [] })
        map.get(log.slot.id).steps.push(log)
      })
    return [...map.values()]
  }, [data])

  const backProps = { backTo: `/admin/jadwal/${id}`, backLabel: 'Kembali ke jadwal' }

  if (!data) {
    return (
      <>
        <PageHeader title="Log algoritma Round Robin" {...backProps} />
        {loading ? (
          <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
            <Spinner className="size-5 text-brand-600" /> Memuat log…
          </div>
        ) : (
          <Alert variant="error">{getErrorMessage(error)}</Alert>
        )}
      </>
    )
  }

  const { summary, period } = data
  const queue = data.data.find((log) => log.action === 'queue')
  const visible = groups.filter(({ steps }) => {
    if (filter === 'skipped') return steps.some((step) => step.action === 'skipped')
    if (filter === 'understaffed') return steps.some((step) => step.action === 'understaffed')
    return true
  })
  const reasons = Object.entries(summary.skip_reasons ?? {})
  const reasonLabels = { class_conflict: 'bentrok kuliah', max_weekly_hours: 'batas jam', already_assigned: 'sudah di slot', shift_conflict: 'di pos lain' }

  return (
    <>
      <PageHeader
        {...backProps}
        title="Log algoritma Round Robin"
        description={`Periode ${formatDateRange(period.start_date, period.end_date)} — setiap langkah pengecekan pointer dicatat sebagai bukti proses penjadwalan.`}
        actions={
          <Button variant="secondary" icon={Download} onClick={() => downloadCsv(data.data, period)}>
            Unduh CSV
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <StatCard icon={ScanSearch} tone="blue" label="Total pengecekan" value={summary.checks} hint="Staff yang ditunjuk pointer" />
        <StatCard icon={UserCheck} tone="green" label="Ditugaskan" value={summary.assigned} />
        <StatCard
          icon={SkipForward}
          tone="amber"
          label="Dilewati"
          value={summary.skipped}
          hint={reasons.map(([reason, count]) => `${count} ${reasonLabels[reason] ?? reason}`).join(' · ')}
        />
        <StatCard icon={AlertTriangle} tone={summary.understaffed ? 'brand' : 'gray'} label="Slot kurang staff" value={summary.understaffed} />
      </div>

      {queue && (
        <Card className="mt-6" title="Langkah 0 — antrian melingkar awal" description="Pointer mulai dari posisi 1 dan bergerak maju setiap kali satu staff dicek.">
          <ol className="flex flex-wrap gap-2">
            {queue.meta.names.map((name, index) => (
              <li key={queue.meta.queue[index]} className="flex items-center gap-2 rounded-full bg-slate-100 py-1 pr-3 pl-1 text-sm">
                <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-semibold text-slate-600 shadow-card">
                  {index + 1}
                </span>
                {name}
              </li>
            ))}
          </ol>
        </Card>
      )}

      <div className="mt-6 mb-4">
        <SegmentedControl aria-label="Filter slot" options={FILTERS} value={filter} onChange={setFilter} />
      </div>

      <div className="space-y-4">
        {visible.map(({ slot, steps }) => {
          const assigned = steps.filter((step) => step.action === 'assigned').length
          const short = steps.some((step) => step.action === 'understaffed')
          return (
            <Card
              key={slot.id}
              title={`${formatShortDate(slot.date)} · ${slot.start_time}–${slot.end_time}${slot.post_name ? ` · ${slot.post_name}` : ''}`}
              actions={
                <Badge color={short ? 'red' : 'green'}>
                  {short ? 'Kurang staff' : 'Terpenuhi'} {assigned}/{slot.required_staff}
                </Badge>
              }
              bodyClassName="p-0"
            >
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 text-xs whitespace-nowrap text-slate-500">
                    <tr>
                      <th className="px-4 py-2 font-medium">Langkah</th>
                      <th className="px-4 py-2 font-medium">Pointer</th>
                      <th className="px-4 py-2 font-medium">Staff</th>
                      <th className="px-4 py-2 font-medium">Hasil</th>
                      <th className="px-4 py-2 font-medium">Keterangan</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {steps.map((step) => (
                      <tr key={step.id} className={cn(step.action === 'understaffed' && 'bg-red-50/60')}>
                        <td className="px-4 py-2 text-slate-500 tabular-nums">#{step.step}</td>
                        <td className="px-4 py-2 text-slate-500 tabular-nums">{step.pointer_position ?? '—'}</td>
                        <td className="px-4 py-2 font-medium whitespace-nowrap text-slate-800">{step.staff?.name ?? '—'}</td>
                        <td className="px-4 py-2">
                          <Badge color={ACTION_BADGE[step.action]}>{step.reason_label ?? step.action_label}</Badge>
                        </td>
                        <td className="min-w-64 px-4 py-2 text-slate-600">{step.message}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          )
        })}
      </div>
    </>
  )
}
