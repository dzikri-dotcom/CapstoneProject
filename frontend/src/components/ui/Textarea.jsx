import { useId } from 'react'
import cn from '@/utils/cn'
import fieldClass from './fieldClass'
import { FieldLabel, FieldMessage } from './Input'

export default function Textarea({ label, error, hint, required, className, rows = 3, ...props }) {
  const id = useId()

  return (
    <div className={className}>
      {label && (
        <FieldLabel htmlFor={id} required={required}>
          {label}
        </FieldLabel>
      )}
      <textarea
        id={id}
        rows={rows}
        required={required}
        aria-invalid={Boolean(error)}
        className={cn(fieldClass(error), 'h-auto resize-y py-2')}
        {...props}
      />
      <FieldMessage error={error} hint={hint} />
    </div>
  )
}
