/** "08:30" → 510 (menit sejak 00:00) */
export function toMinutes(time) {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

/** 510 → "08:30" */
export function fromMinutes(minutes) {
  const pad = (value) => String(value).padStart(2, '0')
  return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`
}

/**
 * Pecah jam operasional satu hari menjadi slot shift, sama seperti backend:
 * mulai dari jam buka, tiap `duration` menit; slot terakhir dipendekkan sampai jam tutup.
 * buildDailySlots('08:00', '17:00', 120) → 08–10, 10–12, 12–14, 14–16, 16–17
 */
export function buildDailySlots(open, close, duration) {
  const slots = []
  const end = toMinutes(close)
  for (let start = toMinutes(open); start < end; start += duration) {
    slots.push({ start: fromMinutes(start), end: fromMinutes(Math.min(start + duration, end)) })
  }
  return slots
}

/**
 * Pembulatan durasi kerja untuk penggajian (aturan yang sama dipakai backend).
 * roundMinutes(100, 30, 'down') → 90, roundMinutes(100, 30, 'nearest') → 90, roundMinutes(110, 30, 'nearest') → 120
 */
export function roundMinutes(minutes, unit, mode) {
  if (unit <= 1) return minutes
  return mode === 'nearest' ? Math.round(minutes / unit) * unit : Math.floor(minutes / unit) * unit
}

/** Tanggal hari ini (zona waktu perangkat) dalam format YYYY-MM-DD. */
export function todayISO() {
  const now = new Date()
  const pad = (value) => String(value).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

/** "2026-08-31" → "2026-09-01" */
export function addDaysISO(date, days) {
  const [year, month, day] = date.split('-').map(Number)
  const result = new Date(year, month - 1, day + days)
  const pad = (value) => String(value).padStart(2, '0')
  return `${result.getFullYear()}-${pad(result.getMonth() + 1)}-${pad(result.getDate())}`
}
