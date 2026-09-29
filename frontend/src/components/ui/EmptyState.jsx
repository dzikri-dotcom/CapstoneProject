export default function EmptyState({ icon: Icon, title, description, action }) {
  return (
    <div className="flex animate-fade-in flex-col items-center justify-center px-6 py-12 text-center">
      {Icon && (
        <div className="relative mb-4">
          <div className="absolute inset-0 scale-150 rounded-full bg-brand-100/50 blur-xl" aria-hidden="true" />
          <div className="relative flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-white to-slate-100 text-slate-400 shadow-soft ring-1 ring-slate-200">
            <Icon className="size-6" />
          </div>
        </div>
      )}
      <p className="font-semibold text-slate-800">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}
