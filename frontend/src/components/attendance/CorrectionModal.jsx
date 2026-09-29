import { Save } from 'lucide-react'
import { useId, useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import Select from '@/components/ui/Select'
import Textarea from '@/components/ui/Textarea'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { correctAttendance } from '@/services/attendanceService'
import { formatShortDate } from '@/utils/format'

const STATUS_OPTIONS = [
  { value: 'on_time', label: 'Tepat waktu' },
  { value: 'late', label: 'Terlambat' },
  { value: 'absent', label: 'Tidak hadir' },
]

/**
 * Koreksi presensi manual oleh admin (lupa scan, HP mati, izin terlambat, dll.) dengan catatan alasan.
 */
export default function CorrectionModal({ item, onClose, onSaved }) {
  const toast = useToast()
  const formId = useId()
  const [form, setForm] = useState(() => ({
    status: item.attendance?.status ?? (item.state === 'missed' ? 'absent' : 'on_time'),
    check_in_time: item.attendance?.check_in_time ?? item.start_time,
    check_out_time: item.attendance?.check_out_time ?? item.end_time,
    correction_note: '',
  }))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const absent = form.status === 'absent'

  function changeCheckIn(value) {
    // Sarankan status otomatis dari jam check-in (admin tetap bisa mengubahnya).
    setForm((current) => ({
      ...current,
      check_in_time: value,
      status: current.status === 'absent' || !value ? current.status : value > item.late_after ? 'late' : 'on_time',
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const result = await correctAttendance(item.assignment_id, {
        status: form.status,
        check_in_time: absent ? null : form.check_in_time || null,
        check_out_time: absent ? null : form.check_out_time || null,
        correction_note: form.correction_note.trim(),
      })
      toast.success(result.message)
      onSaved()
    } catch (error) {
      const fieldErrors = getValidationErrors(error)
      if (Object.keys(fieldErrors).length) setErrors(fieldErrors)
      else toast.error(getErrorMessage(error))
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      onClose={saving ? undefined : onClose}
      title={`Koreksi presensi ${item.staff?.name ?? ''}`}
      description={`${formatShortDate(item.date)} · shift ${item.start_time}–${item.end_time} · terlambat jika check-in setelah ${item.late_after}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Batal
          </Button>
          <Button type="submit" form={formId} icon={Save} loading={saving}>
            Simpan koreksi
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2" noValidate>
        {errors.attendance && (
          <Alert variant="error" className="sm:col-span-2">
            {errors.attendance}
          </Alert>
        )}
        <Select
          label="Status presensi"
          className="sm:col-span-2"
          options={STATUS_OPTIONS}
          value={form.status}
          onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}
          error={errors.status}
          required
        />
        <Input
          label="Jam check-in"
          type="time"
          value={absent ? '' : form.check_in_time}
          onChange={(event) => changeCheckIn(event.target.value)}
          error={errors.check_in_time}
          disabled={absent}
          required={!absent}
        />
        <Input
          label="Jam check-out"
          type="time"
          value={absent ? '' : form.check_out_time}
          onChange={(event) => setForm((current) => ({ ...current, check_out_time: event.target.value }))}
          error={errors.check_out_time}
          disabled={absent}
          hint={absent ? undefined : 'Kosongkan jika staff belum check-out.'}
        />
        <Textarea
          label="Catatan alasan koreksi"
          className="sm:col-span-2"
          placeholder="Contoh: HP staff mati, kehadiran dikonfirmasi rekan shift."
          value={form.correction_note}
          onChange={(event) => setForm((current) => ({ ...current, correction_note: event.target.value }))}
          error={errors.correction_note}
          maxLength={500}
          required
        />
      </form>
    </Modal>
  )
}
