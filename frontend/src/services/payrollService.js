import { downloadFile } from '@/utils/download'
import api from './api'

/* ---------- Admin ---------- */

export async function listPayrollPeriods(page = 1) {
  const { data } = await api.get('/admin/payroll-periods', { params: { page } })
  return data
}

export async function createPayrollPeriod(payload) {
  const { data } = await api.post('/admin/payroll-periods', payload)
  return data.data
}

export async function getPayrollPeriod(id) {
  const { data } = await api.get(`/admin/payroll-periods/${id}`)
  return data.data
}

export async function deletePayrollPeriod(id) {
  const { data } = await api.delete(`/admin/payroll-periods/${id}`)
  return data
}

export async function calculatePayroll(id) {
  const { data } = await api.post(`/admin/payroll-periods/${id}/calculate`)
  return data
}

export async function finalizePayroll(id) {
  const { data } = await api.post(`/admin/payroll-periods/${id}/finalize`)
  return data
}

export function exportPayroll(id) {
  return downloadFile(api.get(`/admin/payroll-periods/${id}/export`, { responseType: 'blob' }), 'rekap-gaji.xlsx')
}

/** Rekap satu staff beserta rincian per tanggal. */
export async function getPayrollDetail(detailId) {
  const { data } = await api.get(`/admin/payroll-details/${detailId}`)
  return data.data
}

export function downloadPayslip(detailId) {
  return downloadFile(api.get(`/admin/payroll-details/${detailId}/slip`, { responseType: 'blob' }), 'slip-gaji.pdf')
}

/* ---------- Staff ---------- */

export async function getMyPayrolls() {
  const { data } = await api.get('/staff/payrolls')
  return data.data
}

export async function getMyPayroll(detailId) {
  const { data } = await api.get(`/staff/payrolls/${detailId}`)
  return data.data
}

export function downloadMyPayslip(detailId) {
  return downloadFile(api.get(`/staff/payrolls/${detailId}/slip`, { responseType: 'blob' }), 'slip-gaji.pdf')
}
