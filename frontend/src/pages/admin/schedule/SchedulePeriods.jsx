import { CalendarRange, ChevronRight, Plus, RotateCw } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import CreatePeriodModal from '@/components/schedule/CreatePeriodModal'
import PeriodStatusBadge from '@/components/schedule/PeriodStatusBadge'
import Alert from '@/components/ui/Alert'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Pagination from '@/components/ui/Pagination'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { listPeriods } from '@/services/scheduleService'
import { formatDateRange, formatDateTime } from '@/utils/format'

export default function SchedulePeriods() {
  const toast = useToast()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const { data, loading, error, reload } = useFetch(() => listPeriods(page), [page])
  const periods = data?.data ?? []

  return (
    <>
      <PageHeader
        title="Jadwal Shift"
        description="Generate jadwal otomatis dengan algoritma Round Robin, periksa dan edit hasilnya, lalu publikasikan ke staff."
        actions={
          <Button icon={Plus} onClick={() => setCreating(true)}>
            Buat periode
          </Button>
        }
      />

      {data && (
        <div className="mb-6 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
          <RotateCw className="mt-0.5 size-4 shrink-0" />
          <p>
            Antrian Round Robin periode baru akan dimulai dari{' '}
            <strong>{data.next_pointer?.name ?? 'staff pertama (belum ada periode sebelumnya)'}</strong>, melanjutkan
            posisi pointer terakhir agar giliran antar periode tetap adil.
          </p>
        </div>
      )}

      <div className="rounded-2xl border border-slate-200/70 bg-white shadow-soft">
        {error && !data && (
          <div className="p-4">
            <Alert variant="error" title="Gagal memuat periode">
              {getErrorMessage(error)}{' '}
              <button type="button" onClick={reload} className="font-semibold underline">
                Coba lagi
              </button>
            </Alert>
          </div>
        )}

        {!data && loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
            <Spinner className="size-5 text-brand-600" /> Memuat periode jadwal…
          </div>
        )}

        {data && periods.length === 0 && (
          <EmptyState
            icon={CalendarRange}
            title="Belum ada periode jadwal"
            description="Buat periode (misal satu minggu), lalu klik Generate Jadwal untuk menyusun shift secara otomatis."
            action={
              <Button icon={Plus} onClick={() => setCreating(true)}>
                Buat periode pertama
              </Button>
            }
          />
        )}

        {periods.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {periods.map((period) => (
              <li key={period.id}>
                <Link to={`/admin/jadwal/${period.id}`} className="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50">
                  <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/30">
                    <CalendarRange className="size-5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-medium text-slate-900">{formatDateRange(period.start_date, period.end_date)}</p>
                      <PeriodStatusBadge status={period.status} />
                      {period.understaffed_slots_count > 0 && (
                        <Badge color="red">{period.understaffed_slots_count} slot kurang staff</Badge>
                      )}
                    </div>
                    <p className="mt-0.5 text-sm text-slate-500">
                      {period.slots_count
                        ? `${period.slots_count} slot · ${period.assignments_count}/${period.required_staff_total} penugasan terisi · di-generate ${formatDateTime(period.generated_at)}`
                        : 'Belum di-generate'}
                    </p>
                  </div>
                  <ChevronRight className="size-5 shrink-0 text-slate-300" />
                </Link>
              </li>
            ))}
          </ul>
        )}

        <Pagination meta={data?.meta} onPageChange={setPage} itemLabel="periode" />
      </div>

      {creating && (
        <CreatePeriodModal
          onClose={() => setCreating(false)}
          onCreated={(period) => {
            toast.success('Periode dibuat. Klik "Generate jadwal" untuk menjalankan algoritma.')
            navigate(`/admin/jadwal/${period.id}`)
          }}
        />
      )}
    </>
  )
}
