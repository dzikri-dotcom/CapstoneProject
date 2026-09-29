import cn from '@/utils/cn'

/** Kerangka pemuatan berkilau (menggantikan spinner agar tata letak tidak "melompat"). */
export default function Skeleton({ className }) {
  return (
    <div className={cn('relative overflow-hidden rounded-lg bg-slate-100', className)} aria-hidden="true">
      <div className="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-white/70 to-transparent" />
    </div>
  )
}

/** Beberapa baris kerangka untuk tabel/daftar. */
export function SkeletonRows({ rows = 5 }) {
  return (
    <div className="space-y-3 p-5" role="status" aria-label="Memuat">
      {Array.from({ length: rows }, (_, index) => (
        <div key={index} className="flex items-center gap-4">
          <Skeleton className="size-9 rounded-full" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-3.5 w-1/3" />
            <Skeleton className="h-3 w-1/5" />
          </div>
          <Skeleton className="h-6 w-16 rounded-full" />
        </div>
      ))}
    </div>
  )
}
