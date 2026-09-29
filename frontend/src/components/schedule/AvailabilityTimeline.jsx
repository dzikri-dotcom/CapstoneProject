import { CalendarOff } from 'lucide-react'
import EmptyState from '@/components/ui/EmptyState'
import { formatDuration } from '@/utils/format'
import { toMinutes } from '@/utils/time'

function Legend({ className, label }) {
  return (
    <span className="flex items-center gap-1.5">
      <span className={`size-3 rounded-sm ${className}`} />
      {label}
    </span>
  )
}

/**
 * Visual jam kosong per hari: jam operasional dikurangi jadwal kuliah.
 * `availability` = response /availability: { days, total_free_minutes, total_operational_minutes }
 */
export default function AvailabilityTimeline({ availability }) {
  if (!availability?.days?.length) {
    return <EmptyState icon={CalendarOff} title="Belum ada hari operasional aktif" />
  }

  const { days, total_free_minutes: totalFree, total_operational_minutes: totalOperational } = availability
  const rangeStart = Math.min(...days.map((day) => toMinutes(day.open_time)))
  const rangeEnd = Math.max(...days.map((day) => toMinutes(day.close_time)))
  const span = rangeEnd - rangeStart
  const position = (start, end) => ({
    left: `${((toMinutes(start) - rangeStart) / span) * 100}%`,
    width: `${((toMinutes(end) - toMinutes(start)) / span) * 100}%`,
  })

  // Label jam di atas grafik (tiap 1 jam, atau tiap 2 jam jika rentangnya panjang).
  const step = span > 5 * 60 ? 120 : 60
  const ticks = []
  for (let minute = Math.ceil(rangeStart / 60) * 60; minute <= rangeEnd; minute += step) ticks.push(minute)

  const percentage = totalOperational ? Math.round((totalFree / totalOperational) * 100) : 0

  return (
    <div>
      <div className="mb-5 rounded-xl bg-emerald-50 p-4">
        <p className="text-sm text-emerald-800">
          Total jam kosong per minggu:{' '}
          <span className="font-semibold">{formatDuration(totalFree)}</span> dari {formatDuration(totalOperational)} waktu
          operasional ({percentage}%)
        </p>
      </div>

      <div className="space-y-2">
        {/* Label jam */}
        <div className="flex items-end gap-3">
          <span className="w-12 shrink-0" />
          <div className="relative h-4 flex-1">
            {ticks.map((minute) => (
              <span
                key={minute}
                className="absolute -translate-x-1/2 text-[10px] text-slate-400 tabular-nums"
                style={{ left: `${((minute - rangeStart) / span) * 100}%` }}
              >
                {String(minute / 60).padStart(2, '0')}
              </span>
            ))}
          </div>
          <span className="w-14 shrink-0" />
        </div>

        {days.map((day) => (
          <div key={day.day_of_week} className="flex items-center gap-3">
            <span className="w-12 shrink-0 text-sm font-medium text-slate-700">{day.day_label}</span>
            <div className="relative h-7 flex-1 overflow-hidden rounded-md bg-slate-100">
              {day.free.map((interval) => (
                <span
                  key={`f-${interval.start}`}
                  className="absolute inset-y-0 border-x border-white/60 bg-emerald-400"
                  style={position(interval.start, interval.end)}
                  title={`Kosong ${interval.start}–${interval.end}`}
                />
              ))}
              {day.busy.map((interval) => (
                <span
                  key={`b-${interval.start}`}
                  className="absolute inset-y-0 border-x border-white/60 bg-amber-300 bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,rgba(255,255,255,0.35)_4px,rgba(255,255,255,0.35)_8px)]"
                  style={position(interval.start, interval.end)}
                  title={`Kuliah ${interval.start}–${interval.end}`}
                />
              ))}
            </div>
            <span className="w-14 shrink-0 text-right text-xs text-slate-500 tabular-nums">
              {formatDuration(day.free_minutes, { short: true })}
            </span>
          </div>
        ))}
      </div>

      <div className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        <Legend className="bg-emerald-400" label="Kosong (bisa diberi shift)" />
        <Legend className="bg-amber-300" label="Kuliah" />
        <Legend className="bg-slate-100 ring-1 ring-slate-200" label="Di luar jam operasional" />
      </div>
    </div>
  )
}
