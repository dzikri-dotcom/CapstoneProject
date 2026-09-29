import api from './api'

/** @returns {{ data: object[], meta: object, summary: object }} */
export async function listStaff(params) {
  const { data } = await api.get('/admin/staff', { params })
  return data
}

export async function getStaff(id) {
  const { data } = await api.get(`/admin/staff/${id}`)
  return data.data
}

export async function createStaff(payload) {
  const { data } = await api.post('/admin/staff', payload)
  return data.data
}

export async function updateStaff(id, payload) {
  const { data } = await api.put(`/admin/staff/${id}`, payload)
  return data.data
}

export async function deleteStaff(id) {
  await api.delete(`/admin/staff/${id}`)
}

export async function resetStaffPassword(id, password) {
  await api.post(`/admin/staff/${id}/reset-password`, { password })
}
