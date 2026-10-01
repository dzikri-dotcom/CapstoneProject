import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Avatar from '@/components/ui/Avatar'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getCandidates } from '@/services/scheduleService'
import { formatDuration } from '@/utils/format'

function StatusBadge({ candidate }) {
  if (candidate.is_assigned) return <Badge color="gray">Sudah di slot ini</Badge>
  if (candidate.busy_in) return <Badge color="gray">Bertugas di pos lain</Badge>
  if (candidate.warnings.length) return <Badge color="amber">Ada peringatan</Badge>
  return <Badge color="green">Tersedia</Badge>
}

/**
 * Pilih staff untuk sebuah slot. Staff dengan peringatan (kuliah / melebihi batas jam)
 * tetap bisa dipilih setelah konfirmasi — keputusan akhir di tangan admin.
 */
export default function CandidatePicker({ slot, title, description, onClose, onPick }) {
  const { data: candidates, loading, error } = useFetch(() => getCandidates(slot.id), [slot.id])
  const [pending, setPending] = useState(null)
  const [saving, setSaving] = useState(false)

  async function pick(candidate) {
    setSaving(true)
    try {
      await onPick(candidate)
    } finally {
      setSaving(false)
    }
  }

  function choose(candidate) {
    if (candidate.warnings.length) setPending(candidate)
    else pick(candidate)
  }

  return (
    <Modal open onClose={saving ? undefined : onClose} title={title} description={description}>
      {pending ? (
        <div className="space-y-4">
          <Alert variant="warning" title={`${pending.name} memiliki peringatan`}>
            <ul className="list-disc space-y-0.5 pl-4">
              {pending.warnings.map((warning) => (
                <li key={warning.type}>{warning.message}</li>
              ))}
            </ul>
          </Alert>
          <p className="text-sm text-slate-600">Tetap tugaskan staff ini di slot tersebut?</p>
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={() => setPending(null)} disabled={saving}>
              Pilih staff lain
            </Button>
            <Button onClick={() => pick(pending)} loading={saving}>
              Tetap tugaskan
            </Button>
          </div>
        </div>
      ) : !candidates && loading ? (
        <div className="flex justify-center py-10">
          <Spinner className="size-5 text-brand-600" />
        </div>
      ) : error ? (
        <Alert variant="error">{getErrorMessage(error)}</Alert>
      ) : (
        <ul className="-mx-2 divide-y divide-slate-100">
          {candidates.map((candidate) => (
            <li key={candidate.staff_profile_id}>
              <button
                type="button"
                disabled={candidate.is_assigned || Boolean(candidate.busy_in) || saving}
                onClick={() => choose(candidate)}
                className="flex w-full items-start gap-3 rounded-lg px-2 py-2.5 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-55"
              >
                <Avatar name={candidate.name} size="sm" />
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium text-slate-800">{candidate.name}</span>
                    <StatusBadge candidate={candidate} />
                  </span>
                  <span className="block text-xs text-slate-500">
                    Jam shift minggu ini: {formatDuration(candidate.week_minutes) || '0 jam'}
                    {candidate.max_minutes ? ` dari batas ${formatDuration(candidate.max_minutes)}` : ''}
                    {candidate.busy_in && ` · sedang di ${candidate.busy_in}`}
                  </span>
                  {candidate.warnings.map((warning) => (
                    <span key={warning.type} className="block text-xs text-amber-700">
                      ⚠ {warning.message}
                    </span>
                  ))}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  )
}
