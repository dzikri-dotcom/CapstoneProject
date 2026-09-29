export default function EmptyState({ icon: Icon, title, description, action }) {
  return (
    <div className="flex animate-fade-in flex-col items-center justify-center px-6 py-12 text-center">
      {Icon && (
        <div className="mb-3 flex size-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-card">
          <Icon className="size-5" />
        </div>
      )}
      <p className="text-sm font-semibold text-slate-800">{title}</p>
      {description && <p className="mt-1 max-w-sm text-[13px] text-slate-500">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
