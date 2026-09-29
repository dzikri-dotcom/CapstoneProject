import { Link } from 'react-router'
import cn from '@/utils/cn'

export default function NotFound({ embedded = false }) {
  return (
    <main
      className={cn(
        'flex flex-col items-center justify-center gap-4 p-6 text-center',
        embedded ? 'py-24' : 'min-h-screen',
      )}
    >
      <p className="text-6xl font-bold text-brand-600">404</p>
      <h1 className="text-xl font-semibold">Halaman tidak ditemukan</h1>
      <p className="text-slate-500">Alamat yang Anda buka tidak tersedia.</p>
      <Link to="/" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        Kembali ke beranda
      </Link>
    </main>
  )
}
