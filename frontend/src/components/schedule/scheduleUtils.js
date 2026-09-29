import { formatShortDate } from '@/utils/format'

/** "Sen, 28 Sep · 08:00–10:00" */
export function slotLabel(slot) {
  return `${formatShortDate(slot.date)} · ${slot.start_time}–${slot.end_time}`
}

/**
 * Tampilkan hasil edit manual: pesan sukses + setiap peringatan (bentrok kuliah / melebihi batas jam).
 */
export function notifyResult(toast, response) {
  toast.success(response.message)
  response.warnings?.forEach((warning) => toast.warning(`Peringatan: ${warning.message}`))
}

/** Rata-rata, selisih tertinggi–terendah, dan standar deviasi (populasi) dalam menit. */
export function distributionStats(values) {
  if (!values.length) return { mean: 0, range: 0, sd: 0 }
  const mean = values.reduce((sum, value) => sum + value, 0) / values.length
  const variance = values.reduce((sum, value) => sum + (value - mean) ** 2, 0) / values.length
  return { mean, range: Math.max(...values) - Math.min(...values), sd: Math.sqrt(variance) }
}
