import { Save, Shuffle } from 'lucide-react'
import { useId, useState } from 'react'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import Toggle from '@/components/ui/Toggle'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { createStaff, updateStaff } from '@/services/staffService'
import { STUDY_PROGRAMS } from '@/utils/constants'
import { generatePassword } from '@/utils/format'

function initialForm(staff) {
  if (staff) {
    return {
      name: staff.name,
      email: staff.email,
      nim: staff.nim,
      phone: staff.phone ?? '',
      study_program: staff.study_program,
      password: '',
      is_active: staff.is_active,
    }
  }
  return { name: '', email: '', nim: '', phone: '', study_program: '', password: generatePassword(), is_active: true }
}

/**
 * Form tambah/edit staff. Dirender hanya saat dibuka, sehingga isi form selalu segar.
 */
export default function StaffFormModal({ staff, onClose, onSaved }) {
  const isEdit = Boolean(staff)
  const toast = useToast()
  const formId = useId()
  const programListId = useId()
  const [form, setForm] = useState(() => initialForm(staff))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})

    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      nim: form.nim.trim(),
      phone: form.phone.trim() || null,
      study_program: form.study_program.trim(),
      is_active: form.is_active,
    }
    if (!isEdit) payload.password = form.password

    try {
      const saved = isEdit ? await updateStaff(staff.id, payload) : await createStaff(payload)
      onSaved(saved)
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
      size="lg"
      title={isEdit ? 'Edit data staff' : 'Tambah staff baru'}
      description={isEdit ? staff.name : 'Akun login staff dibuat sekaligus dengan data ini.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Batal
          </Button>
          <Button type="submit" form={formId} icon={Save} loading={saving}>
            {isEdit ? 'Simpan perubahan' : 'Tambah staff'}
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2" noValidate>
        <Input
          label="Nama lengkap"
          className="sm:col-span-2"
          value={form.name}
          onChange={update('name')}
          error={errors.name}
          required
          autoFocus
        />
        <Input label="NIM" value={form.nim} onChange={update('nim')} error={errors.nim} required />
        <Input
          label="No. HP"
          inputMode="tel"
          placeholder="08xxxxxxxxxx"
          value={form.phone}
          onChange={update('phone')}
          error={errors.phone}
        />
        <Input
          label="Email (untuk login)"
          type="email"
          className="sm:col-span-2"
          value={form.email}
          onChange={update('email')}
          error={errors.email}
          required
        />
        <Input
          label="Program studi"
          className="sm:col-span-2"
          list={programListId}
          placeholder="Pilih atau ketik program studi"
          value={form.study_program}
          onChange={update('study_program')}
          error={errors.study_program}
          required
        />
        <datalist id={programListId}>
          {STUDY_PROGRAMS.map((program) => (
            <option key={program} value={program} />
          ))}
        </datalist>

        {!isEdit && (
          <Input
            label="Password awal"
            className="sm:col-span-2"
            autoComplete="off"
            value={form.password}
            onChange={update('password')}
            error={errors.password}
            hint="Sampaikan password ini ke staff. Staff bisa menggantinya sendiri setelah login."
            required
            addon={
              <Button
                variant="secondary"
                icon={Shuffle}
                onClick={() => setForm((current) => ({ ...current, password: generatePassword() }))}
              >
                Acak
              </Button>
            }
          />
        )}

        <div className="rounded-xl border border-slate-200 p-4 sm:col-span-2">
          <Toggle
            checked={form.is_active}
            onChange={(checked) => setForm((current) => ({ ...current, is_active: checked }))}
            label="Staff aktif"
            description="Staff nonaktif tidak bisa login dan tidak dimasukkan ke antrian jadwal shift."
          />
        </div>
      </form>
    </Modal>
  )
}
