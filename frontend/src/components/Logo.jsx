import BrandMark from '@/components/BrandMark'
import cn from '@/utils/cn'

/**
 * Logo Logistik Asset Management — Telkom University Surabaya.
 * compact: "LOGISTIK / Asset Management" (sidebar & header HP); penuh: nama lengkap + kampus.
 */
export default function Logo({ compact = false, inverted = false, iconOnly = false }) {
  return (
    <div className="flex min-w-0 items-center gap-2.5">
      <BrandMark inverted={inverted} className="h-8 w-[30px] shrink-0" title={iconOnly ? 'Logistik Asset Management' : undefined} />
      {!iconOnly && (
        <div className="min-w-0 leading-tight">
          <p className={cn('truncate text-[13px] font-bold tracking-[0.06em] uppercase', inverted ? 'text-white' : 'text-slate-900')}>
            {compact ? 'Logistik' : 'Logistik Asset Management'}
          </p>
          <p className={cn('truncate text-xs', inverted ? 'text-white/55' : 'text-slate-500')}>
            {compact ? 'Asset Management' : 'Telkom University Surabaya'}
          </p>
        </div>
      )}
    </div>
  )
}
