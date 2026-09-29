import { CircleCheck, CircleX, ClipboardCheck, Clock, FileSpreadsheet, Percent } from 'lucide-react'
import { useState } from 'react'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { CHART, axisProps } from '@/components/charts/chartTheme'
import ChartTooltip from '@/components/charts/ChartTooltip'
import DateRangeFilter from '@/components/reports/DateRangeFilter'
import { RANGE_PRESETS } from '@/components/reports/reportRanges'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import StatCard from '@/components/ui/StatCard'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { exportAttendanceReport, getAttendanceReport } from '@/services/reportService'
import cn from '@/utils/cn'
import { formatDateRange, formatDuration } from '@/utils/format'

const SERIES = [
  { key: 'on_time', label: 'Tepat waktu', color: CHART.onTime },
  { key: 'late', label: 'Terlambat', color: CHART.late },
  { key: 'absent', label: 'Tidak hadir', color: CHART.absent },
]

export default function AttendanceReport() {
  const toast = useToast()
  const [range, setRange] = useState(RANGE_PRESETS.thisMonth)
  const [exporting, setExporting] = useState(false)
  const { data, loading, error } = useFetch(() => getAttendanceReport(range), [range.start_date, range.end_date])

  const rows = data?.staff ?? []
  const totals = data?.totals

  async function handleExport() {
    setExporting(true)
    try {
      await exportAttendanceReport(range)
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setExporting(false)
    }
  }

  return (
    <>
      <PageHeader
        title="Rekap Kehadiran"
        description="Kehadiran per staff pada shift yang sudah berjalan (jadwal yang dipublikasikan)."
        actions={
          <Button variant="secondary" icon={FileSpreadsheet} loading={exporting} disabled={!rows.length} onClick={handleExport}>
            Ekspor Excel
          </Button>
        }
      />

      <DateRangeFilter value={range} onChange={setRange} />

      {!data && loading && (
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Memuat rekap…
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && rows.length === 0 && (
        <Card>
          <EmptyState icon={ClipboardCheck} title="Belum ada data kehadiran" description="Tidak ada shift yang sudah berjalan pada rentang tanggal ini." />
        </Card>
      )}

      {data && rows.length > 0 && (
        <div className={cn('space-y-6 transition-opacity', loading && 'opacity-60')}>
          <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <StatCard icon={Percent} tone="violet" label="Tingkat kehadiran" value={`${totals.attendance_rate.toLocaleString('id-ID')}%`} hint={`${totals.shifts} shift berjalan`} />
            <StatCard icon={CircleCheck} tone="blue" label="Tepat waktu" value={totals.on_time} />
            <StatCard icon={Clock} tone="amber" label="Terlambat" value={totals.late} hint={totals.missing_checkout ? `${totals.missing_checkout} belum check-out` : undefined} />
            <StatCard icon={CircleX} tone="brand" label="Tidak hadir" value={totals.absent} hint={`Total jam hadir ${formatDuration(totals.worked_minutes, { short: true })}`} />
          </div>

          <Card title="Kehadiran per staff" description={formatDateRange(data.from, data.to)}>
            <div style={{ height: Math.max(rows.length * 38 + 80, 220) }}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={rows} layout="vertical" margin={{ top: 4, right: 24, bottom: 4, left: 8 }} barCategoryGap={8}>
                  <CartesianGrid horizontal={false} stroke={CHART.grid} />
                  <XAxis type="number" {...axisProps} allowDecimals={false} />
                  <YAxis type="category" dataKey="name" {...axisProps} width={170} axisLine={false} />
                  <Tooltip
                    cursor={{ fill: '#f1f5f9' }}
                    content={
                      <ChartTooltip
                        title={(d) => d.name}
                        rows={(d) => [
                          ...SERIES.map((s) => [s.label, `${d[s.key]} shift`, s.color]),
                          ['Tingkat kehadiran', `${d.attendance_rate.toLocaleString('id-ID')}%`],
                        ]}
                      />
                    }
                  />
                  <Legend verticalAlign="top" align="left" iconType="square" iconSize={10} wrapperStyle={{ fontSize: 12, paddingBottom: 8 }} />
                  {SERIES.map((series, index) => (
                    <Bar
                      key={series.key}
                      dataKey={series.key}
                      name={series.label}
                      stackId="attendance"
                      fill={series.color}
                      stroke={CHART.surface}
                      strokeWidth={2}
                      maxBarSize={22}
                      radius={index === SERIES.length - 1 ? [0, 4, 4, 0] : 0}
                      isAnimationActive={false}
                    />
                  ))}
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>

          <Card title="Tabel rekap" bodyClassName="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
                  <tr>
                    <th className="px-4 py-3 font-medium">Staff</th>
                    <th className="px-4 py-3 text-right font-medium">Shift</th>
                    <th className="px-4 py-3 text-right font-medium">Tepat waktu</th>
                    <th className="px-4 py-3 text-right font-medium">Terlambat</th>
                    <th className="px-4 py-3 text-right font-medium">Tidak hadir</th>
                    <th className="px-4 py-3 text-right font-medium">Belum check-out</th>
                    <th className="px-4 py-3 text-right font-medium">Jam hadir</th>
                    <th className="px-4 py-3 text-right font-medium">Kehadiran</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {rows.map((row) => (
                    <tr key={row.staff_profile_id}>
                      <td className="px-4 py-2.5">
                        <p className="font-medium text-slate-900">{row.name}</p>
                        <p className="text-xs text-slate-500">{row.nim}</p>
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{row.shifts}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{row.on_time}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{row.late}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{row.absent}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{row.missing_checkout}</td>
                      <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">{formatDuration(row.worked_minutes, { short: true })}</td>
                      <td className="px-4 py-2.5 text-right font-medium tabular-nums">{row.attendance_rate.toLocaleString('id-ID')}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}
    </>
  )
}
