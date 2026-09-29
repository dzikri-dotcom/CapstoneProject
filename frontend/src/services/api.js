import axios from 'axios'

const TOKEN_KEY = 'presensi_logistik_token'
export const UNAUTHORIZED_EVENT = 'auth:unauthorized'

export const getToken = () => localStorage.getItem(TOKEN_KEY)
export const setToken = (token) => localStorage.setItem(TOKEN_KEY, token)
export const clearToken = () => localStorage.removeItem(TOKEN_KEY)

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
})

// Sertakan token Sanctum pada setiap request jika pengguna sudah login.
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Token kedaluwarsa/dicabut → hapus token dan beri tahu AuthProvider agar kembali ke login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && getToken()) {
      clearToken()
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT))
    }
    return Promise.reject(error)
  },
)

/**
 * Ubah error dari Axios menjadi satu kalimat yang bisa ditampilkan ke pengguna.
 */
export function getErrorMessage(error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') {
  if (!error?.response) {
    return 'Tidak dapat terhubung ke server. Pastikan backend sudah berjalan.'
  }

  const { status, data } = error.response
  if (status === 422 && data?.errors) return Object.values(data.errors).flat()[0]
  if (data?.message) return data.message
  if (status === 401) return 'Sesi Anda telah berakhir. Silakan login kembali.'
  if (status === 403) return 'Anda tidak memiliki akses ke fitur ini.'
  if (status === 404) return 'Data tidak ditemukan.'
  if (status >= 500) return 'Terjadi kesalahan pada server.'
  return fallback
}

/**
 * Ambil error validasi (422) per kolom: { email: 'Email sudah digunakan.' }.
 */
export function getValidationErrors(error) {
  const errors = error?.response?.status === 422 ? error.response.data?.errors : null
  if (!errors) return {}
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

export default api
