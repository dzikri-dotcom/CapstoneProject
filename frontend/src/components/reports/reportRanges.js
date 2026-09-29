import { addDaysISO, todayISO } from '@/utils/time'

/** Rentang tanggal cepat untuk laporan. */
const pad = (value) => String(value).padStart(2, '0')
const iso = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

function mondayThisWeek() {
  const day = new Date().getDay()
  return addDaysISO(todayISO(), day === 0 ? -6 : 1 - day)
}

export const RANGE_PRESETS = {
  thisWeek: () => ({ start_date: mondayThisWeek(), end_date: addDaysISO(mondayThisWeek(), 6) }),
  last4Weeks: () => ({ start_date: addDaysISO(mondayThisWeek(), -21), end_date: addDaysISO(mondayThisWeek(), 6) }),
  thisMonth: () => {
    const now = new Date()
    return { start_date: iso(new Date(now.getFullYear(), now.getMonth(), 1)), end_date: iso(new Date(now.getFullYear(), now.getMonth() + 1, 0)) }
  },
  lastMonth: () => {
    const now = new Date()
    return { start_date: iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)), end_date: iso(new Date(now.getFullYear(), now.getMonth(), 0)) }
  },
}
