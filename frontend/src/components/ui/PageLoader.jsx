import Spinner from './Spinner'

/** Penanda saat kode halaman sedang diunduh (di dalam layout, sidebar/menu tetap tampil). */
export default function PageLoader() {
  return (
    <div className="flex items-center justify-center py-24">
      <Spinner className="size-6 text-brand-600" />
    </div>
  )
}
