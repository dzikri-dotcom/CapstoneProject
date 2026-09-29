import cn from '@/utils/cn'

export default function Card({ title, description, actions, className, bodyClassName, interactive = false, children }) {
  return (
    <section
      className={cn(
        'rounded-xl border border-slate-200/80 bg-white shadow-card',
        interactive && 'transition-colors hover:border-slate-300',
        className,
      )}
    >
      {(title || actions) && (
        <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
          <div className="min-w-0">
            {title && <h2 className="text-[15px] font-semibold tracking-tight text-slate-900">{title}</h2>}
            {description && <p className="mt-0.5 text-[13px] text-slate-500">{description}</p>}
          </div>
          {actions}
        </header>
      )}
      <div className={cn('p-5', bodyClassName)}>{children}</div>
    </section>
  )
}
