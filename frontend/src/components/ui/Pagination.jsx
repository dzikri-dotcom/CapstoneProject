import { ChevronLeft, ChevronRight } from 'lucide-react'
import Button from './Button'

/**
 * Navigasi halaman untuk response paginasi Laravel (objek `meta`).
 */
export default function Pagination({ meta, onPageChange, itemLabel = 'data' }) {
  if (!meta || meta.total === 0) return null

  const { current_page: page, last_page: lastPage, from, to, total } = meta

  return (
    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm sm:flex-row">
      <p className="text-slate-500">
        Menampilkan <span className="font-medium text-slate-700">{from}–{to}</span> dari{' '}
        <span className="font-medium text-slate-700">{total}</span> {itemLabel}
      </p>
      {lastPage > 1 && (
        <div className="flex items-center gap-2">
          <Button variant="secondary" size="sm" icon={ChevronLeft} disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
            Sebelumnya
          </Button>
          <span className="px-1 text-slate-500">
            {page} / {lastPage}
          </span>
          <Button variant="secondary" size="sm" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>
            Berikutnya
            <ChevronRight className="size-4" />
          </Button>
        </div>
      )}
    </div>
  )
}
