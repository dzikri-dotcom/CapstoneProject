import { use } from 'react'
import AuthContext from '@/context/AuthContext'

export default function useAuth() {
  const context = use(AuthContext)
  if (!context) throw new Error('useAuth harus dipakai di dalam <AuthProvider>')
  return context
}
