import { useId } from 'react'
import cn from '@/utils/cn'
import fieldClass from './fieldClass'
import { FieldLabel, FieldMessage } from './Input'

export default function Select({ label, error, hint, required, options = [], className, ...props }) {
  const id = useId()

  return (
    <div className={className}>
      {label && (
        <FieldLabel htmlFor={id} required={required}>
          {label}
        </FieldLabel>
      )}
      <select id={id} required={required} aria-invalid={Boolean(error)} className={cn(fieldClass(error), 'pr-8')} {...props}>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <FieldMessage error={error} hint={hint} />
    </div>
  )
}
