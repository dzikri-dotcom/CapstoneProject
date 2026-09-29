import cn from '@/utils/cn'
import Spinner from './Spinner'

const variants = {
  primary: 'bg-brand-600 text-white shadow-card hover:bg-brand-700 focus-visible:outline-brand-600',
  secondary:
    'bg-white text-slate-700 shadow-card ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-slate-400',
  danger: 'bg-red-600 text-white shadow-card hover:bg-red-700 focus-visible:outline-red-600',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
}

const sizes = {
  sm: 'h-8 gap-1.5 px-3 text-[13px]',
  md: 'h-9 gap-2 px-3.5 text-sm',
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
        'inline-flex shrink-0 items-center justify-center rounded-lg font-medium whitespace-nowrap transition-[background-color,color,box-shadow,transform] duration-150',
        'focus-visible:outline-2 focus-visible:outline-offset-2 active:translate-y-px',
        'disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
    >
      {loading ? <Spinner className="size-4" /> : Icon && <Icon className="size-4 shrink-0" strokeWidth={2} />}
      {children}
    </button>
  )
}
