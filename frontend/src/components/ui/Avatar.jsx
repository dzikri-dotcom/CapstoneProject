import cn from '@/utils/cn'
import { initials } from '@/utils/format'

const sizes = {
  sm: 'size-8 text-xs',
  md: 'size-10 text-sm',
  lg: 'size-14 text-lg',
}

/** Warna avatar dipilih dari nama agar setiap orang konsisten punya warnanya sendiri. */
const palettes = [
  'from-rose-400 to-red-500',
  'from-orange-400 to-amber-500',
  'from-emerald-400 to-teal-500',
  'from-sky-400 to-blue-500',
  'from-violet-400 to-purple-500',
  'from-pink-400 to-fuchsia-500',
  'from-cyan-400 to-sky-500',
  'from-indigo-400 to-violet-500',
]

function paletteFor(name = '') {
  let hash = 0
  for (const char of name) hash = (hash * 31 + char.charCodeAt(0)) >>> 0
  return palettes[hash % palettes.length]
}

export default function Avatar({ name, size = 'md', className }) {
  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br font-bold text-white shadow-sm ring-2 ring-white',
        paletteFor(name),
        sizes[size],
        className,
      )}
      aria-hidden="true"
    >
      {initials(name)}
    </span>
  )
}
