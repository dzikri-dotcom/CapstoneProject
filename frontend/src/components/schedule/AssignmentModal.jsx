import { ArrowLeftRight, MoveRight, Trash2, UserRoundPen } from 'lucide-react'
import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import Modal from '@/components/ui/Modal'
import Select from '@/components/ui/Select'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { removeAssignment, swapAssignments, updateAssignment } from '@/services/scheduleService'
import { shortName } from '@/utils/format'
import { notifyResult, slotLabel } from './scheduleUtils'

function Section({ icon: Icon, title, description, children }) {
  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <Icon className="size-4 text-slate-400" />
        <p className="text-sm font-medium text-slate-800">{title}</p>
      </div>
      {description && <p className="text-xs text-slate-500">{description}</p>}
      {children}
    </div>
  )
}

/**
 * Kelola satu penugasan: ganti staff, pindah slot, tukar dengan staff lain, atau hapus.
 */
export default function AssignmentModal({ assignment, slot, slots, warnings = [], onClose, onReplace, onChanged }) {
  const toast = useToast()
  const [targetSlot, setTargetSlot] = useState('')
  const [swapWith, setSwapWith] = useState('')
  const [busy, setBusy] = useState(null)
  const locked = assignment.has_attendance

  const moveOptions = slots
    .filter((other) => other.id !== slot.id && !other.assignments.some((a) => a.staff_profile_id === assignment.staff_profile_id))
    .map((other) => ({
      value: String(other.id),
      label: `${slotLabel(other)} (${other.assignments.length}/${other.required_staff})`,
    }))

  const swapOptions = slots
    .filter((other) => other.id !== slot.id)
    .flatMap((other) =>
      other.assignments
        .filter((a) => a.staff_profile_id !== assignment.staff_profile_id)
        .map((a) => ({ value: String(a.id), label: `${slotLabel(other)} · ${shortName(a.name)}` })),
    )

  async function run(kind, action) {
    setBusy(kind)
    try {
      notifyResult(toast, await action())
      onChanged()
    } catch (error) {
      toast.error(getErrorMessage(error))
      setBusy(null)
    }
  }

  return (
    <Modal open onClose={busy ? undefined : onClose} title={assignment.name} description={slotLabel(slot)}>
      <div className="space-y-5">
        <div className="flex flex-wrap gap-2">
          <Badge color={assignment.source === 'manual' ? 'blue' : 'gray'}>
            {assignment.source === 'manual' ? 'Diubah manual' : 'Hasil algoritma'}
          </Badge>
        </div>

        {warnings.length > 0 && (
          <Alert variant="warning" title="Peringatan">
            <ul className="list-disc space-y-0.5 pl-4">
              {warnings.map((warning) => (
                <li key={warning.type}>{warning.message}</li>
              ))}
            </ul>
          </Alert>
        )}

        {locked ? (
          <Alert variant="info">Penugasan ini sudah memiliki data presensi sehingga tidak bisa diubah.</Alert>
        ) : (
          <>
            <Section icon={UserRoundPen} title="Ganti staff" description="Ganti dengan staff lain di slot yang sama.">
              <Button variant="secondary" size="sm" onClick={onReplace} disabled={Boolean(busy)}>
                Pilih staff pengganti
              </Button>
            </Section>

            <Section icon={MoveRight} title="Pindah ke slot lain">
              <div className="flex flex-col gap-2 sm:flex-row">
                <Select
                  className="flex-1"
                  aria-label="Slot tujuan"
                  value={targetSlot}
                  onChange={(event) => setTargetSlot(event.target.value)}
                  options={[{ value: '', label: 'Pilih slot tujuan…' }, ...moveOptions]}
                />
                <Button
                  variant="secondary"
                  disabled={!targetSlot || Boolean(busy)}
                  loading={busy === 'move'}
                  onClick={() => run('move', () => updateAssignment(assignment.id, { shift_slot_id: Number(targetSlot) }))}
                >
                  Pindahkan
                </Button>
              </div>
            </Section>

            <Section icon={ArrowLeftRight} title="Tukar dengan staff lain" description="Kedua staff bertukar slot.">
              <div className="flex flex-col gap-2 sm:flex-row">
                <Select
                  className="flex-1"
                  aria-label="Tukar dengan"
                  value={swapWith}
                  onChange={(event) => setSwapWith(event.target.value)}
                  options={[{ value: '', label: 'Pilih staff di slot lain…' }, ...swapOptions]}
                />
                <Button
                  variant="secondary"
                  disabled={!swapWith || Boolean(busy)}
                  loading={busy === 'swap'}
                  onClick={() => run('swap', () => swapAssignments(assignment.id, Number(swapWith)))}
                >
                  Tukar
                </Button>
              </div>
            </Section>

            <div className="border-t border-slate-100 pt-4">
              <Button
                variant="ghost"
                icon={Trash2}
                className="text-red-600 hover:bg-red-50 hover:text-red-700"
                loading={busy === 'remove'}
                disabled={Boolean(busy)}
                onClick={() => run('remove', () => removeAssignment(assignment.id))}
              >
                Hapus dari slot ini
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
