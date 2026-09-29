import { ChevronDown, History, Trash2 } from 'lucide-react'
import { useState } from 'react'
import Badge from '@/components/ui/Badge'
import Card from '@/components/ui/Card'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import EmptyState from '@/components/ui/EmptyState'
import Spinner from '@/components/ui/Spinner'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { deleteRateVersion } from '@/services/hourlyRateService'
import cn from '@/utils/cn'
import { formatDate, formatRupiah } from '@/utils/format'

const STATUS = {
  current: { label: 'Berlaku sekarang', color: 'green' },
  upcoming: { label: 'Akan berlaku', color: 'blue' },
  past: { label: 'Riwayat', color: 'gray' },
}

export default function RateHistory({ versions, onDeleted }) {
  const toast = useToast()
  const [expanded, setExpanded] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  async function handleDelete() {
    setBusy(true)
    try {
      await deleteRateVersion(deleting.effective_date)
      toast.success(`Versi tarif ${formatDate(deleting.effective_date)} dihapus.`)
      setDeleting(null)
      onDeleted?.()
    } catch (error) {
      toast.error(getErrorMessage(error))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card title="Riwayat tarif" description="Setiap versi berlaku mulai tanggalnya sampai versi berikutnya." bodyClassName="p-0">
      {!versions ? (
        <div className="flex justify-center py-10">
          <Spinner className="size-5 text-brand-600" />
        </div>
      ) : versions.length === 0 ? (
        <EmptyState icon={History} title="Belum ada tarif" />
      ) : (
        <ul className="divide-y divide-slate-100">
          {versions.map((version) => {
            const status = STATUS[version.status]
            const open = expanded === version.effective_date
            const range =
              version.min_rate === version.max_rate
                ? formatRupiah(version.min_rate)
                : `${formatRupiah(version.min_rate)} – ${formatRupiah(version.max_rate)}`

            return (
              <li key={version.effective_date}>
                <div className="flex items-center gap-2 px-5 py-3.5">
                  <button
                    type="button"
                    onClick={() => setExpanded(open ? null : version.effective_date)}
                    className="flex min-w-0 flex-1 items-start gap-3 text-left"
                    aria-expanded={open}
                  >
                    <ChevronDown className={cn('mt-0.5 size-4 shrink-0 text-slate-400 transition', open && 'rotate-180')} />
                    <span className="min-w-0">
                      <span className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium text-slate-800">{formatDate(version.effective_date)}</span>
                        <Badge color={status.color}>{status.label}</Badge>
                      </span>
                      <span className="block text-xs text-slate-500 tabular-nums">
                        {range} / jam{version.created_by && ` · oleh ${version.created_by}`}
                      </span>
                    </span>
                  </button>
                  {version.status === 'upcoming' && (
                    <button
                      type="button"
                      onClick={() => setDeleting(version)}
                      className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                      aria-label={`Hapus versi tarif ${formatDate(version.effective_date)}`}
                    >
                      <Trash2 className="size-4" />
                    </button>
                  )}
                </div>

                {open && (
                  <div className="px-5 pb-4">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-slate-50 p-3 text-xs tabular-nums">
                      {version.rates.map((rate) => (
                        <div key={rate.start_time} className="flex justify-between gap-2">
                          <dt className="text-slate-500">
                            {rate.start_time}–{rate.end_time}
                          </dt>
                          <dd className="font-medium text-slate-700">{formatRupiah(rate.rate)}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                )}
              </li>
            )
          })}
        </ul>
      )}

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={handleDelete}
        loading={busy}
        title="Hapus versi tarif?"
        confirmLabel="Ya, hapus"
        message={deleting && <>Versi tarif yang akan berlaku {formatDate(deleting.effective_date)} dibatalkan.</>}
      />
    </Card>
  )
}
