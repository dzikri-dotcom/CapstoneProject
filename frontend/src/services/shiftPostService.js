import api from './api'

/** Shift (jam kerja untuk presensi) dan pos jaga — keduanya diatur admin. */
function resource(path) {
  return {
    async list() {
      const { data } = await api.get(path)
      return data.data
    },
    async create(payload) {
      const { data } = await api.post(path, payload)
      return data
    },
    async update(id, payload) {
      const { data } = await api.put(`${path}/${id}`, payload)
      return data
    },
    async remove(id) {
      const { data } = await api.delete(`${path}/${id}`)
      return data
    },
  }
}

export const shiftApi = resource('/admin/shifts')
export const postApi = resource('/admin/posts')
