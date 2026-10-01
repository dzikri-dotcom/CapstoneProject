import { AlertTriangle, Plus } from 'lucide-react'
import { Fragment } from 'react'
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
        'flex h-7 w-full min-w-0 items-center gap-1 rounded-md px-2 text-left text-xs font-medium transition',
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

/**
 * Satu pos jaga pada satu shift: tempat sebanyak kapasitas pos, berdampingan seperti dua kolom di lembar Excel.
 * Tempat kosong menjadi tombol "Tambah" (atau garis putus-putus bila jadwal tidak bisa diedit).
 */
function SlotCell({ slot, warnings, editable, onAdd, onManage, showLabel = false }) {
  const missing = Math.max(0, slot.required_staff - slot.assignments.length)
  const post = slot.post?.name

  return (
    <div className={cn('rounded-lg border p-1', missing > 0 ? 'border-red-200 bg-red-50/60' : 'border-slate-200 bg-white')}>
      {showLabel && (
        <div className="mb-1 flex items-center justify-between gap-2 px-1">
          <span className="truncate text-[11px] font-semibold text-slate-600">{post ?? 'Slot'}</span>
          <span className={cn('text-[11px] tabular-nums', missing > 0 ? 'font-semibold text-red-700' : 'text-slate-400')}>
            {slot.assignments.length}/{slot.required_staff}
          </span>
        </div>
      )}
      <div className="grid grid-cols-2 gap-1">
        {slot.assignments.map((assignment) => (
          <AssignmentChip
            key={assignment.id}
            assignment={assignment}
            warnings={warnings[assignment.id]}
            editable={editable}
            onClick={() => onManage(assignment, slot)}
          />
        ))}
        {Array.from({ length: missing }, (_, index) =>
          editable ? (
            <button
              key={`empty-${index}`}
              type="button"
              onClick={() => onAdd(slot)}
              className="flex h-7 items-center justify-center gap-1 rounded-md border border-dashed border-red-300 text-[11px] text-red-500 transition hover:border-brand-500 hover:bg-white hover:text-brand-700"
              aria-label={`Tambah staff ke ${slot.day_label} ${slot.start_time}${post ? ` ${post}` : ''}`}
              title="Kurang staff — klik untuk menambah"
            >
              <Plus className="size-3" /> Tambah
            </button>
          ) : (
            <span key={`empty-${index}`} className="h-7 rounded-md border border-dashed border-red-200" aria-label="Kosong" />
          ),
        )}
      </div>
    </div>
  )
}

/**
 * Tabel jadwal seperti lembar SOP: baris = hari & shift, kolom = pos jaga yang dikelompokkan per lokasi (desktop);
 * daftar per hari (HP).
 */
export default function ScheduleGrid({ slots, warnings = {}, editable = true, onAdd, onManage }) {
  const dates = [...new Set(slots.map((slot) => slot.date))]
  const dayLabel = Object.fromEntries(slots.map((slot) => [slot.date, slot.day_label]))
  const cellProps = { warnings, editable, onAdd, onManage }

  const shifts = [...new Map(slots.map((slot) => [`${slot.start_time}|${slot.end_time}`, slot])).values()]
    .map((slot) => ({ key: `${slot.start_time}|${slot.end_time}`, start: slot.start_time, end: slot.end_time, name: slot.shift?.name }))
    .sort((a, b) => a.key.localeCompare(b.key))

  // Kolom pos mengikuti urutan dari server. Jadwal lama tanpa pos memakai satu kolom "Staff".
  const postKey = (slot) => slot.post?.name ?? ''
  const posts = [...new Map(slots.map((slot) => [postKey(slot), slot.post])).entries()].map(([key, post]) => ({
    key,
    name: post?.name ?? 'Staff',
    location: post?.location ?? null,
  }))
  const hasLocations = posts.some((post) => post.location)
  // Pos bersebelahan dengan lokasi yang sama digabung dalam satu judul (Gedung A: Pos 1, Pos 2).
  const locationGroups = posts.reduce((groups, post) => {
    const last = groups[groups.length - 1]
    if (last && last.location === post.location) last.span += 1
    else groups.push({ location: post.location, span: 1 })
    return groups
  }, [])

  const byCell = new Map(slots.map((slot) => [`${slot.date}|${slot.start_time}|${slot.end_time}|${postKey(slot)}`, slot]))
  const slotFor = (date, shift, post) => byCell.get(`${date}|${shift.key}|${post.key}`)
  const shiftsOn = (date) => shifts.filter((shift) => posts.some((post) => slotFor(date, shift, post)))

  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[820px] border-separate border-spacing-0 text-sm">
          <thead>
            {hasLocations && (
              <tr>
                <th colSpan={2} />
                {locationGroups.map((group, index) => (
                  <th
                    key={`${group.location}-${index}`}
                    colSpan={group.span}
                    className="border-b border-slate-200 px-2 pb-1.5 text-center text-xs font-medium text-slate-500"
                  >
                    {group.location ?? ''}
                  </th>
                ))}
              </tr>
            )}
            <tr className="text-xs text-slate-500">
              <th className="w-24 px-2 py-2 text-left font-medium">Hari</th>
              <th className="w-32 px-2 py-2 text-left font-medium">Shift</th>
              {posts.map((post) => (
                <th key={post.key} className="px-2 py-2 text-center font-semibold text-slate-700">
                  {post.name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {dates.map((date) => {
              const dayShifts = shiftsOn(date)
              return (
                <Fragment key={date}>
                  {dayShifts.map((shift, index) => (
                    <tr key={`${date}-${shift.key}`} className="group">
                      {index === 0 && (
                        <th
                          rowSpan={dayShifts.length}
                          className="border-t border-slate-200 px-2 py-2 text-left align-top font-normal"
                          scope="rowgroup"
                        >
                          <span className="block font-semibold text-slate-800">{dayLabel[date]}</span>
                          <span className="text-xs text-slate-500">{formatDayMonth(date)}</span>
                        </th>
                      )}
                      <th
                        className={cn('px-2 py-1 text-left align-middle font-normal', index === 0 && 'border-t border-slate-200')}
                        scope="row"
                      >
                        {shift.name && <span className="block text-xs font-semibold text-slate-700">{shift.name}</span>}
                        <span className="text-xs text-slate-500 tabular-nums">
                          {shift.start}–{shift.end}
                        </span>
                      </th>
                      {posts.map((post) => {
                        const slot = slotFor(date, shift, post)
                        return (
                          <td key={post.key} className={cn('min-w-44 px-1 py-1 align-middle', index === 0 && 'border-t border-slate-200')}>
                            {slot ? <SlotCell slot={slot} {...cellProps} /> : <div className="h-9 rounded-lg bg-slate-50" />}
                          </td>
                        )
                      })}
                    </tr>
                  ))}
                </Fragment>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="space-y-5 md:hidden">
        {dates.map((date) => (
          <section key={date}>
            <p className="mb-2 text-sm font-semibold text-slate-800">
              {dayLabel[date]}, {formatDate(date)}
            </p>
            <div className="space-y-3">
              {shiftsOn(date).map((shift) => (
                <div key={shift.key}>
                  <p className="mb-1.5 text-xs font-medium text-slate-500 tabular-nums">
                    {shift.name && <span className="font-semibold text-slate-700">{shift.name} · </span>}
                    {shift.start}–{shift.end}
                  </p>
                  <div className="grid gap-1.5 sm:grid-cols-2">
                    {posts.map((post) => {
                      const slot = slotFor(date, shift, post)
                      return slot ? <SlotCell key={post.key} slot={slot} showLabel {...cellProps} /> : null
                    })}
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
