import { useState } from 'react'
import RateEditor from '@/components/rates/RateEditor'
import RateHistory from '@/components/rates/RateHistory'
import Alert from '@/components/ui/Alert'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getCurrentRates, getRateHistory } from '@/services/hourlyRateService'

export default function HourlyRates() {
  const [version, setVersion] = useState(0)
  const currentQuery = useFetch(getCurrentRates, [version])
  const historyQuery = useFetch(getRateHistory, [version])
  const refresh = () => setVersion((value) => value + 1)

  const current = currentQuery.data
  // Form dibuat ulang setelah menyimpan / saat tarif berlaku berubah, agar isiannya segar.
  const editorKey = `${version}:${current?.effective_date}:${current?.slots.map((slot) => slot.rate).join(',')}`

  return (
    <>
      <PageHeader
        title="Tarif per Jam"
        description="Tarif dibayar per slot jam. Setiap perubahan disimpan sebagai versi baru dengan tanggal berlaku, sehingga gaji periode lama tetap memakai tarif lamanya."
      />

      {currentQuery.error && !current && (
        <Alert variant="error" title="Tarif tidak dapat dimuat">
          {getErrorMessage(currentQuery.error)}
        </Alert>
      )}

      {!current && !currentQuery.error && (
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Memuat tarif…
        </div>
      )}

      {current && (
        <>
          {current.missing_count > 0 && (
            <Alert variant="warning" title="Ada slot jam yang belum punya tarif" className="mb-6">
              {current.missing_count} slot jam operasional belum memiliki tarif, sehingga jam kerja pada slot tersebut tidak
              akan dibayar. Isi tarifnya lalu simpan.
            </Alert>
          )}

          <div className="grid items-start gap-6 xl:grid-cols-3">
            <RateEditor
              key={editorKey}
              className="xl:col-span-2"
              current={current}
              versions={historyQuery.data}
              onSaved={refresh}
            />
            <RateHistory versions={historyQuery.data} onDeleted={refresh} />
          </div>
        </>
      )}
    </>
  )
}
