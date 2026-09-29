import api from './api'

/** Tarif yang berlaku hari ini per slot jam: { effective_date, slots, missing_count, locked_until } */
export async function getCurrentRates() {
  const { data } = await api.get('/admin/hourly-rates')
  return data.data
}

export async function getRateHistory() {
  const { data } = await api.get('/admin/hourly-rates/history')
  return data.data
}

export async function saveRates(effectiveDate, rates) {
  const { data } = await api.post('/admin/hourly-rates', { effective_date: effectiveDate, rates })
  return data.data
}

export async function deleteRateVersion(effectiveDate) {
  await api.delete(`/admin/hourly-rates/${effectiveDate}`)
}
