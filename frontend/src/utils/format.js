const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
const longDateFormatter = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const shortDateFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })
const dayMonthFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' })
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

/**
 * "2026-09-28" dibaca sebagai tanggal lokal (bukan UTC), agar tidak bergeser sehari.
 * Nilai lain (ISO datetime / Date) diteruskan apa adanya.
 */
export function toDate(value) {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [year, month, day] = value.split('-').map(Number)
    return new Date(year, month - 1, day)
  }
  return new Date(value)
}

/** "24 September 2026" */
export function formatDate(value) {
  return value ? dateFormatter.format(toDate(value)) : '-'
}

/** "Kamis, 24 September 2026" */
export function formatLongDate(value = new Date()) {
  return longDateFormatter.format(toDate(value))
}

/** "Sen, 28 Sep" */
export function formatShortDate(value) {
  return shortDateFormatter.format(toDate(value))
}

/** "28 Sep" */
export function formatDayMonth(value) {
  return dayMonthFormatter.format(toDate(value))
}

/** 528 → "8,8 jam" */
export function formatHours(minutes) {
  return `${(minutes / 60).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jam`
}

/** "28 Sep – 4 Okt 2026" */
export function formatDateRange(start, end) {
  const endDate = toDate(end)
  return `${dayMonthFormatter.format(toDate(start))} – ${dayMonthFormatter.format(endDate)} ${endDate.getFullYear()}`
}

/** "24 Sep 2026, 13.05" */
export function formatDateTime(value) {
  return value ? dateTimeFormatter.format(new Date(value)) : '-'
}

/** "Muhammad Rizky Ramadhan" → "Muhammad R." */
export function shortName(name = '') {
  const [first, second] = name.split(' ')
  return second ? `${first} ${second[0]}.` : first
}

const rupiahFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

/** 11000 → "Rp 11.000" */
export function formatRupiah(value) {
  return value === null || value === undefined || value === '' ? '-' : rupiahFormatter.format(value)
}

/** 270 → "4 jam 30 menit" (short: "4j 30m") */
export function formatDuration(minutes, { short = false } = {}) {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  const parts = []
  if (hours) parts.push(short ? `${hours}j` : `${hours} jam`)
  if (rest || !hours) parts.push(short ? `${rest}m` : `${rest} menit`)
  return parts.join(' ')
}

/** "Andi Pratama" → "AP"; nama satu kata memakai dua huruf pertama: "Bintang" → "BI" */
export function initials(name = '') {
  const words = name.split(' ').filter(Boolean)
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return words
    .slice(0, 2)
    .map((word) => word[0].toUpperCase())
    .join('')
}

export function firstName(name = '') {
  return name.split(' ')[0]
}

/** Password acak mudah dibaca (tanpa karakter mirip seperti 0/O, 1/l). */
export function generatePassword(length = 10) {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'
  const values = crypto.getRandomValues(new Uint32Array(length))
  return Array.from(values, (value) => chars[value % chars.length]).join('')
}
