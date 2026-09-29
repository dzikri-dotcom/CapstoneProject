import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react'
import { useCallback, useMemo, useRef, useState } from 'react'
import cn from '@/utils/cn'
import ToastContext from './ToastContext'

const styles = {
  success: { icon: CheckCircle2, color: 'text-emerald-600', bar: 'bg-emerald-500', ring: 'bg-emerald-50' },
  error: { icon: XCircle, color: 'text-red-600', bar: 'bg-red-500', ring: 'bg-red-50' },
  warning: { icon: AlertTriangle, color: 'text-amber-500', bar: 'bg-amber-400', ring: 'bg-amber-50' },
  info: { icon: Info, color: 'text-sky-600', bar: 'bg-sky-500', ring: 'bg-sky-50' },
}

/**
 * Notifikasi singkat di pojok layar. Pakai lewat hook useToast():
 * toast.success('Data tersimpan'), toast.error('Gagal menyimpan')
 */
export default function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const nextId = useRef(0)

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  const show = useCallback(
    (type, message) => {
      const id = ++nextId.current
      const duration = type === 'warning' ? 7000 : 4000
      setToasts((current) => [...current, { id, type, message, duration }])
      setTimeout(() => dismiss(id), duration)
    },
    [dismiss],
  )

  const value = useMemo(
    () => ({
      success: (message) => show('success', message),
      error: (message) => show('error', message),
      warning: (message) => show('warning', message),
      info: (message) => show('info', message),
    }),
    [show],
  )

  return (
    <ToastContext value={value}>
      {children}
      <div
        aria-live="polite"
        className="pointer-events-none fixed inset-x-4 top-4 z-[60] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-4 sm:items-end"
      >
        {toasts.map(({ id, type, message, duration }) => {
          const { icon: Icon, color, bar, ring } = styles[type]
          return (
            <div
              key={id}
              role="status"
              className="glass pointer-events-auto relative w-full max-w-sm animate-toast-in overflow-hidden rounded-2xl border border-white/60 shadow-lift ring-1 ring-slate-900/5"
            >
              <div className="flex items-start gap-3 p-4 text-sm">
                <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', ring)}>
                  <Icon className={cn('size-5', color)} />
                </span>
                <p className="flex-1 pt-1 text-slate-700">{message}</p>
                <button
                  type="button"
                  onClick={() => dismiss(id)}
                  className="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                  aria-label="Tutup notifikasi"
                >
                  <X className="size-4" />
                </button>
              </div>
              <div className={cn('h-0.5 animate-shrink', bar)} style={{ animationDuration: `${duration}ms` }} />
            </div>
          )
        })}
      </div>
    </ToastContext>
  )
}
