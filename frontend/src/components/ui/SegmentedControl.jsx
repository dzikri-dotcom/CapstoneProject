import { useLayoutEffect, useRef } from 'react'
import cn from '@/utils/cn'

/**
 * Pilihan tab bersebelahan dengan indikator yang bergeser ke tab aktif.
 * options: [{ value, label, count? }]
 */
export default function SegmentedControl({ options, value, onChange, className, 'aria-label': ariaLabel }) {
  const listRef = useRef(null)
  const indicatorRef = useRef(null)

  // Posisi indikator ditulis langsung ke DOM (tanpa state) agar tidak ada render ulang.
  useLayoutEffect(() => {
    const place = () => {
      const active = listRef.current?.querySelector('[aria-selected="true"]')
      const indicator = indicatorRef.current
      if (!indicator) return
      if (!active) {
        indicator.style.opacity = '0'
        return
      }
      indicator.style.opacity = '1'
      indicator.style.width = `${active.offsetWidth}px`
      indicator.style.transform = `translateX(${active.offsetLeft}px)`
    }
    place()
    const observer = new ResizeObserver(place)
    if (listRef.current) observer.observe(listRef.current)
    return () => observer.disconnect()
  }, [value, options])

  return (
    <div
      ref={listRef}
      role="tablist"
      aria-label={ariaLabel}
      className={cn('relative inline-flex max-w-full overflow-x-auto rounded-lg bg-slate-200/70 p-0.5 [scrollbar-width:none]', className)}
    >
      <span
        ref={indicatorRef}
        className="absolute top-0.5 bottom-0.5 left-0 rounded-md bg-white opacity-0 shadow-card ring-1 ring-slate-200/80 transition-[transform,width] duration-200 ease-out"
        aria-hidden="true"
      />
      {options.map((option) => {
        const selected = option.value === value
        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={() => onChange(option.value)}
            className={cn(
              'relative z-10 flex h-8 flex-1 items-center justify-center gap-1.5 rounded-md px-3 text-[13px] font-medium whitespace-nowrap transition-colors sm:flex-none',
              selected ? 'text-slate-900' : 'text-slate-500 hover:text-slate-800',
            )}
          >
            {option.label}
            {option.count !== undefined && (
              <span className={cn('rounded px-1 text-[11px] tabular-nums', selected ? 'bg-slate-100 text-slate-600' : 'text-slate-400')}>
                {option.count}
              </span>
            )}
          </button>
        )
      })}
    </div>
  )
}
