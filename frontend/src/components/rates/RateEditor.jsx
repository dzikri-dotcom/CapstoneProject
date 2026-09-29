import { ArrowDown, ArrowUp, Save, WandSparkles } from 'lucide-react'
import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import fieldClass from '@/components/ui/fieldClass'
import Input from '@/components/ui/Input'
import useToast from '@/hooks/useToast'
import { getErrorMessage, getValidationErrors } from '@/services/api'
import { saveRates } from '@/services/hourlyRateService'
import cn from '@/utils/cn'
import { formatDate, formatRupiah } from '@/utils/format'
import { addDaysISO, todayISO } from '@/utils/time'

function RupiahInput({ className, ...props }) {
  return (
    <div className={cn('relative', className)}>
      <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">Rp</span>
      <input type="number" min={0} step={500} inputMode="numeric" className={cn(fieldClass(false), 'pl-9 text-right tabular-nums')} {...props} />
    </div>
  )
}

/**
 * Form versi tarif baru. `current` = tarif berlaku hari ini per slot jam.
 */
export default function RateEditor({ current, versions = [], onSaved, className }) {
  const toast = useToast()
  const minDate = current.locked_until ? addDaysISO(current.locked_until, 1) : undefined
  const [effectiveDate, setEffectiveDate] = useState(() => {
    const today = todayISO()
    return minDate && today < minDate ? minDate : today
  })
  const [values, setValues] = useState(() =>
    Object.fromEntries(current.slots.map((slot) => [slot.start_time, slot.rate === null ? '' : String(slot.rate)])),
  )
  const [bulk, setBulk] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const today = todayISO()
  const existing = versions.find((version) => version.effective_date === effectiveDate)
  const changedCount = current.slots.filter((slot) => String(slot.rate ?? '') !== values[slot.start_time]).length

  function applyToAll() {
    if (bulk === '') return
    setValues(Object.fromEntries(current.slots.map((slot) => [slot.start_time, bulk])))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    const missing = current.slots.filter((slot) => values[slot.start_time] === '')
    if (missing.length) {
      setErrors({ rates: `Tarif jam ${missing.map((slot) => slot.start_time).join(', ')} belum diisi.` })
      return
    }

    setSaving(true)
    setErrors({})
    try {
      await saveRates(
        effectiveDate,
        current.slots.map((slot) => ({
          start_time: slot.start_time,
          end_time: slot.end_time,
          rate: Number(values[slot.start_time]),
        })),
      )
      toast.success(`Tarif berlaku mulai ${formatDate(effectiveDate)} berhasil disimpan.`)
      onSaved?.()
    } catch (error) {
      const fieldErrors = getValidationErrors(error)
      if (Object.keys(fieldErrors).length) {
        const { effective_date: dateError, ...rest } = fieldErrors
        setErrors({ effective_date: dateError, rates: Object.values(rest)[0] })
      } else {
        toast.error(getErrorMessage(error))
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card
      className={className}
      title="Ubah tarif"
      description={
        current.effective_date
          ? `Tarif saat ini berlaku sejak ${formatDate(current.effective_date)}.`
          : 'Belum ada tarif yang berlaku.'
      }
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="Berlaku mulai tanggal"
            type="date"
            min={minDate}
            value={effectiveDate}
            onChange={(event) => setEffectiveDate(event.target.value)}
            error={errors.effective_date}
            hint={
              current.locked_until
                ? `Gaji s.d. ${formatDate(current.locked_until)} sudah final, jadi tarif tidak bisa berlaku sebelum tanggal itu.`
                : undefined
            }
            required
          />
          <div>
            <p className="mb-1.5 text-sm font-medium text-slate-700">Terapkan tarif yang sama ke semua jam</p>
            <div className="flex gap-2">
              <RupiahInput
                className="flex-1"
                aria-label="Tarif untuk semua jam"
                placeholder="10000"
                value={bulk}
                onChange={(event) => setBulk(event.target.value)}
              />
              <Button variant="secondary" icon={WandSparkles} onClick={applyToAll} disabled={bulk === ''}>
                Terapkan
              </Button>
            </div>
          </div>
        </div>

        {existing && (
          <Alert variant="warning">
            Versi tarif tanggal {formatDate(effectiveDate)} sudah ada dan akan <strong>diganti</strong> dengan isian ini.
          </Alert>
        )}
        {!existing && effectiveDate > today && (
          <Alert variant="info">
            Tarif baru mulai dipakai pada {formatDate(effectiveDate)}. Sampai tanggal itu, tarif lama tetap berlaku.
          </Alert>
        )}
        {errors.rates && <Alert variant="error">{errors.rates}</Alert>}

        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
              <tr>
                <th className="px-4 py-2.5 font-medium">Slot jam</th>
                <th className="px-4 py-2.5 text-right font-medium">Tarif saat ini</th>
                <th className="px-4 py-2.5 text-right font-medium">Tarif baru / jam</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {current.slots.map((slot) => {
                const value = values[slot.start_time]
                const changed = String(slot.rate ?? '') !== value && value !== ''
                const up = changed && slot.rate !== null && Number(value) > slot.rate
                const down = changed && slot.rate !== null && Number(value) < slot.rate
                return (
                  <tr key={slot.start_time} className={cn(changed && 'bg-amber-50/60')}>
                    <td className="px-4 py-2 font-medium whitespace-nowrap text-slate-700 tabular-nums">
                      {slot.start_time}–{slot.end_time}
                    </td>
                    <td className="px-4 py-2 text-right whitespace-nowrap text-slate-500 tabular-nums">
                      {slot.rate === null ? <span className="text-amber-600">Belum ada</span> : formatRupiah(slot.rate)}
                    </td>
                    <td className="px-4 py-2">
                      <div className="ml-auto flex max-w-48 items-center gap-2">
                        <span className="w-4 shrink-0">
                          {up && <ArrowUp className="size-4 text-emerald-600" aria-label="naik" />}
                          {down && <ArrowDown className="size-4 text-red-600" aria-label="turun" />}
                        </span>
                        <RupiahInput
                          className="flex-1"
                          aria-label={`Tarif ${slot.start_time}–${slot.end_time}`}
                          value={value}
                          onChange={(event) => setValues((current) => ({ ...current, [slot.start_time]: event.target.value }))}
                        />
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-slate-500">
            {changedCount ? `${changedCount} slot jam diubah.` : 'Belum ada perubahan dari tarif saat ini.'} Gaji periode
            sebelum tanggal berlaku tetap memakai tarif lama.
          </p>
          <Button type="submit" icon={Save} loading={saving}>
            Simpan tarif
          </Button>
        </div>
      </form>
    </Card>
  )
}
