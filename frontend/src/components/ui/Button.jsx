import cn from '@/utils/cn'
import Spinner from './Spinner'

const variants = {
  primary:
    'bg-brand-gradient text-white shadow-glow ring-1 ring-inset ring-white/15 hover:-translate-y-px hover:shadow-glow-lg hover:brightness-110 focus-visible:outline-rose-600',
  secondary:
    'bg-white text-slate-700 shadow-soft ring-1 ring-inset ring-slate-200 hover:-translate-y-px hover:text-slate-900 hover:ring-violet-300 focus-visible:outline-violet-400',
  danger: 'bg-gradient-to-br from-red-500 to-rose-600 text-white shadow-md shadow-red-500/30 hover:-translate-y-px hover:brightness-110 focus-visible:outline-red-600',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
}

const sizes = {
  sm: 'gap-1.5 px-3 py-1.5 text-sm',
  md: 'gap-2 px-4 py-2.5 text-sm',
  icon: 'size-9 justify-center',
}

export default function Button({
  variant = 'primary',
  size = 'md',
  type = 'button',
  loading = false,
  disabled = false,
  icon: Icon,
  className,
  children,
  ...props
}) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-xl font-semibold whitespace-nowrap transition-all duration-200',
        'focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.97]',
        'disabled:pointer-events-none disabled:opacity-55',
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
    >
      {loading ? <Spinner className="size-4" /> : Icon && <Icon className="size-4 shrink-0" />}
      {children}
    </button>
  )
}
