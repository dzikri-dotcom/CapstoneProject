import { ArrowUpDown, ChartColumn, Clock, Info, Percent, Sigma } from 'lucide-react'
import { useState } from 'react'
import { Bar, BarChart, CartesianGrid, LabelList, Line, LineChart, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { CHART, axisProps } from '@/components/charts/chartTheme'
import ChartTooltip from '@/components/charts/ChartTooltip'
import DateRangeFilter from '@/components/reports/DateRangeFilter'
import { RANGE_PRESETS } from '@/components/reports/reportRanges'
import PeriodStatusBadge from '@/components/schedule/PeriodStatusBadge'
import Alert from '@/components/ui/Alert'
import Card from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import StatCard from '@/components/ui/StatCard'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getFairnessReport } from '@/services/reportService'
import cn from '@/utils/cn'
import { formatDateRange, formatHours } from '@/utils/format'

const hours = (minutes) => Math.round((minutes / 60) * 100) / 100
const fmt = (value, digits = 2) => value.toLocaleString('id-ID', { maximumFractionDigits: digits })

export default function FairnessReport() {
  const [range, setRange] = useState(RANGE_PRESETS.last4Weeks)
  const { data, loading, error } = useFetch(() => getFairnessReport(range), [range.start_date, range.end_date])

  const stats = data?.stats
  const staff = (data?.staff ?? []).map((row) => ({ ...row, hours: hours(row.minutes) }))
  const meanHours = stats ? hours(stats.mean) : 0
  const periods = (data?.periods ?? []).map((period) => ({
    ...period,
    label: formatDateRange(period.start_date, period.end_date),
    sdHours: hours(period.stats.sd),
  }))
  const hasShifts = staff.some((row) => row.minutes > 0)

  return (
    <>
      <PageHeader
        title="Keadilan Distribusi Jadwal"
        description="Mengukur seberapa merata algoritma Round Robin membagi jam shift ke setiap staff."
      />

      <DateRangeFilter value={range} onChange={setRange} />

      {!data && loading && (
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Menghitung laporan…
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && !hasShifts && (
        <Card>
          <EmptyState
            icon={ChartColumn}
            title="Belum ada jadwal pada rentang ini"
            description="Generate jadwal shift terlebih dahulu, atau pilih rentang tanggal lain."
          />
        </Card>
      )}

      {data && hasShifts && (
        <div className={cn('space-y-6 transition-opacity', loading && 'opacity-60')}>
          <div className="stagger grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <StatCard icon={Clock} tone="blue" label="Rata-rata jam per staff" value={formatHours(stats.mean)} hint={`${stats.count} staff · total ${formatHours(stats.total)}`} />
            <StatCard icon={ArrowUpDown} tone="violet" label="Selisih tertinggi–terendah" value={formatHours(stats.range)} hint={`${formatHours(stats.max)} – ${formatHours(stats.min)}`} />
            <StatCard icon={Sigma} tone="amber" label="Standar deviasi" value={formatHours(stats.sd)} hint="Makin kecil makin merata" />
            <StatCard icon={Percent} tone="green" label="Koefisien variasi" value={`${fmt(stats.cv, 1)}%`} hint="Standar deviasi ÷ rata-rata" />
          </div>

          <Card
            title="Total jam shift per staff"
            description={`${formatDateRange(data.from, data.to)}. Garis putus-putus = rata-rata (${formatHours(stats.mean)}).`}
          >
            <div style={{ height: Math.max(staff.length * 38 + 40, 200) }}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={staff} layout="vertical" margin={{ top: 8, right: 56, bottom: 8, left: 8 }} barCategoryGap={8}>
                  <CartesianGrid horizontal={false} stroke={CHART.grid} />
                  <XAxis type="number" {...axisProps} unit=" j" allowDecimals={false} />
                  <YAxis type="category" dataKey="name" {...axisProps} width={170} axisLine={false} />
                  <Tooltip
                    cursor={{ fill: '#f1f5f9' }}
                    content={
                      <ChartTooltip
                        title={(d) => d.name}
                        rows={(d) => [
                          ['Jam shift', formatHours(d.minutes)],
                          ['Jumlah shift', d.shifts],
                          ['Selisih dari rata-rata', `${d.minutes >= stats.mean ? '+' : '−'}${formatHours(Math.abs(d.minutes - stats.mean))}`],
                          ['Jam kosong per minggu', formatHours(d.free_minutes_per_week)],
                        ]}
                      />
                    }
                  />
                  <ReferenceLine x={meanHours} stroke={CHART.reference} strokeDasharray="4 4" />
                  <Bar dataKey="hours" fill={CHART.primary} radius={[0, 4, 4, 0]} maxBarSize={22} isAnimationActive={false}>
                    <LabelList dataKey="hours" position="right" fill="#334155" fontSize={12} formatter={(value) => `${fmt(value, 1)} j`} />
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>

          <Card title="Tabel distribusi" bodyClassName="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
                  <tr>
                    <th className="px-4 py-3 font-medium">No</th>
                    <th className="px-4 py-3 font-medium">Staff</th>
                    <th className="px-4 py-3 text-right font-medium">Jam shift</th>
                    <th className="px-4 py-3 text-right font-medium">Jumlah shift</th>
                    <th className="px-4 py-3 text-right font-medium">Selisih dari rata-rata</th>
                    <th className="px-4 py-3 text-right font-medium">Diubah manual</th>
                    <th className="px-4 py-3 text-right font-medium">Jam kosong / minggu</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {staff.map((row, index) => {
                    const diff = row.minutes - stats.mean
                    return (
                      <tr key={row.staff_profile_id}>
                        <td className="px-4 py-2.5 text-slate-500">{index + 1}</td>
                        <td className="px-4 py-2.5">
                          <p className="font-medium text-slate-900">{row.name}</p>
                          <p className="text-xs text-slate-500">
                            {row.nim}
                            {!row.is_active && ' · nonaktif'}
                          </p>
                        </td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{formatHours(row.minutes)}</td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{row.shifts}</td>
                        <td className="px-4 py-2.5 text-right text-slate-600 tabular-nums">
                          {diff >= 0 ? '+' : '−'}
                          {formatHours(Math.abs(diff))}
                        </td>
                        <td className="px-4 py-2.5 text-right tabular-nums">{row.manual_shifts}</td>
                        <td className="px-4 py-2.5 text-right text-slate-600 tabular-nums">{formatHours(row.free_minutes_per_week)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Card>

          <Card title="Per periode jadwal" description="Pemerataan dihitung terpisah untuk setiap periode yang sudah di-generate." bodyClassName="p-0">
            {periods.length >= 2 && (
              <div className="px-4 pt-4 pb-4">
                <p className="mb-2 text-xs font-medium text-slate-500">Standar deviasi jam shift per periode</p>
                <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={periods} margin={{ top: 8, right: 24, bottom: 8, left: 0 }}>
                    <CartesianGrid vertical={false} stroke={CHART.grid} />
                    <XAxis dataKey="label" {...axisProps} interval={0} padding={{ left: 60, right: 60 }} />
                    <YAxis {...axisProps} width={56} axisLine={false} tickFormatter={(value) => `${fmt(value, 1)} j`} />
                    <Tooltip
                      content={
                        <ChartTooltip
                          title={(d) => d.label}
                          rows={(d) => [
                            ['Standar deviasi', formatHours(d.stats.sd)],
                            ['Rata-rata', formatHours(d.stats.mean)],
                            ['Selisih', formatHours(d.stats.range)],
                          ]}
                        />
                      }
                    />
                    <Line type="monotone" dataKey="sdHours" name="Standar deviasi" stroke={CHART.primary} strokeWidth={2} dot={{ r: 4, fill: CHART.primary, stroke: CHART.surface, strokeWidth: 2 }} isAnimationActive={false} />
                  </LineChart>
                </ResponsiveContainer>
                </div>
              </div>
            )}
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
                  <tr>
                    <th className="px-4 py-3 font-medium">Periode</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    <th className="px-4 py-3 text-right font-medium">Rata-rata</th>
                    <th className="px-4 py-3 text-right font-medium">Selisih</th>
                    <th className="px-4 py-3 text-right font-medium">Std. deviasi</th>
                    <th className="px-4 py-3 text-right font-medium">Koef. variasi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {periods.map((period) => (
                    <tr key={period.id}>
                      <td className="px-4 py-2.5 font-medium whitespace-nowrap text-slate-800">{period.label}</td>
                      <td className="px-4 py-2.5">
                        <PeriodStatusBadge status={period.status} />
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{formatHours(period.stats.mean)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{formatHours(period.stats.range)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{formatHours(period.stats.sd)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{fmt(period.stats.cv, 1)}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            <Info className="mt-0.5 size-5 shrink-0 text-sky-600" />
            <p>
              Rumus: rata-rata μ = Σx / n; standar deviasi σ = √(Σ(x − μ)² / n); koefisien variasi = σ / μ × 100%. Staff dengan
              jam kosong sedikit (jadwal kuliah padat) wajar mendapat jam lebih sedikit, karena algoritma tidak pernah menugaskan
              staff saat kuliah. Staff aktif tanpa shift tetap dihitung (0 jam).
            </p>
          </div>
        </div>
      )}
    </>
  )
}
