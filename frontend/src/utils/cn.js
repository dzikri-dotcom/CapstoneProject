/**
 * Gabungkan class Tailwind, abaikan nilai kosong/false.
 * Contoh: cn('px-4', active && 'bg-brand-50')
 */
export default function cn(...classes) {
  return classes.filter(Boolean).join(' ')
}
