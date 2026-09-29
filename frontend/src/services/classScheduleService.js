import api from './api'

/**
 * Jadwal kuliah bisa dikelola staff (miliknya sendiri) atau admin (staff tertentu).
 * Keduanya memakai "adapter" dengan bentuk sama agar komponen UI bisa dipakai bersama.
 */

export const myClassScheduleApi = {
  key: 'me',
  list: async () => (await api.get('/staff/class-schedules')).data.data,
  create: async (payload) => (await api.post('/staff/class-schedules', payload)).data.data,
  update: async (id, payload) => (await api.put(`/staff/class-schedules/${id}`, payload)).data.data,
  remove: async (id) => api.delete(`/staff/class-schedules/${id}`),
  availability: async () => (await api.get('/staff/availability')).data.data,
}

export function staffClassScheduleApi(staffId) {
  return {
    key: `staff-${staffId}`,
    list: async () => (await api.get(`/admin/staff/${staffId}/class-schedules`)).data.data,
    create: async (payload) => (await api.post(`/admin/staff/${staffId}/class-schedules`, payload)).data.data,
    update: async (id, payload) => (await api.put(`/admin/class-schedules/${id}`, payload)).data.data,
    remove: async (id) => api.delete(`/admin/class-schedules/${id}`),
    availability: async () => (await api.get(`/admin/staff/${staffId}/availability`)).data.data,
  }
}
