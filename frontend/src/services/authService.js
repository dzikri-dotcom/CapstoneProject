import api from './api'

export async function login(email, password) {
  const { data } = await api.post('/login', { email, password })
  return data // { token, user }
}

export async function logout() {
  await api.post('/logout')
}

export async function me() {
  const { data } = await api.get('/me')
  return data.data
}

export async function updatePassword(payload) {
  const { data } = await api.put('/me/password', payload)
  return data
}
