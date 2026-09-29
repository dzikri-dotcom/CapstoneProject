import api from './api'

export async function getSettings() {
  const { data } = await api.get('/admin/settings')
  return data.data
}

export async function updateSettings(payload) {
  const { data } = await api.put('/admin/settings', payload)
  return data.data
}

export async function getOperationalHours() {
  const { data } = await api.get('/admin/operational-hours')
  return data.data
}

export async function updateOperationalHours(days) {
  const { data } = await api.put('/admin/operational-hours', { days })
  return data.data
}
