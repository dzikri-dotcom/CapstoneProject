import { BookOpen, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import EmptyState from '@/components/ui/EmptyState'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { DAYS } from '@/utils/constants'
import ClassScheduleFormModal from './ClassScheduleFormModal'

/**
 * Daftar jadwal kuliah per hari + tambah/edit/hapus.
 * Dipakai di halaman staff (jadwal sendiri) dan detail staff di admin.
 */
export default function ClassScheduleManager({ api, onChanged }) {
  const toast = useToast()
  const { data: schedules, loading, error, reload } = useFetch(() => api.list(), [api.key])
  const [form, setForm] = useState(null) // { schedule, day }
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  function handleChanged() {
    reload()
    onChanged?.()
  }

  async function handleDelete() {
    setBusy(true)
    try {
      await api.remove(deleting.id)
      toast.success('Jadwal kuliah dihapus.')
      setDeleting(null)
      handleChanged()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  if (!schedules && loading) {
    return (
      <div className="flex items-center justify-center gap-3 py-10 text-sm text-slate-500">
        <Spinner className="size-5 text-brand-600" /> Memuat jadwal kuliah…
      </div>
    )
  }

  if (error && !schedules) {
    return (
      <Alert variant="error" title="Gagal memuat jadwal kuliah">
        {getErrorMessage(error)}
      </Alert>
    )
  }

  // Senin–Jumat selalu tampil; Sabtu/Minggu hanya jika ada kuliah.
  const days = DAYS.filter((day) => day.value <= 5 || schedules.some((item) => item.day_of_week === day.value))

  return (
    <div>
      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-sm text-slate-500">
          {schedules.length ? `${schedules.length} kelas per minggu` : 'Belum ada jadwal kuliah.'}
        </p>
        <Button size="sm" icon={Plus} onClick={() => setForm({ schedule: null, day: 1 })}>
          Tambah jadwal
        </Button>
      </div>

      {schedules.length === 0 ? (
        <EmptyState
          icon={BookOpen}
          title="Jadwal kuliah masih kosong"
          description="Tanpa jadwal kuliah, sistem menganggap seluruh jam operasional kosong untuk shift."
        />
      ) : (
        <ul className="divide-y divide-slate-100">
          {days.map((day) => {
            const items = schedules.filter((item) => item.day_of_week === day.value)
            return (
              <li key={day.value} className="flex gap-3 py-3 first:pt-0 last:pb-0">
                <div className="w-16 shrink-0 pt-2">
                  <p className="text-sm font-medium text-slate-700">{day.label}</p>
                </div>
                <div className="min-w-0 flex-1 space-y-1.5">
                  {items.map((item) => (
                    <div
                      key={item.id}
                      className="group flex items-center gap-2 rounded-lg border border-slate-200 bg-white py-1.5 pr-1 pl-3"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-slate-800 tabular-nums">
                          {item.start_time}–{item.end_time}
                        </p>
                        {item.course_name && <p className="truncate text-xs text-slate-500">{item.course_name}</p>}
                      </div>
                      <button
                        type="button"
                        onClick={() => setForm({ schedule: item })}
                        className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label={`Edit jadwal ${day.label} ${item.start_time}`}
                      >
                        <Pencil className="size-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => setDeleting(item)}
                        className="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                        aria-label={`Hapus jadwal ${day.label} ${item.start_time}`}
                      >
                        <Trash2 className="size-4" />
                      </button>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() => setForm({ schedule: null, day: day.value })}
                    className="flex items-center gap-1 rounded-md px-1 py-1 text-xs font-medium text-slate-400 hover:text-brand-700"
                  >
                    <Plus className="size-3.5" />
                    {items.length ? 'Tambah' : 'Tidak ada kuliah · tambah'}
                  </button>
                </div>
              </li>
            )
          })}
        </ul>
      )}

      {form && (
        <ClassScheduleFormModal
          api={api}
          schedule={form.schedule}
          defaultDay={form.day}
          onClose={() => setForm(null)}
          onSaved={(isEdit) => {
            toast.success(isEdit ? 'Jadwal kuliah diperbarui.' : 'Jadwal kuliah ditambahkan.')
            setForm(null)
            handleChanged()
          }}
        />
      )}

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={handleDelete}
        loading={busy}
        title="Hapus jadwal kuliah?"
        confirmLabel="Ya, hapus"
        message={
          deleting && (
            <>
              Jadwal <strong>{DAYS.find((day) => day.value === deleting.day_of_week)?.label}</strong>{' '}
              {deleting.start_time}–{deleting.end_time}
              {deleting.course_name && ` (${deleting.course_name})`} akan dihapus.
            </>
          )
        }
      />
    </div>
  )
}
