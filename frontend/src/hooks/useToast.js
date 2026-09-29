import { use } from 'react'
import ToastContext from '@/context/ToastContext'

export default function useToast() {
  const context = use(ToastContext)
  if (!context) throw new Error('useToast harus dipakai di dalam <ToastProvider>')
  return context
}
