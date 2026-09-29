import { CalendarPlus } from 'lucide-react'
import { useId, useState } from 'react'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { createPeriod } from '@/services/scheduleService'
import { addDaysISO, todayISO } from '@/utils/time'

/** Senin terdekat (hari ini jika hari ini Senin). */
function upcomingMonday() {
  const day = new Date().getDay() // 0 = Minggu, 1 = Senin
  return addDaysISO(todayISO(), day === 1 ? 0 : (8 - day) % 7)
}

export default function CreatePeriodModal({ onClose, onCreated }) {
  const toast = useToast()
  const formId = useId()
  const [form, setForm] = useState(() => {
    const start = upcomingMonday()
    return { start_date: start, end_date: addDaysISO(start, 6) }
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      onCreated(await createPeriod(form))
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
      size="sm"
      title="Buat periode jadwal"
      description="Biasanya 1 minggu (Senin–Minggu). Slot hanya dibuat pada hari operasional yang aktif."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Batal
          </Button>
          <Button type="submit" form={formId} icon={CalendarPlus} loading={saving}>
            Buat periode
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2" noValidate>
        <Input
          label="Tanggal mulai"
          type="date"
          value={form.start_date}
          onChange={(event) =>
            setForm({ start_date: event.target.value, end_date: event.target.value ? addDaysISO(event.target.value, 6) : '' })
          }
          error={errors.start_date}
          required
        />
        <Input
          label="Tanggal selesai"
          type="date"
          min={form.start_date}
          value={form.end_date}
          onChange={(event) => setForm((current) => ({ ...current, end_date: event.target.value }))}
          error={errors.end_date}
          required
        />
      </form>
    </Modal>
  )
}
