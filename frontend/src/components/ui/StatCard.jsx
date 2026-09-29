import useCountUp from '@/hooks/useCountUp'
import cn from '@/utils/cn'

/** Warna ikon per tone; kartu tetap putih agar angka yang menonjol, bukan hiasannya. */
const tones = {
  brand: 'bg-brand-50 text-brand-600',
  green: 'bg-emerald-50 text-emerald-600',
  gray: 'bg-slate-100 text-slate-500',
  amber: 'bg-amber-50 text-amber-600',
  blue: 'bg-sky-50 text-sky-600',
  violet: 'bg-indigo-50 text-indigo-600',
}

export default function StatCard({ icon: Icon, label, value, hint, tone = 'gray', loading = false }) {
  const display = useCountUp(value)

  return (
    <div className="group rounded-xl border border-slate-200/80 bg-white p-4 shadow-card transition-colors hover:border-slate-300 sm:p-5">
      <div className="flex items-center justify-between gap-3">
        <p className="truncate text-[13px] font-medium text-slate-500">{label}</p>
        {Icon && (
          <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-md', tones[tone])}>
            <Icon className="size-4" />
          </span>
        )}
      </div>
      {loading ? (
        <div className="mt-3 h-7 w-16 animate-pulse rounded-md bg-slate-100" />
      ) : (
        <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{display}</p>
      )}
      {hint && <p className="mt-1 truncate text-xs text-slate-400">{hint}</p>}
    </div>
  )
}
