import { RefreshCw, TriangleAlert } from 'lucide-react'
import Button from '@/components/ui/Button'
import ErrorBoundary from '@/components/ui/ErrorBoundary'
import AuthProvider from '@/context/AuthProvider'
import ToastProvider from '@/context/ToastProvider'
import AppRouter from '@/routes/AppRouter'

function CrashScreen() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <div className="flex size-14 items-center justify-center rounded-full bg-red-50 text-red-600">
        <TriangleAlert className="size-7" />
      </div>
      <div>
        <p className="font-semibold text-slate-800">Terjadi kesalahan pada tampilan</p>
        <p className="mt-1 max-w-sm text-sm text-slate-500">
          Coba muat ulang halaman. Jika masalah berlanjut, periksa koneksi internet Anda.
        </p>
      </div>
      <Button icon={RefreshCw} onClick={() => window.location.reload()}>
        Muat ulang
      </Button>
    </main>
  )
}

export default function App() {
  return (
    <ErrorBoundary fallback={() => <CrashScreen />}>
      <ToastProvider>
        <AuthProvider>
          <AppRouter />
        </AuthProvider>
      </ToastProvider>
    </ErrorBoundary>
  )
}
