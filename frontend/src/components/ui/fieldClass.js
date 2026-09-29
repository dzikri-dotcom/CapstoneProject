import cn from '@/utils/cn'

/**
 * Class Tailwind untuk input/select. Warna fokus sengaja netral (abu gelap), bukan merah brand,
 * agar kolom yang sedang diisi tidak tertukar dengan kolom yang error (merah).
 */
export default function fieldClass(error) {
  return cn(
    'block h-9 w-full rounded-lg border bg-white px-3 text-sm text-slate-800 shadow-card outline-none transition duration-150',
    'placeholder:text-slate-400 focus:ring-[3px] disabled:bg-slate-50 disabled:text-slate-500',
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
      : 'border-slate-200 hover:border-slate-300 focus:border-slate-400 focus:ring-slate-200/60',
  )
}
