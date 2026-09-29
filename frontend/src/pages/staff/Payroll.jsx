import { ChevronDown, FileDown, Wallet } from 'lucide-react'
import { useState } from 'react'
import PayrollItemsTable from '@/components/payroll/PayrollItemsTable'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { downloadMyPayslip, getMyPayroll, getMyPayrolls } from '@/services/payrollService'
import cn from '@/utils/cn'
import { formatDateRange, formatDuration, formatRupiah } from '@/utils/format'

function PayrollItems({ detailId }) {
  const { data, loading, error } = useFetch(() => getMyPayroll(detailId), [detailId])
  if (!data && loading) {
    return (
      <div className="flex justify-center py-6">
        <Spinner className="size-5 text-brand-600" />
      </div>
    )
  }
  if (error && !data) return <Alert variant="error">{getErrorMessage(error)}</Alert>
  return <PayrollItemsTable items={data.items} />
}

export default function Payroll() {
  const toast = useToast()
  const { data: payrolls, loading, error } = useFetch(getMyPayrolls)
  const [open, setOpen] = useState(null)
  const [downloading, setDownloading] = useState(null)

  async function handleSlip(id) {
    setDownloading(id)
    try {
      await downloadMyPayslip(id)
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setDownloading(null)
    }
  }

  return (
    <>
      <PageHeader title="Rekap gaji" description="Gaji per periode yang sudah difinalkan koordinator logistik." />

      {!payrolls && loading && (
        <div className="flex justify-center py-16">
          <Spinner className="size-6 text-brand-600" />
        </div>
      )}
      {error && !payrolls && <Alert variant="error">{getErrorMessage(error)}</Alert>}

      {payrolls && payrolls.length === 0 && (
        <div className="rounded-xl border border-slate-200/80 bg-white shadow-card">
          <EmptyState
            icon={Wallet}
            title="Belum ada rekap gaji"
            description="Rekap dan slip gaji akan muncul di sini setelah koordinator memfinalkan penggajian."
          />
        </div>
      )}

      <div className="space-y-3">
        {payrolls?.map((payroll) => {
          const expanded = open === payroll.id
          return (
            <section key={payroll.id} className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-card">
              <div className="p-4">
                <p className="text-sm text-slate-500">{formatDateRange(payroll.period.start_date, payroll.period.end_date)}</p>
                <p className="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{formatRupiah(payroll.total_amount)}</p>
                <p className="mt-1 text-xs text-slate-500">
                  {payroll.total_shifts} shift hadir · {formatDuration(payroll.total_minutes)} dibayar
                  {payroll.absent_count > 0 && ` · ${payroll.absent_count} tidak hadir`}
                </p>
                <div className="mt-3 flex gap-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => setOpen(expanded ? null : payroll.id)}
                    aria-expanded={expanded}
                  >
                    Rincian
                    <ChevronDown className={cn('size-4 transition', expanded && 'rotate-180')} />
                  </Button>
                  <Button size="sm" icon={FileDown} loading={downloading === payroll.id} onClick={() => handleSlip(payroll.id)}>
                    Slip PDF
                  </Button>
                </div>
              </div>
              {expanded && (
                <div className="border-t border-slate-100 px-4">
                  <PayrollItems detailId={payroll.id} />
                </div>
              )}
            </section>
          )
        })}
      </div>
    </>
  )
}
