import { ChevronRight, Plus, Sigma, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import CreatePayrollPeriodModal from '@/components/payroll/CreatePayrollPeriodModal'
import PayrollStatusBadge from '@/components/payroll/PayrollStatusBadge'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Pagination from '@/components/ui/Pagination'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { listPayrollPeriods } from '@/services/payrollService'
import { formatDateRange, formatDateTime, formatRupiah } from '@/utils/format'

export default function PayrollPeriods() {
  const toast = useToast()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const { data, loading, error } = useFetch(() => listPayrollPeriods(page), [page])
  const periods = data?.data ?? []

  return (
    <>
      <PageHeader
        title="Penggajian"
        description="Hitung gaji student staff otomatis dari presensi, lalu finalkan dan ekspor."
        actions={
          <Button icon={Plus} onClick={() => setCreating(true)}>
            Buat periode gaji
          </Button>
        }
      />

      <div className="mb-6 flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        <Sigma className="mt-0.5 size-5 shrink-0 text-brand-600" />
        <p>
          <strong className="text-slate-800">Total Gaji = Σ (jam yang dibayar pada setiap slot jam × tarif slot jam tersebut)</strong>
          . Tanpa potongan gaji, staff yang hadir dibayar penuh sepanjang jam shift. Jika potongan diaktifkan di Pengaturan,
          jam yang dibayar mengikuti check-in s.d. check-out lalu dibulatkan. Tarif mengikuti versi yang berlaku pada tanggal
          shift.
        </p>
      </div>

      <div className="rounded-xl border border-slate-200/80 bg-white shadow-card">
        {error && !data && (
          <div className="p-4">
            <Alert variant="error">{getErrorMessage(error)}</Alert>
          </div>
        )}
        {!data && loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
            <Spinner className="size-5 text-brand-600" /> Memuat periode gaji…
          </div>
        )}
        {data && periods.length === 0 && (
          <EmptyState
            icon={Wallet}
            title="Belum ada periode gaji"
            description="Buat periode (misal satu bulan), lalu klik Hitung Gaji."
            action={
              <Button icon={Plus} onClick={() => setCreating(true)}>
                Buat periode gaji
              </Button>
            }
          />
        )}

        {periods.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {periods.map((period) => (
              <li key={period.id}>
                <Link to={`/admin/penggajian/${period.id}`} className="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50">
                  <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-card">
                    <Wallet className="size-5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-medium text-slate-900">{formatDateRange(period.start_date, period.end_date)}</p>
                      <PayrollStatusBadge status={period.status} />
                    </div>
                    <p className="mt-0.5 text-sm text-slate-500">
                      {period.calculated_at
                        ? `${period.details_count} staff · dihitung ${formatDateTime(period.calculated_at)}`
                        : 'Belum dihitung'}
                      {period.finalized_at && ` · final ${formatDateTime(period.finalized_at)}`}
                    </p>
                  </div>
                  <p className="text-right font-semibold text-slate-900 tabular-nums">
                    {period.calculated_at ? formatRupiah(period.total_amount) : '—'}
                  </p>
                  <ChevronRight className="size-5 shrink-0 text-slate-300" />
                </Link>
              </li>
            ))}
          </ul>
        )}

        <Pagination meta={data?.meta} onPageChange={setPage} itemLabel="periode" />
      </div>

      {creating && (
        <CreatePayrollPeriodModal
          onClose={() => setCreating(false)}
          onCreated={(period) => {
            toast.success('Periode gaji dibuat. Klik "Hitung gaji" untuk memproses presensi.')
            navigate(`/admin/penggajian/${period.id}`)
          }}
        />
      )}
    </>
  )
}
