import { Calculator, Clock, FileDown, FileSpreadsheet, Lock, ReceiptText, Trash2, Users, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import PayrollDetailModal from '@/components/payroll/PayrollDetailModal'
import PayrollStatusBadge from '@/components/payroll/PayrollStatusBadge'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import StatCard from '@/components/ui/StatCard'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import {
  calculatePayroll,
  deletePayrollPeriod,
  downloadPayslip,
  exportPayroll,
  finalizePayroll,
  getPayrollPeriod,
} from '@/services/payrollService'
import { formatDateRange, formatDateTime, formatDuration, formatRupiah } from '@/utils/format'

const ACTIONS = {
  calculate: { run: calculatePayroll },
  finalize: {
    run: finalizePayroll,
    title: 'Finalkan penggajian?',
    confirmLabel: 'Ya, finalkan',
    variant: 'primary',
    message:
      'Gaji akan dihitung ulang dengan presensi terbaru, lalu dikunci. Setelah final, gaji, presensi, dan tarif pada periode ini tidak bisa diubah, dan staff dapat melihat rekap serta slip gajinya.',
  },
  delete: {
    run: deletePayrollPeriod,
    title: 'Hapus periode gaji?',
    confirmLabel: 'Ya, hapus',
    variant: 'danger',
    message: 'Periode beserta hasil perhitungannya akan dihapus. Data presensi tidak terpengaruh.',
  },
}

function roundingLabel(snapshot) {
  if (!snapshot) return null
  if (snapshot.rounding_unit_minutes <= 1) return 'per menit (tanpa pembulatan)'
  return `per ${snapshot.rounding_unit_minutes} menit, ${snapshot.rounding_mode === 'nearest' ? 'ke terdekat' : 'ke bawah'}`
}

export default function PayrollDetail() {
  const { id } = useParams()
  const toast = useToast()
  const navigate = useNavigate()
  const { data: period, loading, error, reload } = useFetch(() => getPayrollPeriod(id), [id])
  const [confirm, setConfirm] = useState(null)
  const [busy, setBusy] = useState(null)
  const [viewing, setViewing] = useState(null)

  if (!period) {
    return (
      <>
        <PageHeader title="Penggajian" backTo="/admin/penggajian" backLabel="Kembali ke daftar periode" />
        {loading ? (
          <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
            <Spinner className="size-5 text-brand-600" /> Memuat penggajian…
          </div>
        ) : (
          <Alert variant="error">{getErrorMessage(error)}</Alert>
        )}
      </>
    )
  }

  const isFinal = period.status === 'final'
  const calculated = Boolean(period.calculated_at)
  const details = period.details ?? []
  const totalMinutes = details.reduce((sum, detail) => sum + detail.total_minutes, 0)
  const totalShifts = details.reduce((sum, detail) => sum + detail.total_shifts, 0)

  async function run(kind) {
    setBusy(kind)
    try {
      const result = await ACTIONS[kind].run(period.id)
      toast.success(result.message)
      setConfirm(null)
      if (kind === 'delete') navigate('/admin/penggajian', { replace: true })
      else reload()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setBusy(null)
    }
  }

  async function download(kind, action) {
    setBusy(kind)
    try {
      await action()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setBusy(null)
    }
  }

  const confirmAction = confirm ? ACTIONS[confirm] : null

  return (
    <>
      <PageHeader
        backTo="/admin/penggajian"
        backLabel="Kembali ke daftar periode"
        title={`Gaji ${formatDateRange(period.start_date, period.end_date)}`}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <PayrollStatusBadge status={period.status} />
            {calculated && <span>Dihitung {formatDateTime(period.calculated_at)}</span>}
            {roundingLabel(period.settings_snapshot) && <span>· Pembulatan {roundingLabel(period.settings_snapshot)}</span>}
          </span>
        }
        actions={
          <>
            {calculated && (
              <Button
                variant="secondary"
                icon={FileSpreadsheet}
                loading={busy === 'export'}
                onClick={() => download('export', () => exportPayroll(period.id))}
              >
                Ekspor Excel
              </Button>
            )}
            {!isFinal && calculated && (
              <Button variant="secondary" icon={Calculator} loading={busy === 'calculate'} onClick={() => run('calculate')}>
                Hitung ulang
              </Button>
            )}
            {!isFinal && calculated && (
              <Button icon={Lock} onClick={() => setConfirm('finalize')}>
                Finalkan
              </Button>
            )}
            {!isFinal && (
              <Button variant="ghost" size="icon" onClick={() => setConfirm('delete')} aria-label="Hapus periode gaji" title="Hapus periode gaji">
                <Trash2 className="size-4" />
              </Button>
            )}
          </>
        }
      />

      {isFinal && (
        <Alert variant="success" title="Penggajian sudah final" className="mb-6">
          Difinalkan {formatDateTime(period.finalized_at)}
          {period.finalized_by && ` oleh ${period.finalized_by}`}. Presensi dan tarif pada periode ini terkunci; staff sudah
          dapat melihat rekap dan mengunduh slip gajinya.
        </Alert>
      )}
      {!isFinal && calculated && (
        <Alert variant="info" className="mb-6">
          Masih draft. Jika ada koreksi presensi setelah perhitungan, klik <strong>Hitung ulang</strong>. Finalisasi juga
          menghitung ulang secara otomatis.
        </Alert>
      )}

      {!calculated ? (
        <Card>
          <EmptyState
            icon={Calculator}
            title="Gaji belum dihitung"
            description="Sistem akan mengambil presensi dari jadwal yang dipublikasikan pada rentang tanggal ini, lalu menghitung gaji setiap staff."
            action={
              <Button icon={Calculator} loading={busy === 'calculate'} onClick={() => run('calculate')}>
                Hitung gaji
              </Button>
            }
          />
        </Card>
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard icon={Wallet} label="Total gaji" value={formatRupiah(period.total_amount)} />
            <StatCard icon={Users} label="Staff" value={details.length} tone="blue" />
            <StatCard icon={ReceiptText} label="Shift hadir" value={totalShifts} tone="green" />
            <StatCard icon={Clock} label="Total jam dibayar" value={formatDuration(totalMinutes, { short: true })} tone="amber" />
          </div>

          <Card title="Rekap per staff" bodyClassName="p-0">
            {details.length === 0 ? (
              <EmptyState
                icon={Users}
                title="Tidak ada presensi pada periode ini"
                description="Pastikan jadwal pada rentang tanggal ini sudah dipublikasikan dan shift-nya sudah berjalan."
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
                    <tr>
                      <th className="px-4 py-3 font-medium">Staff</th>
                      <th className="px-4 py-3 text-right font-medium">Shift hadir</th>
                      <th className="px-4 py-3 text-right font-medium">Tidak hadir</th>
                      <th className="px-4 py-3 text-right font-medium">Jam dibayar</th>
                      <th className="px-4 py-3 text-right font-medium">Total gaji</th>
                      <th className="px-4 py-3" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {details.map((detail) => (
                      <tr key={detail.id} className="hover:bg-slate-50/60">
                        <td className="px-4 py-3">
                          <p className="font-medium text-slate-900">{detail.staff.name}</p>
                          <p className="text-xs text-slate-500">{detail.staff.nim}</p>
                        </td>
                        <td className="px-4 py-3 text-right tabular-nums">{detail.total_shifts}</td>
                        <td className="px-4 py-3 text-right tabular-nums">
                          {detail.absent_count ? <span className="text-red-600">{detail.absent_count}</span> : 0}
                        </td>
                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                          {formatDuration(detail.total_minutes, { short: true })}
                        </td>
                        <td className="px-4 py-3 text-right font-semibold whitespace-nowrap text-slate-900 tabular-nums">
                          {formatRupiah(detail.total_amount)}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex justify-end gap-1">
                            <Button variant="ghost" size="sm" icon={ReceiptText} onClick={() => setViewing(detail.id)}>
                              Rincian
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              icon={FileDown}
                              loading={busy === `slip-${detail.id}`}
                              onClick={() => download(`slip-${detail.id}`, () => downloadPayslip(detail.id))}
                            >
                              Slip
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </div>
      )}

      {viewing && <PayrollDetailModal detailId={viewing} onClose={() => setViewing(null)} />}

      <ConfirmDialog
        open={Boolean(confirmAction)}
        onClose={() => setConfirm(null)}
        onConfirm={() => run(confirm)}
        loading={Boolean(busy)}
        variant={confirmAction?.variant}
        title={confirmAction?.title}
        confirmLabel={confirmAction?.confirmLabel}
        message={confirmAction?.message}
      />
    </>
  )
}
