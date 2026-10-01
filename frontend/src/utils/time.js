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
 * Shift aktif yang berjalan pada satu hari operasional, sama seperti backend:
 * shift dipotong ke jam buka–tutup; yang seluruhnya di luar jam operasional dilewati.
 * shiftsWithin('08:00', '15:00', [08–12, 13–17, 17–20]) → 08–12, 13–15
 */
export function shiftsWithin(open, close, shifts) {
  return shifts
    .filter((shift) => shift.is_active)
    .map((shift) => ({
      ...shift,
      start: fromMinutes(Math.max(toMinutes(shift.start_time), toMinutes(open))),
      end: fromMinutes(Math.min(toMinutes(shift.end_time), toMinutes(close))),
    }))
    .filter((shift) => shift.start < shift.end)
    .sort((a, b) => a.start.localeCompare(b.start))
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
