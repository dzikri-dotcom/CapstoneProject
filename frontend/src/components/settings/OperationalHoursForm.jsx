import { Save } from 'lucide-react'
import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import Input from '@/components/ui/Input'
import Toggle from '@/components/ui/Toggle'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { updateOperationalHours } from '@/services/settingService'
import cn from '@/utils/cn'
import { buildDailySlots } from '@/utils/time'

export default function OperationalHoursForm({ initialDays, shiftDuration, onSaved }) {
  const toast = useToast()
  const [days, setDays] = useState(initialDays)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const updateDay = (index, patch) =>
    setDays((current) => current.map((day, i) => (i === index ? { ...day, ...patch } : day)))

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const saved = await updateOperationalHours(
        days.map(({ day_of_week, is_active, open_time, close_time }) => ({ day_of_week, is_active, open_time, close_time })),
      )
      setDays(saved)
      toast.success('Jam operasional berhasil disimpan.')
      onSaved?.(saved)
    } catch (error) {
      const fieldErrors = getValidationErrors(error)
      if (Object.keys(fieldErrors).length) setErrors(fieldErrors)
      else toast.error(getErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card title="Jam operasional" description="Hari dan jam ruang logistik buka. Slot shift dibentuk dari jam ini.">
      <form onSubmit={handleSubmit}>
        {errors.days && (
          <Alert variant="error" className="mb-4">
            {errors.days}
          </Alert>
        )}

        <div className="divide-y divide-slate-100">
          {days.map((day, index) => {
            const valid = day.open_time && day.close_time && day.open_time < day.close_time
            const slots = day.is_active && valid ? buildDailySlots(day.open_time, day.close_time, shiftDuration) : []
            const error = errors[`days.${index}.close_time`] ?? errors[`days.${index}.open_time`]

            return (
              <div key={day.day_of_week} className="flex flex-col gap-3 py-3 first:pt-0 sm:flex-row sm:items-center">
                <div className="sm:w-44">
                  <Toggle
                    checked={day.is_active}
                    onChange={(checked) => updateDay(index, { is_active: checked })}
                    label={day.day_label}
                    description={day.is_active ? 'Buka' : 'Libur'}
                  />
                </div>
                <div className={cn('grid grid-cols-2 gap-3 sm:w-72', !day.is_active && 'opacity-50')}>
                  <Input
                    type="time"
                    aria-label={`Jam buka ${day.day_label}`}
                    value={day.open_time}
                    onChange={(event) => updateDay(index, { open_time: event.target.value })}
                    disabled={!day.is_active}
                    error={error ? ' ' : undefined}
                  />
                  <Input
                    type="time"
                    aria-label={`Jam tutup ${day.day_label}`}
                    value={day.close_time}
                    onChange={(event) => updateDay(index, { close_time: event.target.value })}
                    disabled={!day.is_active}
                    error={error ? ' ' : undefined}
                  />
                </div>
                <p className={cn('text-xs sm:ml-auto sm:text-right', error ? 'text-red-600' : 'text-slate-500')}>
                  {error ?? (day.is_active ? `${slots.length} slot shift` : 'Tidak ada shift')}
                </p>
              </div>
            )
          })}
        </div>

        <div className="mt-5 flex justify-end">
          <Button type="submit" icon={Save} loading={saving}>
            Simpan jam operasional
          </Button>
        </div>
      </form>
    </Card>
  )
}
