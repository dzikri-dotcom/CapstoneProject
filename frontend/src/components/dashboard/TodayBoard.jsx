import { Fragment } from 'react'
import { CHART } from '@/components/charts/chartTheme'
import cn from '@/utils/cn'

/**
 * Keadaan presensi satu staff → titik warna + teks singkat.
 * Warna mengikuti palet grafik (tervalidasi aman buta warna); teks tetap ditampilkan agar tidak bergantung warna.
 */
function statusOf(item) {
  const { state, attendance } = item
  if (state === 'absent' || state === 'missed') return { label: 'Tidak hadir', color: CHART.absent }
  if (state === 'in_progress') return { label: 'Bertugas', color: CHART.onTime, live: true }
  if (state === 'missing_checkout') return { label: 'Belum check-out', color: CHART.late, hollow: true }
  if (attendance?.status === 'late') return { label: 'Terlambat', color: CHART.late }
  if (attendance?.status === 'on_time') return { label: 'Selesai', color: CHART.onTime }
  if (state === 'open') return { label: 'Belum check-in', color: '#a8a29e', hollow: true }
  return { label: 'Belum mulai', color: '#d6d3d1', hollow: true }
}

function Dot({ color, hollow, live }) {
  return (
    <span className="relative flex size-2 shrink-0" aria-hidden="true">
      {live && <span className="absolute inset-0 animate-ping rounded-full opacity-50" style={{ background: color }} />}
      <span
        className="relative size-2 rounded-full"
        style={hollow ? { boxShadow: `inset 0 0 0 1.5px ${color}` } : { background: color }}
      />
    </span>
  )
}

function StaffLine({ item }) {
  const status = statusOf(item)
  return (
    <li className="flex items-center justify-between gap-2 text-[13px]" title={`${item.staff?.name} — ${status.label}`}>
      <span className="flex min-w-0 items-center gap-2">
        <Dot {...status} />
        <span className="truncate text-slate-800">{item.staff?.name}</span>
      </span>
      <span className="shrink-0 text-[11px] text-slate-500 tabular-nums">
        {item.attendance?.check_in_time ?? status.label}
      </span>
    </li>
  )
}

const LEGEND = [
  { label: 'Bertugas / selesai', color: CHART.onTime },
  { label: 'Terlambat', color: CHART.late },
  { label: 'Tidak hadir', color: CHART.absent },
  { label: 'Belum check-in', color: '#a8a29e', hollow: true },
]

/**
 * Papan tugas hari ini, seperti lembar SOP: baris = shift, kolom = pos jaga.
 * Shift yang sedang berjalan ditandai; tempat kosong (kurang staff) diberi garis putus-putus.
 */
export default function TodayBoard({ slots, now }) {
  const posts = [...new Map(slots.map((slot) => [slot.post_name ?? '', slot.post_name])).entries()].map(([key, name]) => ({
    key,
    name: name ?? 'Staff',
  }))
  const shifts = [...new Map(slots.map((slot) => [`${slot.start_time}|${slot.end_time}`, slot])).values()]
    .map((slot) => ({ key: `${slot.start_time}|${slot.end_time}`, start: slot.start_time, end: slot.end_time, name: slot.shift_name }))
    .sort((a, b) => a.key.localeCompare(b.key))
  const byCell = new Map(slots.map((slot) => [`${slot.start_time}|${slot.end_time}|${slot.post_name ?? ''}`, slot]))
  const clock = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

  return (
    <div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[560px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-slate-200 text-left text-xs text-slate-500">
              <th className="w-32 py-2 pr-3 font-medium">Shift</th>
              {posts.map((post) => (
                <th key={post.key} className="px-3 py-2 font-medium">
                  {post.name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {shifts.map((shift) => {
              const current = clock >= shift.start && clock < shift.end
              return (
                <Fragment key={shift.key}>
                  <tr className={cn('border-b border-slate-100 align-top last:border-0', current && 'bg-brand-50/50')}>
                    <th scope="row" className="py-2.5 pr-3 text-left font-normal">
                      <span className="flex items-center gap-1.5 text-[13px] font-medium text-slate-800">
                        {shift.name ?? 'Shift'}
                        {current && (
                          <span className="rounded bg-brand-600 px-1 py-px text-[10px] font-semibold tracking-wide text-white uppercase">
                            Kini
                          </span>
                        )}
                      </span>
                      <span className="text-xs text-slate-500 tabular-nums">
                        {shift.start}–{shift.end}
                      </span>
                    </th>
                    {posts.map((post) => {
                      const slot = byCell.get(`${shift.key}|${post.key}`)
                      if (!slot) return <td key={post.key} className="px-3 py-2.5 text-xs text-slate-300">—</td>
                      const missing = Math.max(0, slot.required_staff - slot.staff.length)
                      return (
                        <td key={post.key} className="px-3 py-2.5">
                          <ul className="space-y-1.5">
                            {slot.staff.map((item) => (
                              <StaffLine key={item.assignment_id} item={item} />
                            ))}
                            {Array.from({ length: missing }, (_, index) => (
                              <li key={`kosong-${index}`} className="rounded border border-dashed border-red-300 px-1.5 text-[11px] text-red-600">
                                Kosong
                              </li>
                            ))}
                          </ul>
                        </td>
                      )
                    })}
                  </tr>
                </Fragment>
              )
            })}
          </tbody>
        </table>
      </div>
      <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
        {LEGEND.map((item) => (
          <li key={item.label} className="flex items-center gap-1.5">
            <Dot {...item} />
            {item.label}
          </li>
        ))}
      </ul>
    </div>
  )
}
