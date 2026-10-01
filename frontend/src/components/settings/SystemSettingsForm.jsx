import { Save } from 'lucide-react'
import { useState } from 'react'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import Input from '@/components/ui/Input'
import Select from '@/components/ui/Select'
import Toggle from '@/components/ui/Toggle'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { updateSettings } from '@/services/settingService'
import { formatDuration } from '@/utils/format'
import { fromMinutes, roundMinutes, toMinutes } from '@/utils/time'

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
  'max_weekly_hours',
  'checkin_early_minutes',
  'late_tolerance_minutes',
  'max_late_minutes',
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

export default function SystemSettingsForm({ initial, shifts, posts, onSaved }) {
  const toast = useToast()
  const [form, setForm] = useState(() =>
    // Angka & pilihan disimpan sebagai teks (isi input); saklar tetap boolean.
    Object.fromEntries(Object.entries(initial).map(([key, value]) => [key, typeof value === 'boolean' ? value : String(value)])),
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))
  const field = (name) => ({ value: form[name], onChange: update(name), error: errors[name], required: true })

  const activeShifts = shifts.filter((shift) => shift.is_active)
  const activePosts = posts.filter((post) => post.is_active)
  const staffPerShift = activePosts.reduce((sum, post) => sum + post.capacity, 0)

  // Contoh jendela presensi untuk shift aktif pertama, mengikuti angka yang sedang diketik.
  const sample = activeShifts[0]
  const sampleStart = sample ? toMinutes(sample.start_time) : 0
  const sampleEnd = sample ? toMinutes(sample.end_time) : 0
  const at = (minutes) => fromMinutes(Math.max(0, Math.min(minutes, 23 * 60 + 59)))
  const deduct = Boolean(form.salary_deduction_enabled)
  const checkInCloses = Math.min(sampleStart + Number(form.max_late_minutes || 0), sampleEnd)

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})

    const payload = { ...form, rounding_unit_minutes: Number(form.rounding_unit_minutes) }
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
          description="Batas beban kerja setiap staff pada algoritma Round Robin. Jam shift dan pos jaga diatur di kartu Shift dan Pos jaga."
          preview={
            <Preview title="Kebutuhan per hari operasional">
              <p className="text-slate-700">
                {activeShifts.length} shift × {activePosts.length} pos ={' '}
                <span className="font-semibold">{activeShifts.length * staffPerShift} penugasan</span> per hari ({staffPerShift} staff per
                shift).
              </p>
            </Preview>
          }
        >
          <Input
            label="Batas jam kerja per staff per minggu"
            type="number"
            min={1}
            max={60}
            hint="Dalam jam. Staff yang mencapai batas akan dilewati algoritma."
            {...field('max_weekly_hours')}
          />
        </Section>

        <Section
          title="Presensi"
          description="Batas waktu check-in/check-out terhadap jam shift, dan pergantian QR Code dinamis."
          preview={
            sample && (
              <Preview title={`Contoh untuk ${sample.name} (${sample.start_time}–${sample.end_time})`}>
                <ul className="space-y-1 text-slate-700 tabular-nums">
                  <li>
                    Check-in dibuka <span className="font-semibold">{at(sampleStart - Number(form.checkin_early_minutes || 0))}</span>
                  </li>
                  <li>
                    Tepat waktu sampai <span className="font-semibold">{at(sampleStart + Number(form.late_tolerance_minutes || 0))}</span>,
                    setelah itu terlambat
                  </li>
                  <li>
                    Check-in ditutup <span className="font-semibold">{at(checkInCloses)}</span> — lewat dari ini dianggap tidak hadir
                  </li>
                  <li>
                    Check-out paling lambat <span className="font-semibold">{at(sampleEnd + Number(form.checkout_grace_minutes || 0))}</span>
                  </li>
                </ul>
              </Preview>
            )
          }
        >
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
            label="Maksimal keterlambatan"
            type="number"
            min={1}
            max={480}
            hint="Menit. Lewat dari ini tidak bisa check-in dan dianggap tidak hadir."
            {...field('max_late_minutes')}
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
          description="Gaji = jam yang dibayar × tarif per jam. Tentukan apakah terlambat atau pulang lebih cepat mengurangi gaji."
          preview={
            <Preview title={deduct ? 'Contoh pembulatan' : `Contoh${sample ? ` untuk ${sample.name}` : ''}`}>
              {deduct ? (
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
              ) : (
                <ul className="space-y-1 text-slate-700">
                  <li>
                    Hadir (tepat waktu, terlambat, atau pulang lebih cepat) → dibayar penuh{' '}
                    <span className="font-semibold">{formatDuration(sampleEnd - sampleStart)}</span>
                  </li>
                  <li>Tidak hadir → tidak dibayar</li>
                </ul>
              )}
            </Preview>
          }
        >
          <div className="rounded-lg border border-slate-200 p-3 sm:col-span-2">
            <Toggle
              checked={deduct}
              onChange={(checked) => setForm((current) => ({ ...current, salary_deduction_enabled: checked }))}
              label="Potong gaji sesuai jam hadir"
              description={
                deduct
                  ? 'Aktif: gaji mengikuti menit hadir, sehingga terlambat atau pulang lebih cepat mengurangi gaji.'
                  : 'Nonaktif: tidak ada potongan gaji. Staff yang hadir dibayar penuh sepanjang jam shift.'
              }
            />
          </div>
          {deduct && (
            <>
              <Select label="Satuan pembulatan" options={ROUNDING_UNITS} {...field('rounding_unit_minutes')} />
              <Select label="Cara pembulatan" options={ROUNDING_MODES} {...field('rounding_mode')} />
            </>
          )}
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
