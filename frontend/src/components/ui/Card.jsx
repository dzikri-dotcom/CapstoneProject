import cn from '@/utils/cn'

export default function Card({ title, description, actions, className, bodyClassName, interactive = false, children }) {
  return (
    <section
      className={cn(
        'rounded-[20px] border border-white bg-white/90 shadow-soft ring-1 ring-slate-200/60 backdrop-blur',
        interactive && 'transition duration-300 hover:-translate-y-0.5 hover:shadow-lift',
        className,
      )}
    >
      {(title || actions) && (
        <header className="flex flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3">
          <div className="flex gap-2.5">
            {title && <span className="bg-brand-gradient mt-1 h-4 w-1 shrink-0 rounded-full" aria-hidden="true" />}
            <div>
              {title && <h2 className="font-bold tracking-tight text-slate-900">{title}</h2>}
              {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
            </div>
          </div>
          {actions}
        </header>
      )}
      <div className={cn('p-5', (title || actions) && 'pt-2', bodyClassName)}>{children}</div>
    </section>
  )
}
