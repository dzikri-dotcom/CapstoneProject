import Badge from '@/components/ui/Badge'
import Card from '@/components/ui/Card'
import { formatHours } from '@/utils/format'
import { distributionStats } from './scheduleUtils'

function Stat({ label, value }) {
  return (
    <div className="rounded-xl bg-slate-50 px-4 py-3">
      <p className="text-xs text-slate-500">{label}</p>
      <p className="text-lg font-semibold text-slate-900 tabular-nums">{value}</p>
    </div>
  )
}

/**
 * Pratinjau pemerataan beban kerja per staff dalam satu periode.
 */
export default function StaffHoursPanel({ staffHours }) {
  const minutes = staffHours.map((staff) => staff.minutes)
  const { mean, range, sd } = distributionStats(minutes)
  const max = Math.max(...minutes, 1)
  const sorted = [...staffHours].sort((a, b) => b.minutes - a.minutes || a.name.localeCompare(b.name))

  return (
    <Card
      title="Beban kerja per staff"
      description="Total jam shift setiap staff pada periode ini. Semakin kecil standar deviasi, semakin merata."
    >
      <div className="mb-5 grid grid-cols-3 gap-3">
        <Stat label="Rata-rata" value={formatHours(mean)} />
        <Stat label="Selisih maks–min" value={formatHours(range)} />
        <Stat label="Standar deviasi" value={formatHours(sd)} />
      </div>

      <ul className="space-y-2">
        {sorted.map((staff) => (
          <li key={staff.staff_profile_id} className="flex items-center gap-3 text-sm">
            <span className="w-36 shrink-0 truncate text-slate-700 sm:w-48" title={staff.name}>
              {staff.name}
              {!staff.is_active && (
                <Badge color="gray" className="ml-1">
                  nonaktif
                </Badge>
              )}
            </span>
            <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
              <div className="h-full rounded-full bg-brand-500" style={{ width: `${(staff.minutes / max) * 100}%` }} />
            </div>
            <span className="w-28 shrink-0 text-right text-xs text-slate-500 tabular-nums">
              {formatHours(staff.minutes)} · {staff.shifts} shift
            </span>
          </li>
        ))}
      </ul>
    </Card>
  )
}
