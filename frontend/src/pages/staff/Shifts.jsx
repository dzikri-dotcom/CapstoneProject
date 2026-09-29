import { CalendarDays, Clock, Users } from 'lucide-react'
import Alert from '@/components/ui/Alert'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getMyShifts } from '@/services/scheduleService'
import cn from '@/utils/cn'
import { formatDate, formatDayMonth, formatDuration, shortName } from '@/utils/format'
import { addDaysISO, todayISO } from '@/utils/time'

/** Senin minggu ini dalam format YYYY-MM-DD. */
function thisMondayISO() {
  const day = new Date().getDay() // 0 = Minggu
  return addDaysISO(todayISO(), day === 0 ? -6 : 1 - day)
}

function weekTitle(weekStart, thisMonday) {
  if (weekStart === thisMonday) return 'Minggu ini'
  if (weekStart === addDaysISO(thisMonday, 7)) return 'Minggu depan'
  return `${formatDayMonth(weekStart)} – ${formatDayMonth(addDaysISO(weekStart, 6))}`
}

export default function Shifts() {
  const today = todayISO()
  const thisMonday = thisMondayISO()
  const { data, loading, error } = useFetch(() => getMyShifts({ from: thisMonday, to: addDaysISO(thisMonday, 27) }), [thisMonday])

  const shifts = data?.data ?? []
  const weeks = new Map()
  shifts.forEach((shift) => {
    const offset = Math.floor((new Date(shift.date) - new Date(thisMonday)) / (7 * 24 * 3600 * 1000))
    const weekStart = addDaysISO(thisMonday, offset * 7)
    if (!weeks.has(weekStart)) weeks.set(weekStart, [])
    weeks.get(weekStart).push(shift)
  })

  return (
    <>
      <PageHeader title="Jadwal shift" description="Shift Anda untuk 4 minggu ke depan yang sudah dipublikasikan koordinator." />

      {!data && loading && (
        <div className="flex justify-center py-16">
          <Spinner className="size-6 text-brand-600" />
        </div>
      )}
      {error && !data && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {data && shifts.length === 0 && (
        <div className="rounded-2xl border border-slate-200 bg-white">
          <EmptyState
            icon={CalendarDays}
            title="Belum ada jadwal shift"
            description="Jadwal akan muncul di sini setelah koordinator logistik mempublikasikannya. Pastikan jadwal kuliah Anda sudah diisi."
          />
        </div>
      )}

      {shifts.length > 0 && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-2xl border border-slate-200 bg-white p-4">
              <p className="text-xs text-slate-500">Total shift</p>
              <p className="text-xl font-semibold text-slate-900">{shifts.length}</p>
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white p-4">
              <p className="text-xs text-slate-500">Total jam</p>
              <p className="text-xl font-semibold text-slate-900">{formatDuration(data.meta.total_minutes)}</p>
            </div>
          </div>

          {[...weeks.entries()].map(([weekStart, items]) => (
            <section key={weekStart}>
              <h2 className="mb-2 text-sm font-semibold text-slate-700">
                {weekTitle(weekStart, thisMonday)}
                <span className="ml-2 font-normal text-slate-400">
                  {items.length} shift · {formatDuration(items.reduce((sum, item) => sum + item.duration_minutes, 0))}
                </span>
              </h2>
              <ul className="space-y-2">
                {items.map((shift) => {
                  const past = shift.date < today
                  const isToday = shift.date === today
                  return (
                    <li
                      key={shift.id}
                      className={cn(
                        'flex gap-4 rounded-2xl border bg-white p-4',
                        isToday ? 'border-brand-300 ring-2 ring-brand-100' : 'border-slate-200',
                        past && 'opacity-60',
                      )}
                    >
                      <div className="flex w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-50 py-1.5 text-brand-700">
                        <span className="text-[11px] font-medium uppercase">{shift.day_label.slice(0, 3)}</span>
                        <span className="text-lg leading-none font-semibold">{Number(shift.date.slice(8))}</span>
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-1.5 font-semibold text-slate-900 tabular-nums">
                          <Clock className="size-4 text-slate-400" />
                          {shift.start_time}–{shift.end_time}
                          {isToday && <span className="ml-1 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] text-white">Hari ini</span>}
                        </p>
                        <p className="text-xs text-slate-500">
                          {formatDate(shift.date)} · {formatDuration(shift.duration_minutes)}
                        </p>
                        <p className="mt-1.5 flex items-center gap-1.5 text-xs text-slate-600">
                          <Users className="size-3.5 text-slate-400" />
                          {shift.teammates.length ? `Bersama ${shift.teammates.map(shortName).join(', ')}` : 'Bertugas sendiri'}
                        </p>
                      </div>
                    </li>
                  )
                })}
              </ul>
            </section>
          ))}
        </div>
      )}
    </>
  )
}
