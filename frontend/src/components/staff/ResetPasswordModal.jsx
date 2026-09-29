import { KeyRound, Shuffle } from 'lucide-react'
import { useId, useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { resetStaffPassword } from '@/services/staffService'
import { generatePassword } from '@/utils/format'

export default function ResetPasswordModal({ staff, onClose, onDone }) {
  const toast = useToast()
  const formId = useId()
  const [password, setPassword] = useState(() => generatePassword())
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await resetStaffPassword(staff.id, password)
      toast.success(`Password ${staff.name} berhasil direset.`)
      onDone?.()
      onClose()
    } catch (err) {
      setError(getValidationErrors(err).password ?? getErrorMessage(err))
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      onClose={saving ? undefined : onClose}
      size="sm"
      title="Reset password"
      description={staff.name}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Batal
          </Button>
          <Button type="submit" form={formId} icon={KeyRound} loading={saving}>
            Reset password
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={handleSubmit} className="space-y-4">
        <Alert variant="warning">
          Semua sesi login staff ini akan dikeluarkan. Sampaikan password baru kepada staff yang bersangkutan.
        </Alert>
        <Input
          label="Password baru"
          autoComplete="off"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={error}
          hint="Minimal 8 karakter."
          required
          addon={
            <Button variant="secondary" icon={Shuffle} onClick={() => setPassword(generatePassword())}>
              Acak
            </Button>
          }
        />
      </form>
    </Modal>
  )
}
