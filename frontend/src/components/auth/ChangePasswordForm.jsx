import { KeyRound } from 'lucide-react'
import { useState } from 'react'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { updatePassword } from '@/services/authService'

const emptyForm = { current_password: '', password: '', password_confirmation: '' }

export default function ChangePasswordForm() {
  const toast = useToast()
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  async function handleSubmit(event) {
    event.preventDefault()

    if (form.password !== form.password_confirmation) {
      setErrors({ password_confirmation: 'Konfirmasi password baru tidak cocok.' })
      return
    }

    setSaving(true)
    setErrors({})
    try {
      await updatePassword(form)
      toast.success('Password berhasil diubah. Sesi di perangkat lain telah dikeluarkan.')
      setForm(emptyForm)
    } catch (error) {
      const fieldErrors = getValidationErrors(error)
      if (Object.keys(fieldErrors).length) setErrors(fieldErrors)
      else toast.error(getErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <Input
        label="Password saat ini"
        type="password"
        autoComplete="current-password"
        value={form.current_password}
        onChange={update('current_password')}
        error={errors.current_password}
        required
      />
      <Input
        label="Password baru"
        type="password"
        autoComplete="new-password"
        hint="Minimal 8 karakter."
        value={form.password}
        onChange={update('password')}
        error={errors.password}
        minLength={8}
        required
      />
      <Input
        label="Ulangi password baru"
        type="password"
        autoComplete="new-password"
        value={form.password_confirmation}
        onChange={update('password_confirmation')}
        error={errors.password_confirmation}
        required
      />
      <Button type="submit" icon={KeyRound} loading={saving}>
        Simpan password
      </Button>
    </form>
  )
}
