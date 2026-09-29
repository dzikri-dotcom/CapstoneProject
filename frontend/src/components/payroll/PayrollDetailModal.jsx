import { FileDown } from 'lucide-react'
import { useState } from 'react'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { downloadPayslip, getPayrollDetail } from '@/services/payrollService'
import { formatDuration, formatRupiah } from '@/utils/format'
import PayrollItemsTable from './PayrollItemsTable'

/**
 * Rincian gaji satu staff per tanggal (admin).
 */
export default function PayrollDetailModal({ detailId, onClose }) {
  const toast = useToast()
  const { data: detail, loading, error } = useFetch(() => getPayrollDetail(detailId), [detailId])
  const [downloading, setDownloading] = useState(false)

  async function handleSlip() {
    setDownloading(true)
    try {
      await downloadPayslip(detailId)
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setDownloading(false)
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="xl"
      title={detail ? `Rincian gaji ${detail.staff.name}` : 'Rincian gaji'}
      description={
        detail &&
        `${detail.staff.nim} · ${detail.total_shifts} shift hadir · ${formatDuration(detail.total_minutes)} dibayar · ${formatRupiah(detail.total_amount)}`
      }
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Tutup
          </Button>
          <Button icon={FileDown} loading={downloading} onClick={handleSlip} disabled={!detail}>
            Unduh slip PDF
          </Button>
        </>
      }
    >
      {!detail && loading && (
        <div className="flex justify-center py-10">
          <Spinner className="size-5 text-brand-600" />
        </div>
      )}
      {error && !detail && <Alert variant="error">{getErrorMessage(error)}</Alert>}
      {detail && <PayrollItemsTable items={detail.items} />}
    </Modal>
  )
}
