import api from './api'

/* ---------- Admin ---------- */

/** Token QR yang sedang berlaku: { token, expires_at, server_time, refresh_seconds } */
export async function getQrToken() {
  const { data } = await api.post('/admin/qr-tokens/current')
  return data.data
}

/** Penugasan shift pada satu tanggal beserta presensinya: { data, summary, date } */
export async function listAttendances(params) {
  const { data } = await api.get('/admin/attendances', { params })
  return data
}

export async function correctAttendance(assignmentId, payload) {
  const { data } = await api.put(`/admin/shift-assignments/${assignmentId}/attendance`, payload)
  return data
}

/* ---------- Staff ---------- */

/** { check_in, check_out, today, next, server_time } */
export async function getMyAttendanceStatus() {
  const { data } = await api.get('/staff/attendance/current')
  return data.data
}

/**
 * Satu scan QR: server menentukan check-in, check-out, atau pergantian sesi.
 * { message, action: 'check_in' | 'check_out' | 'handover', data: status terbaru }
 */
export async function scanAttendance(token) {
  const { data } = await api.post('/staff/attendance/scan', { token })
  return data
}

export async function checkIn(token) {
  const { data } = await api.post('/staff/attendance/check-in', { token })
  return data
}

export async function checkOut(token) {
  const { data } = await api.post('/staff/attendance/check-out', { token })
  return data
}

/** { data, summary, month } */
export async function getMyAttendanceHistory(month) {
  const { data } = await api.get('/staff/attendances', { params: { month } })
  return data
}
