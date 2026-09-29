import { AlertTriangle, Plus } from 'lucide-react'
import cn from '@/utils/cn'
import { formatDate, formatDayMonth, shortName } from '@/utils/format'

function AssignmentChip({ assignment, warnings, editable, onClick }) {
  const hasWarning = warnings?.length > 0
  const tooltip = [
    assignment.name,
    assignment.source === 'manual' ? '(diubah manual oleh admin)' : '(hasil algoritma)',
    ...(warnings ?? []).map((warning) => `⚠ ${warning.message}`),
  ].join('\n')

  return (
    <button
      type="button"
      onClick={onClick}
      title={tooltip}
      aria-label={`${assignment.name}${hasWarning ? ', ada peringatan' : ''}`}
      className={cn(
        'flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs font-medium transition',
        hasWarning ? 'bg-amber-50 text-amber-900 ring-1 ring-amber-300' : 'bg-slate-100 text-slate-700',
        editable ? 'hover:ring-1 hover:ring-slate-400' : 'cursor-default',
      )}
    >
      {hasWarning && <AlertTriangle className="size-3.5 shrink-0 text-amber-500" />}
      <span className="truncate">{shortName(assignment.name)}</span>
      {assignment.source === 'manual' && (
        <span className="ml-auto rounded bg-sky-100 px-1 text-[10px] font-semibold text-sky-700" aria-hidden="true">
          M
        </span>
      )}
    </button>
  )
}

function SlotCell({ slot, warnings, editable, onAdd, onManage }) {
  const missing = slot.required_staff - slot.assignments.length

  return (
    <div
      className={cn(
        'flex h-full min-h-20 flex-col gap-1 rounded-lg border p-1.5',
        missing > 0 ? 'border-red-200 bg-red-50/70' : 'border-slate-200 bg-white',
      )}
    >
      {slot.assignments.map((assignment) => (
        <AssignmentChip
          key={assignment.id}
          assignment={assignment}
          warnings={warnings[assignment.id]}
          editable={editable}
          onClick={() => onManage(assignment, slot)}
        />
      ))}
      {missing > 0 && <span className="px-2 py-0.5 text-[11px] font-semibold text-red-700">Kurang {missing} staff</span>}
      {editable && (
        <button
          type="button"
          onClick={() => onAdd(slot)}
          className="mt-auto flex items-center justify-center gap-1 rounded-md border border-dashed border-slate-300 py-0.5 text-[11px] text-slate-400 transition hover:border-brand-400 hover:text-brand-700"
          aria-label={`Tambah staff ke ${slot.day_label} ${slot.start_time}`}
        >
          <Plus className="size-3" /> Tambah
        </button>
      )}
    </div>
  )
}

/**
 * Tabel jadwal: kolom = tanggal, baris = jam slot (desktop); daftar per hari (HP).
 */
export default function ScheduleGrid({ slots, warnings = {}, editable = true, onAdd, onManage }) {
  const dates = [...new Set(slots.map((slot) => slot.date))]
  const times = [...new Map(slots.map((slot) => [slot.start_time, slot.end_time])).entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([start, end]) => ({ start, end }))
  const byKey = new Map(slots.map((slot) => [`${slot.date}|${slot.start_time}`, slot]))
  const dayLabel = Object.fromEntries(slots.map((slot) => [slot.date, slot.day_label]))
  const cellProps = { warnings, editable, onAdd, onManage }

  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[720px] table-fixed border-separate border-spacing-1.5">
          <thead>
            <tr>
              <th className="w-20" />
              {dates.map((date) => (
                <th key={date} className="pb-1 text-left font-normal">
                  <span className="block text-sm font-semibold text-slate-800">{dayLabel[date]}</span>
                  <span className="text-xs text-slate-500">{formatDayMonth(date)}</span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {times.map((time) => (
              <tr key={time.start}>
                <th className="pr-1 text-right align-top text-xs font-medium text-slate-500 tabular-nums">
                  <div className="pt-1.5">
                    {time.start}
                    <br />
                    <span className="text-slate-400">{time.end}</span>
                  </div>
                </th>
                {dates.map((date) => {
                  const slot = byKey.get(`${date}|${time.start}`)
                  return (
                    <td key={date} className="h-px align-top">
                      {slot ? <SlotCell slot={slot} {...cellProps} /> : <div className="h-full rounded-lg bg-slate-50" />}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="space-y-5 md:hidden">
        {dates.map((date) => (
          <section key={date}>
            <p className="mb-2 text-sm font-semibold text-slate-800">
              {dayLabel[date]}, {formatDate(date)}
            </p>
            <div className="space-y-2">
              {slots
                .filter((slot) => slot.date === date)
                .map((slot) => (
                  <div key={slot.id} className="flex gap-3">
                    <span className="w-12 shrink-0 pt-1.5 text-xs text-slate-500 tabular-nums">
                      {slot.start_time}
                      <br />
                      {slot.end_time}
                    </span>
                    <div className="flex-1">
                      <SlotCell slot={slot} {...cellProps} />
                    </div>
                  </div>
                ))}
            </div>
          </section>
        ))}
      </div>
    </>
  )
}
