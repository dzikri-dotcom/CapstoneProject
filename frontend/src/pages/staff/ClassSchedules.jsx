import { useState } from 'react'
import AvailabilityTimeline from '@/components/schedule/AvailabilityTimeline'
import ClassScheduleManager from '@/components/schedule/ClassScheduleManager'
import Alert from '@/components/ui/Alert'
import Card from '@/components/ui/Card'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { myClassScheduleApi } from '@/services/classScheduleService'

export default function ClassSchedules() {
  const [version, setVersion] = useState(0)
  const { data: availability, loading, error } = useFetch(() => myClassScheduleApi.availability(), [version])

  return (
    <>
      <PageHeader
        title="Jadwal kuliah"
        description="Isi jadwal kuliah mingguan Anda. Jam di luar kuliah dipakai koordinator untuk menyusun shift secara otomatis."
      />

      <div className="space-y-5">
        <Card title="Jadwal per minggu">
          <ClassScheduleManager api={myClassScheduleApi} onChanged={() => setVersion((value) => value + 1)} />
        </Card>

        <Card title="Jam kosong Anda" description="Jam operasional logistik dikurangi jadwal kuliah.">
          {availability ? (
            <AvailabilityTimeline availability={availability} />
          ) : loading ? (
            <div className="flex justify-center py-8">
              <Spinner className="size-5 text-brand-600" />
            </div>
          ) : (
            <Alert variant="error">{getErrorMessage(error)}</Alert>
          )}
        </Card>
      </div>
    </>
  )
}
