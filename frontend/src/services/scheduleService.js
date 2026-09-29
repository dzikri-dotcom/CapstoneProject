import api from './api'

/* ---------- Periode jadwal (admin) ---------- */

/** @returns {{ data, meta, links, next_pointer }} */
export async function listPeriods(page = 1) {
  const { data } = await api.get('/admin/schedule-periods', { params: { page } })
  return data
}

export async function createPeriod(payload) {
  const { data } = await api.post('/admin/schedule-periods', payload)
  return data.data
}

/** @returns {{ data: period dengan slots, meta: { warnings, staff_hours } }} */
export async function getPeriod(id) {
  const { data } = await api.get(`/admin/schedule-periods/${id}`)
  return data
}

export async function deletePeriod(id) {
  const { data } = await api.delete(`/admin/schedule-periods/${id}`)
  return data
}

export async function generatePeriod(id) {
  const { data } = await api.post(`/admin/schedule-periods/${id}/generate`)
  return data
}

export async function publishPeriod(id) {
  const { data } = await api.post(`/admin/schedule-periods/${id}/publish`)
  return data
}

export async function unpublishPeriod(id) {
  const { data } = await api.post(`/admin/schedule-periods/${id}/unpublish`)
  return data
}

/** @returns {{ data: log[], summary, period }} */
export async function getPeriodLogs(id) {
  const { data } = await api.get(`/admin/schedule-periods/${id}/logs`)
  return data
}

/* ---------- Edit manual penugasan (admin) ---------- */

export async function getCandidates(slotId) {
  const { data } = await api.get(`/admin/shift-slots/${slotId}/candidates`)
  return data.data
}

export async function addAssignment(slotId, staffProfileId) {
  const { data } = await api.post(`/admin/shift-slots/${slotId}/assignments`, { staff_profile_id: staffProfileId })
  return data
}

export async function updateAssignment(id, payload) {
  const { data } = await api.put(`/admin/shift-assignments/${id}`, payload)
  return data
}

export async function swapAssignments(firstId, secondId) {
  const { data } = await api.post('/admin/shift-assignments/swap', {
    first_assignment_id: firstId,
    second_assignment_id: secondId,
  })
  return data
}

export async function removeAssignment(id) {
  const { data } = await api.delete(`/admin/shift-assignments/${id}`)
  return data
}

/* ---------- Jadwal shift saya (staff) ---------- */

export async function getMyShifts(params) {
  const { data } = await api.get('/staff/shifts', { params })
  return data
}
