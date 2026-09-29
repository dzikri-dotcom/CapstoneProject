import { Eye, EyeOff } from 'lucide-react'
import { useId, useState } from 'react'
import cn from '@/utils/cn'
import fieldClass from './fieldClass'

export function FieldLabel({ htmlFor, children, required }) {
  return (
    <label htmlFor={htmlFor} className="mb-1.5 block text-sm font-medium text-slate-700">
      {children}
      {required && (
        <span className="ml-0.5 text-brand-600" aria-hidden="true">
          *
        </span>
      )}
    </label>
  )
}

export function FieldMessage({ error, hint }) {
  if (error) return <p className="mt-1.5 text-xs text-red-600">{error}</p>
  if (hint) return <p className="mt-1.5 text-xs text-slate-500">{hint}</p>
  return null
}

/**
 * Input teks dengan label & pesan error. type="password" otomatis punya tombol lihat/sembunyikan.
 */
export default function Input({ label, error, hint, required, className, type = 'text', addon, ...props }) {
  const id = useId()
  const [showPassword, setShowPassword] = useState(false)
  const isPassword = type === 'password'

  return (
    <div className={className}>
      {label && (
        <FieldLabel htmlFor={id} required={required}>
          {label}
        </FieldLabel>
      )}
      <div className="flex gap-2">
        <div className="relative flex-1">
          <input
            id={id}
            type={isPassword && showPassword ? 'text' : type}
            required={required}
            aria-invalid={Boolean(error)}
            className={cn(fieldClass(error), isPassword && 'pr-10')}
            {...props}
          />
          {isPassword && (
            <button
              type="button"
              onClick={() => setShowPassword((value) => !value)}
              className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600"
              aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          )}
        </div>
        {addon}
      </div>
      <FieldMessage error={error} hint={hint} />
    </div>
  )
}
