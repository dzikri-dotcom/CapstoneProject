import { Clock } from 'lucide-react'
import Input from '@/components/ui/Input'
import { shiftApi } from '@/services/shiftPostService'
import { formatDuration } from '@/utils/format'
import ManagedList from './ManagedList'

const EMPTY = { name: '', start_time: '', end_time: '', is_active: true }

/** Kelola shift: nama dan jam mulai–selesai yang dipakai untuk check-in/check-out. */
export default function ShiftManager({ shifts, onChange }) {
  const sorted = [...shifts].sort((a, b) => a.start_time.localeCompare(b.start_time))

  return (
    <ManagedList
      title="Shift"
      description="Jam kerja yang dipakai untuk check-in dan check-out. Gaji tetap dihitung per jam hadir."
      icon={Clock}
      noun="shift"
      items={sorted}
      onChange={onChange}
      api={shiftApi}
      emptyForm={EMPTY}
      toForm={({ name, start_time, end_time, is_active }) => ({ name, start_time, end_time, is_active })}
      toPayload={(form) => form}
      renderSummary={(shift) => `${shift.start_time}–${shift.end_time} · ${formatDuration(shift.duration_minutes)}`}
      footnote="Jam antar-shift tidak boleh beririsan. Shift yang jatuh di luar jam operasional suatu hari akan dipotong atau dilewati."
      renderFields={({ form, set, errors }) => (
        <>
          <Input
            label="Nama shift"
            placeholder="Misal: Shift 1"
            value={form.name}
            onChange={(event) => set('name')(event.target.value)}
            error={errors.name}
            required
            autoFocus
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Jam mulai"
              type="time"
              value={form.start_time}
              onChange={(event) => set('start_time')(event.target.value)}
              error={errors.start_time}
              required
            />
            <Input
              label="Jam selesai"
              type="time"
              value={form.end_time}
              onChange={(event) => set('end_time')(event.target.value)}
              error={errors.end_time}
              required
            />
          </div>
        </>
      )}
    />
  )
}
