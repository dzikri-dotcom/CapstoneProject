import cn from '@/utils/cn'

/**
 * Class Tailwind untuk input/select. Warna fokus sengaja netral (abu gelap), bukan merah brand,
 * agar kolom yang sedang diisi tidak tertukar dengan kolom yang error (merah).
 */
export default function fieldClass(error) {
  return cn(
    'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-xs outline-none transition duration-200',
    'placeholder:text-slate-400 hover:border-slate-400 focus:ring-4 disabled:bg-slate-50 disabled:text-slate-500',
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
      : 'border-slate-300 focus:border-slate-600 focus:ring-slate-200/70',
  )
}
