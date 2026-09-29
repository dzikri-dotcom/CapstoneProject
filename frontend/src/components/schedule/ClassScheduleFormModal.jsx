import { Save } from 'lucide-react'
import { useId, useState } from 'react'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import Select from '@/components/ui/Select'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { DAYS } from '@/utils/constants'

/**
 * Form tambah/edit satu jadwal kuliah. `api` = adapter dari classScheduleService.
 */
export default function ClassScheduleFormModal({ api, schedule, defaultDay = 1, onClose, onSaved }) {
  const isEdit = Boolean(schedule)
  const toast = useToast()
  const formId = useId()
  const [form, setForm] = useState(() => ({
    day_of_week: String(schedule?.day_of_week ?? defaultDay),
    start_time: schedule?.start_time ?? '',
    end_time: schedule?.end_time ?? '',
    course_name: schedule?.course_name ?? '',
  }))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})

    const payload = {
      day_of_week: Number(form.day_of_week),
      start_time: form.start_time,
      end_time: form.end_time,
      course_name: form.course_name.trim() || null,
    }

    try {
      if (isEdit) await api.update(schedule.id, payload)
      else await api.create(payload)
      onSaved(isEdit)
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
      title={isEdit ? 'Edit jadwal kuliah' : 'Tambah jadwal kuliah'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Batal
          </Button>
          <Button type="submit" form={formId} icon={Save} loading={saving}>
            Simpan
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit} className="grid grid-cols-2 gap-4" noValidate>
        <Select
          label="Hari"
          className="col-span-2"
          value={form.day_of_week}
          onChange={update('day_of_week')}
          error={errors.day_of_week}
          options={DAYS}
          required
        />
        <Input
          label="Jam mulai"
          type="time"
          step={300}
          value={form.start_time}
          onChange={update('start_time')}
          error={errors.start_time}
          required
        />
        <Input
          label="Jam selesai"
          type="time"
          step={300}
          value={form.end_time}
          onChange={update('end_time')}
          error={errors.end_time}
          required
        />
        <Input
          label="Nama mata kuliah"
          className="col-span-2"
          placeholder="Opsional, misal: Struktur Data"
          value={form.course_name}
          onChange={update('course_name')}
          error={errors.course_name}
        />
      </form>
    </Modal>
  )
}
