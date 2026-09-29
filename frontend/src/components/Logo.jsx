import { PackageCheck } from 'lucide-react'
import cn from '@/utils/cn'

export default function Logo({ compact = false, inverted = false, iconOnly = false }) {
  return (
    <div className="flex min-w-0 items-center gap-2.5">
      <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white">
        <PackageCheck className="size-[18px]" />
      </div>
      {!iconOnly && (
        <div className="min-w-0 leading-tight">
          <p className={cn('truncate text-sm font-semibold', inverted ? 'text-white' : 'text-slate-900')}>Presensi Logistik</p>
          {!compact && <p className={cn('truncate text-xs', inverted ? 'text-white/50' : 'text-slate-500')}>Telkom University Surabaya</p>}
        </div>
      )}
    </div>
  )
}
