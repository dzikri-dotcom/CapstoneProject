import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react'
import { useCallback, useMemo, useRef, useState } from 'react'
import cn from '@/utils/cn'
import ToastContext from './ToastContext'

const styles = {
  success: { icon: CheckCircle2, color: 'text-emerald-600', bar: 'bg-emerald-500' },
  error: { icon: XCircle, color: 'text-red-600', bar: 'bg-red-500' },
  warning: { icon: AlertTriangle, color: 'text-amber-500', bar: 'bg-amber-400' },
  info: { icon: Info, color: 'text-sky-600', bar: 'bg-sky-500' },
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
          const { icon: Icon, color, bar } = styles[type]
          return (
            <div
              key={id}
              role="status"
              className="pointer-events-auto relative w-full max-w-sm animate-toast-in overflow-hidden rounded-xl border border-slate-200 bg-white shadow-pop"
            >
              <div className="flex items-start gap-3 px-4 py-3 text-sm">
                <Icon className={cn('mt-0.5 size-4 shrink-0', color)} />
                <p className="flex-1 text-slate-700">{message}</p>
                <button
                  type="button"
                  onClick={() => dismiss(id)}
                  className="-mt-0.5 -mr-1 rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                  aria-label="Tutup notifikasi"
                >
                  <X className="size-3.5" />
                </button>
              </div>
              <div className={cn('h-px animate-shrink opacity-60', bar)} style={{ animationDuration: `${duration}ms` }} />
            </div>
          )
        })}
      </div>
    </ToastContext>
  )
}
