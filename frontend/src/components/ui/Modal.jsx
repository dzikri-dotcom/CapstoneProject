import { X } from 'lucide-react'
import { useEffect, useId } from 'react'
import { createPortal } from 'react-dom'
import cn from '@/utils/cn'

const sizes = {
  sm: 'sm:max-w-md',
  md: 'sm:max-w-lg',
  lg: 'sm:max-w-2xl',
  xl: 'sm:max-w-4xl',
}

/**
 * Dialog di tengah layar (desktop) atau muncul dari bawah (HP).
 * Tutup dengan tombol X, klik area gelap, atau tombol Esc.
 */
export default function Modal({ open, onClose, title, description, children, footer, size = 'md' }) {
  const titleId = useId()

  useEffect(() => {
    if (!open) return undefined

    const handleKey = (event) => event.key === 'Escape' && onClose?.()
    const previousOverflow = document.body.style.overflow
    document.addEventListener('keydown', handleKey)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', handleKey)
      document.body.style.overflow = previousOverflow
    }
  }, [open, onClose])

  if (!open) return null

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
      <div className="absolute inset-0 animate-fade-in bg-slate-900/40" onClick={onClose} aria-hidden="true" />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cn(
          'relative flex max-h-[92vh] w-full animate-sheet-up flex-col rounded-t-2xl bg-white shadow-pop ring-1 ring-slate-900/10',
          'sm:animate-scale-in sm:rounded-xl',
          sizes[size],
        )}
      >
        {/* Pegangan geser (tampilan bottom sheet di HP) */}
        <div className="mx-auto mt-2 h-1 w-9 rounded-full bg-slate-200 sm:hidden" aria-hidden="true" />
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <h2 id={titleId} className="text-[15px] font-semibold tracking-tight text-slate-900">
              {title}
            </h2>
            {description && <p className="mt-0.5 text-[13px] text-slate-500">{description}</p>}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="-mr-1.5 rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
            aria-label="Tutup"
          >
            <X className="size-4" />
          </button>
        </div>
        <div className="overflow-y-auto px-5 py-4">{children}</div>
        {footer && (
          <div className="flex flex-col-reverse gap-2 rounded-b-xl border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:flex-row sm:justify-end">
            {footer}
          </div>
        )}
      </div>
    </div>,
    document.body,
  )
}
