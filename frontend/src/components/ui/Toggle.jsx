import cn from '@/utils/cn'

/**
 * Saklar on/off dengan label, misal status aktif/nonaktif.
 */
export default function Toggle({ checked, onChange, label, description, disabled }) {
  return (
    <label className={cn('flex items-start justify-between gap-4', disabled ? 'opacity-60' : 'cursor-pointer')}>
      <span>
        <span className="block text-sm font-medium text-slate-700">{label}</span>
        {description && <span className="block text-xs text-slate-500">{description}</span>}
      </span>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative inline-flex h-6 w-11 shrink-0 rounded-full transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600',
          checked ? 'bg-brand-600' : 'bg-slate-300',
        )}
      >
        <span
          className={cn(
            'absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition',
            checked && 'translate-x-5',
          )}
        />
      </button>
    </label>
  )
}
