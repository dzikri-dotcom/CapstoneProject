import cn from '@/utils/cn'
import { initials } from '@/utils/format'

const sizes = {
  sm: 'size-8 text-xs',
  md: 'size-10 text-sm',
  lg: 'size-14 text-lg',
}

/** Warna lembut dipilih dari nama agar setiap orang konsisten punya warnanya sendiri. */
const palettes = [
  'bg-rose-100 text-rose-700',
  'bg-amber-100 text-amber-800',
  'bg-emerald-100 text-emerald-700',
  'bg-sky-100 text-sky-700',
  'bg-indigo-100 text-indigo-700',
  'bg-teal-100 text-teal-700',
  'bg-orange-100 text-orange-700',
  'bg-slate-200 text-slate-700',
]

function paletteFor(name = '') {
  let hash = 0
  for (const char of name) hash = (hash * 31 + char.charCodeAt(0)) >>> 0
  return palettes[hash % palettes.length]
}

export default function Avatar({ name, size = 'md', className }) {
  return (
    <span
      className={cn('inline-flex shrink-0 items-center justify-center rounded-full font-semibold', paletteFor(name), sizes[size], className)}
      aria-hidden="true"
    >
      {initials(name)}
    </span>
  )
}
