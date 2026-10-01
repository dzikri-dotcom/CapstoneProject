import { useState } from 'react'
import OperationalHoursForm from '@/components/settings/OperationalHoursForm'
import PostManager from '@/components/settings/PostManager'
import ShiftManager from '@/components/settings/ShiftManager'
import SystemSettingsForm from '@/components/settings/SystemSettingsForm'
import Alert from '@/components/ui/Alert'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getOperationalHours, getSettings } from '@/services/settingService'
import { postApi, shiftApi } from '@/services/shiftPostService'

export default function Settings() {
  const hoursQuery = useFetch(getOperationalHours)
  const settingsQuery = useFetch(getSettings)
  const shiftsQuery = useFetch(shiftApi.list)
  const postsQuery = useFetch(postApi.list)
  const [savedHours, setSavedHours] = useState(null)
  const [savedSettings, setSavedSettings] = useState(null)
  const [savedShifts, setSavedShifts] = useState(null)
  const [savedPosts, setSavedPosts] = useState(null)

  const hours = savedHours ?? hoursQuery.data
  const settings = savedSettings ?? settingsQuery.data
  const shifts = savedShifts ?? shiftsQuery.data
  const posts = savedPosts ?? postsQuery.data
  const error = hoursQuery.error ?? settingsQuery.error ?? shiftsQuery.error ?? postsQuery.error
  const ready = hours && settings && shifts && posts

  return (
    <>
      <PageHeader
        title="Pengaturan Sistem"
        description="Perubahan berlaku untuk jadwal yang di-generate berikutnya dan presensi/penggajian selanjutnya."
      />

      {error && !ready && (
        <Alert variant="error" title="Pengaturan tidak dapat dimuat">
          {getErrorMessage(error)}
        </Alert>
      )}

      {!error && !ready && (
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Memuat pengaturan…
        </div>
      )}

      {ready && (
        <div className="space-y-6">
          <div className="grid items-start gap-6 xl:grid-cols-2">
            <ShiftManager shifts={shifts} onChange={setSavedShifts} />
            <PostManager posts={posts} onChange={setSavedPosts} />
          </div>
          <OperationalHoursForm initialDays={hours} shifts={shifts} onSaved={setSavedHours} />
          <SystemSettingsForm initial={settings} shifts={shifts} posts={posts} onSaved={setSavedSettings} />
        </div>
      )}
    </>
  )
}
