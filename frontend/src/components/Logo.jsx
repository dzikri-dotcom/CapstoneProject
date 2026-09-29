import { PackageCheck } from 'lucide-react'
import cn from '@/utils/cn'

export default function Logo({ compact = false, inverted = false }) {
  return (
    <div className="flex items-center gap-2.5">
      <div className="bg-brand-gradient flex size-9 shrink-0 items-center justify-center rounded-xl text-white shadow-glow ring-1 ring-white/20">
        <PackageCheck className="size-5" />
      </div>
      <div className="leading-tight">
        <p className={cn('text-sm font-bold', inverted ? 'text-white' : 'text-slate-900')}>Presensi Logistik</p>
        {!compact && <p className={cn('text-xs', inverted ? 'text-white/55' : 'text-slate-500')}>Telkom University Surabaya</p>}
      </div>
    </div>
  )
}
