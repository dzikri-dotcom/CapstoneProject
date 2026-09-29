import { RefreshCw, WifiOff } from 'lucide-react'
import Button from './Button'
import Spinner from './Spinner'

/**
 * Layar penuh saat aplikasi memeriksa sesi login, atau saat server tidak bisa dihubungi.
 */
export default function LoadingScreen({ error, onRetry }) {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      {error ? (
        <>
          <div className="flex size-14 items-center justify-center rounded-full bg-red-50 text-red-600">
            <WifiOff className="size-7" />
          </div>
          <div>
            <p className="font-semibold text-slate-800">Tidak dapat terhubung ke server</p>
            <p className="mt-1 max-w-sm text-sm text-slate-500">{error}</p>
          </div>
          {onRetry && (
            <Button icon={RefreshCw} onClick={onRetry}>
              Coba lagi
            </Button>
          )}
        </>
      ) : (
        <>
          <Spinner className="size-8 text-brand-600" />
          <p className="text-sm text-slate-500">Memuat aplikasi…</p>
        </>
      )}
    </main>
  )
}
