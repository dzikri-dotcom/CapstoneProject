import useCountUp from '@/hooks/useCountUp'
import cn from '@/utils/cn'

/** Kartu statistik berwarna penuh; tiap tone punya gradasi dan cahaya sendiri. */
const tones = {
  brand: 'from-rose-500 via-red-500 to-orange-500 shadow-rose-500/30',
  green: 'from-emerald-500 to-teal-600 shadow-emerald-500/30',
  gray: 'from-slate-500 to-slate-700 shadow-slate-500/25',
  amber: 'from-amber-400 to-orange-500 shadow-amber-500/30',
  blue: 'from-sky-500 to-indigo-600 shadow-indigo-500/30',
  violet: 'from-violet-500 to-fuchsia-600 shadow-violet-500/30',
}

export default function StatCard({ icon: Icon, label, value, hint, tone = 'brand', loading = false }) {
  const display = useCountUp(value)

  return (
    <div
      className={cn(
        'group relative overflow-hidden rounded-[20px] bg-gradient-to-br p-4 text-white sm:p-5 shadow-lg transition duration-300 hover:-translate-y-1 hover:shadow-xl',
        tones[tone],
      )}
    >
      <div className="bg-dots pointer-events-none absolute inset-0 opacity-40 [mask-image:linear-gradient(to_left,black,transparent_70%)]" aria-hidden="true" />
      <div
        className="pointer-events-none absolute -right-8 -bottom-10 size-32 rounded-full bg-white/15 transition duration-500 group-hover:scale-125"
        aria-hidden="true"
      />
      <div className="relative flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-medium text-white/80 sm:text-sm">{label}</p>
          {loading ? (
            <div className="mt-2 h-8 w-16 animate-pulse rounded-lg bg-white/25" />
          ) : (
            <p className="mt-1 text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl">{display}</p>
          )}
          {hint && <p className="mt-0.5 truncate text-xs text-white/70">{hint}</p>}
        </div>
        {Icon && (
          <div className="flex size-9 shrink-0 items-center justify-center rounded-xl sm:size-11 sm:rounded-2xl bg-white/20 ring-1 ring-white/30 backdrop-blur transition duration-300 group-hover:rotate-6">
            <Icon className="size-5" />
          </div>
        )}
      </div>
    </div>
  )
}
