import { Save } from 'lucide-react'
import { useState } from 'react'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import Input from '@/components/ui/Input'
import Select from '@/components/ui/Select'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { updateSettings } from '@/services/settingService'
import { formatDuration } from '@/utils/format'
import { buildDailySlots, roundMinutes, toMinutes } from '@/utils/time'

const DURATIONS = [60, 90, 120, 150, 180, 240]

const ROUNDING_UNITS = [
  { value: '1', label: 'Per menit (tanpa pembulatan)' },
  { value: '5', label: 'Per 5 menit' },
  { value: '15', label: 'Per 15 menit' },
  { value: '30', label: 'Per 30 menit' },
  { value: '60', label: 'Per 1 jam' },
]

const ROUNDING_MODES = [
  { value: 'down', label: 'Ke bawah (sisa menit tidak dibayar)' },
  { value: 'nearest', label: 'Ke terdekat' },
]

const NUMBER_FIELDS = [
  'staff_per_shift',
  'max_weekly_hours',
  'checkin_early_minutes',
  'late_tolerance_minutes',
  'checkout_grace_minutes',
  'qr_refresh_seconds',
]

function Section({ title, description, children, preview }) {
  return (
    <div className="grid gap-4 py-6 first:pt-0 last:pb-0 lg:grid-cols-3 lg:gap-8">
      <div>
        <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
        <p className="mt-1 text-sm text-slate-500">{description}</p>
      </div>
      <div className="space-y-4 lg:col-span-2">
        <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        {preview}
      </div>
    </div>
  )
}

function Preview({ title, children }) {
  return (
    <div className="rounded-xl bg-slate-50 p-4 text-sm">
      <p className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">{title}</p>
      {children}
    </div>
  )
}

export default function SystemSettingsForm({ initial, sampleDay, onSaved }) {
  const toast = useToast()
  const [form, setForm] = useState(() =>
    Object.fromEntries(Object.entries(initial).map(([key, value]) => [key, String(value)])),
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))
  const field = (name) => ({ value: form[name], onChange: update(name), error: errors[name], required: true })

  const duration = Number(form.shift_duration_minutes)
  const durationOptions = [...new Set([...DURATIONS, duration])]
    .sort((a, b) => a - b)
    .map((minutes) => ({ value: String(minutes), label: formatDuration(minutes) }))
  const slots = sampleDay && duration ? buildDailySlots(sampleDay.open_time, sampleDay.close_time, duration) : []
  const lastSlotShort =
    sampleDay && duration > 0 && (toMinutes(sampleDay.close_time) - toMinutes(sampleDay.open_time)) % duration !== 0

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})

    const payload = { ...form, shift_duration_minutes: duration, rounding_unit_minutes: Number(form.rounding_unit_minutes) }
    NUMBER_FIELDS.forEach((name) => {
      payload[name] = form[name] === '' ? null : Number(form[name])
    })

    try {
      const saved = await updateSettings(payload)
      toast.success('Pengaturan berhasil disimpan.')
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
    <Card title="Aturan sistem" description="Dipakai algoritma penjadwalan, presensi, dan penggajian.">
      <form onSubmit={handleSubmit} className="divide-y divide-slate-100">
        <Section
          title="Penjadwalan shift"
          description="Menentukan slot shift dan batas beban kerja setiap staff pada algoritma Round Robin."
          preview={
            sampleDay && (
              <Preview title={`Contoh slot hari ${sampleDay.day_label} (${sampleDay.open_time}–${sampleDay.close_time})`}>
                <div className="flex flex-wrap gap-1.5">
                  {slots.map((slot) => (
                    <span key={slot.start} className="rounded-md bg-white px-2 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200 tabular-nums">
                      {slot.start}–{slot.end}
                    </span>
                  ))}
                </div>
                <p className="mt-2 text-xs text-slate-500">
                  {slots.length} slot × {form.staff_per_shift || 0} staff per slot.
                  {lastSlotShort && ' Slot terakhir lebih pendek karena mengikuti jam tutup.'}
                </p>
              </Preview>
            )
          }
        >
          <Select label="Durasi 1 shift" options={durationOptions} {...field('shift_duration_minutes')} />
          <Input label="Jumlah staff per shift" type="number" min={1} max={10} {...field('staff_per_shift')} />
          <Input
            label="Batas jam kerja per staff per minggu"
            type="number"
            min={1}
            max={60}
            hint="Dalam jam. Staff yang mencapai batas akan dilewati algoritma."
            {...field('max_weekly_hours')}
          />
        </Section>

        <Section title="Presensi" description="Batas waktu check-in/check-out dan pergantian QR Code dinamis.">
          <Input
            label="Check-in dibuka sebelum shift"
            type="number"
            min={0}
            hint="Menit sebelum jam mulai shift."
            {...field('checkin_early_minutes')}
          />
          <Input
            label="Toleransi keterlambatan"
            type="number"
            min={0}
            hint="Menit. Lewat dari ini dianggap terlambat."
            {...field('late_tolerance_minutes')}
          />
          <Input
            label="Batas check-out setelah shift"
            type="number"
            min={0}
            hint="Menit setelah jam selesai shift."
            {...field('checkout_grace_minutes')}
          />
          <Input
            label="QR berganti setiap"
            type="number"
            min={30}
            max={60}
            hint="Detik (30–60). Mencegah titip absen."
            {...field('qr_refresh_seconds')}
          />
        </Section>

        <Section
          title="Penggajian"
          description="Pembulatan durasi hadir sebelum dikalikan tarif per jam."
          preview={
            <Preview title="Contoh pembulatan">
              <ul className="space-y-1 text-slate-700">
                {[100, 115, 47].map((minutes) => (
                  <li key={minutes} className="tabular-nums">
                    Hadir {formatDuration(minutes)} → dibayar{' '}
                    <span className="font-semibold">
                      {formatDuration(roundMinutes(minutes, Number(form.rounding_unit_minutes), form.rounding_mode))}
                    </span>
                  </li>
                ))}
              </ul>
            </Preview>
          }
        >
          <Select label="Satuan pembulatan" options={ROUNDING_UNITS} {...field('rounding_unit_minutes')} />
          <Select label="Cara pembulatan" options={ROUNDING_MODES} {...field('rounding_mode')} />
        </Section>

        <div className="flex justify-end pt-6">
          <Button type="submit" icon={Save} loading={saving}>
            Simpan pengaturan
          </Button>
        </div>
      </form>
    </Card>
  )
}
