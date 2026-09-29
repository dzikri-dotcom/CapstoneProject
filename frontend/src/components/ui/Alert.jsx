import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'

const variants = {
  success: { icon: CheckCircle2, style: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
  error: { icon: XCircle, style: 'border-red-200 bg-red-50 text-red-800' },
  warning: { icon: AlertTriangle, style: 'border-amber-200 bg-amber-50 text-amber-800' },
  info: { icon: Info, style: 'border-sky-200 bg-sky-50 text-sky-800' },
}

export default function Alert({ variant = 'info', title, children, className = '' }) {
  const { icon: Icon, style } = variants[variant]

  return (
    <div className={`flex gap-3 rounded-lg border p-4 text-sm ${style} ${className}`}>
      <Icon className="mt-0.5 size-5 shrink-0" />
      <div className="min-w-0 flex-1 space-y-1">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div>{children}</div>}
      </div>
    </div>
  )
}
