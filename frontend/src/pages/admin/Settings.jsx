import { useState } from 'react'
import OperationalHoursForm from '@/components/settings/OperationalHoursForm'
import SystemSettingsForm from '@/components/settings/SystemSettingsForm'
import Alert from '@/components/ui/Alert'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getOperationalHours, getSettings } from '@/services/settingService'

export default function Settings() {
  const hoursQuery = useFetch(getOperationalHours)
  const settingsQuery = useFetch(getSettings)
  const [savedHours, setSavedHours] = useState(null)
  const [savedSettings, setSavedSettings] = useState(null)

  const hours = savedHours ?? hoursQuery.data
  const settings = savedSettings ?? settingsQuery.data
  const error = hoursQuery.error ?? settingsQuery.error

  return (
    <>
      <PageHeader
        title="Pengaturan Sistem"
        description="Perubahan berlaku untuk jadwal yang di-generate berikutnya dan presensi/penggajian selanjutnya."
      />

      {error && !(hours && settings) && (
        <Alert variant="error" title="Pengaturan tidak dapat dimuat">
          {getErrorMessage(error)}
        </Alert>
      )}

      {!error && !(hours && settings) && (
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Memuat pengaturan…
        </div>
      )}

      {hours && settings && (
        <div className="space-y-6">
          <OperationalHoursForm
            initialDays={hours}
            shiftDuration={settings.shift_duration_minutes}
            onSaved={setSavedHours}
          />
          <SystemSettingsForm
            initial={settings}
            sampleDay={hours.find((day) => day.is_active)}
            onSaved={setSavedSettings}
          />
        </div>
      )}
    </>
  )
}
