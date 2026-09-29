import { downloadFile } from '@/utils/download'
import api from './api'

export async function getAdminDashboard() {
  const { data } = await api.get('/admin/dashboard')
  return data.data
}

export async function getStaffDashboard() {
  const { data } = await api.get('/staff/dashboard')
  return data.data
}

/** @param {{ start_date: string, end_date: string }} range */
export async function getFairnessReport(range) {
  const { data } = await api.get('/admin/reports/fairness', { params: range })
  return data.data
}

export async function getAttendanceReport(range) {
  const { data } = await api.get('/admin/reports/attendance', { params: range })
  return data.data
}

export function exportAttendanceReport(range) {
  return downloadFile(
    api.get('/admin/reports/attendance/export', { params: range, responseType: 'blob' }),
    'rekap-kehadiran.xlsx',
  )
}
