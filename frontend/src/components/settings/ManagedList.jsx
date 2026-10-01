import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import EmptyState from '@/components/ui/EmptyState'
import Modal from '@/components/ui/Modal'
import Toggle from '@/components/ui/Toggle'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'

/**
 * Kartu daftar yang bisa ditambah / diubah / dihapus / diaktifkan lewat dialog.
 * Dipakai untuk shift dan pos jaga di halaman Pengaturan.
 *
 * - api: { create, update, remove } dari shiftPostService
 * - emptyForm: nilai awal form tambah
 * - toPayload(form): bentuk data yang dikirim ke server
 * - renderFields({ form, set, errors }): isi form
 * - renderSummary(item): ringkasan satu baris (di bawah nama)
 */
export default function ManagedList({
  title,
  description,
  icon,
  noun,
  items,
  onChange,
  api,
  emptyForm,
  toForm,
  toPayload,
  renderFields,
  renderSummary,
  footnote,
}) {
  const toast = useToast()
  const [editing, setEditing] = useState(null) // { item: null } = tambah, { item } = ubah
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [removing, setRemoving] = useState(false)

  const set = (field) => (value) => setForm((current) => ({ ...current, [field]: value }))

  function open(item) {
    setForm(item ? toForm(item) : emptyForm)
    setErrors({})
    setEditing({ item })
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const payload = toPayload(form)
      const response = editing.item ? await api.update(editing.item.id, payload) : await api.create(payload)
      onChange(
        editing.item ? items.map((item) => (item.id === response.data.id ? response.data : item)) : [...items, response.data],
      )
      toast.success(response.message)
      setEditing(null)
    } catch (error) {
      const fieldErrors = getValidationErrors(error)
      if (Object.keys(fieldErrors).length) setErrors(fieldErrors)
      else toast.error(getErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete() {
    setRemoving(true)
    try {
      const response = await api.remove(deleting.id)
      onChange(items.filter((item) => item.id !== deleting.id))
      toast.success(response.message)
      setDeleting(null)
    } catch (error) {
      toast.error(getErrorMessage(error))
    } finally {
      setRemoving(false)
    }
  }

  return (
    <Card
      title={title}
      description={description}
      bodyClassName="p-0"
      actions={
        <Button size="sm" variant="secondary" icon={Plus} onClick={() => open(null)}>
          Tambah {noun}
        </Button>
      }
    >
      {items.length === 0 ? (
        <EmptyState icon={icon} title={`Belum ada ${noun}`} description={`Tambahkan ${noun} agar jadwal bisa di-generate.`} />
      ) : (
        <ul className="divide-y divide-slate-100">
          {items.map((item) => (
            <li key={item.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
                  {item.name}
                  {!item.is_active && <Badge color="gray">Nonaktif</Badge>}
                </p>
                <p className="text-[13px] text-slate-500 tabular-nums">{renderSummary(item)}</p>
              </div>
              <button
                type="button"
                onClick={() => open(item)}
                className="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                aria-label={`Ubah ${item.name}`}
                title="Ubah"
              >
                <Pencil className="size-4" />
              </button>
              <button
                type="button"
                onClick={() => setDeleting(item)}
                className="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                aria-label={`Hapus ${item.name}`}
                title="Hapus"
              >
                <Trash2 className="size-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
      {footnote && <p className="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">{footnote}</p>}

      <Modal
        open={Boolean(editing)}
        onClose={saving ? undefined : () => setEditing(null)}
        title={editing?.item ? `Ubah ${editing.item.name}` : `Tambah ${noun}`}
        size="sm"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {renderFields({ form, set, errors })}
          <div className="rounded-lg border border-slate-200 p-3">
            <Toggle
              checked={form.is_active}
              onChange={set('is_active')}
              label="Aktif"
              description={`Hanya ${noun} aktif yang masuk ke jadwal yang di-generate.`}
            />
          </div>
          <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={() => setEditing(null)} disabled={saving}>
              Batal
            </Button>
            <Button type="submit" loading={saving}>
              Simpan
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={handleDelete}
        loading={removing}
        title={`Hapus ${deleting?.name}?`}
        confirmLabel="Ya, hapus"
        message="Jadwal yang sudah dibuat tidak berubah. Jadwal yang di-generate setelah ini tidak lagi memakainya."
      />
    </Card>
  )
}
