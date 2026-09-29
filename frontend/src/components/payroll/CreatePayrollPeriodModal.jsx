import { CalendarPlus } from 'lucide-react'
import { useId, useState } from 'react'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { createPayrollPeriod } from '@/services/payrollService'

/** Tanggal 1 s.d. akhir bulan berjalan. */
function currentMonth() {
  const now = new Date()
  const pad = (value) => String(value).padStart(2, '0')
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
  const prefix = `${now.getFullYear()}-${pad(now.getMonth() + 1)}`
  return { start_date: `${prefix}-01`, end_date: `${prefix}-${pad(last)}` }
}

export default function CreatePayrollPeriodModal({ onClose, onCreated }) {
  const toast = useToast()
  const formId = useId()
  const [form, setForm] = useState(currentMonth)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      onCreated(await createPayrollPeriod(form))
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
      title="Buat periode penggajian"
      description="Biasanya satu bulan. Gaji dihitung dari presensi pada jadwal yang sudah dipublikasikan."
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
          onChange={(event) => setForm((current) => ({ ...current, start_date: event.target.value }))}
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
