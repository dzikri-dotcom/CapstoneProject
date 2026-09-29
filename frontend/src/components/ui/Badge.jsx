import cn from '@/utils/cn'

const colors = {
  green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
  red: 'bg-red-50 text-red-700 ring-red-600/15',
  amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  blue: 'bg-sky-50 text-sky-700 ring-sky-600/15',
  gray: 'bg-slate-50 text-slate-600 ring-slate-500/15',
  brand: 'bg-brand-50 text-brand-700 ring-brand-600/15',
}

const dots = {
  green: 'bg-emerald-500',
  red: 'bg-red-500',
  amber: 'bg-amber-500',
  blue: 'bg-sky-500',
  gray: 'bg-slate-400',
  brand: 'bg-brand-500',
}

export default function Badge({ color = 'gray', className, children }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
        colors[color],
        className,
      )}
    >
      <span className={cn('size-1.5 shrink-0 rounded-full', dots[color])} aria-hidden="true" />
      {children}
    </span>
  )
}
