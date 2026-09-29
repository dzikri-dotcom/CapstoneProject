import {
  AlertTriangle,
  CalendarClock,
  LayoutGrid,
  Play,
  RefreshCw,
  RotateCw,
  ScrollText,
  Send,
  Trash2,
  Undo2,
  UserCheck,
} from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import AssignmentModal from '@/components/schedule/AssignmentModal'
import CandidatePicker from '@/components/schedule/CandidatePicker'
import PeriodStatusBadge from '@/components/schedule/PeriodStatusBadge'
import ScheduleGrid from '@/components/schedule/ScheduleGrid'
import { notifyResult, slotLabel } from '@/components/schedule/scheduleUtils'
import StaffHoursPanel from '@/components/schedule/StaffHoursPanel'
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
  addAssignment,
  deletePeriod,
  generatePeriod,
  getPeriod,
  publishPeriod,
  unpublishPeriod,
  updateAssignment,
} from '@/services/scheduleService'
import { formatDateRange, formatDateTime } from '@/utils/format'

const ACTIONS = {
  generate: {
    title: 'Generate jadwal?',
    confirmLabel: 'Ya, generate',
    variant: 'primary',
    run: generatePeriod,
  },
  regenerate: {
    title: 'Generate ulang jadwal?',
    confirmLabel: 'Ya, generate ulang',
    variant: 'danger',
    message: 'Semua penugasan di periode ini, termasuk hasil edit manual, akan diganti dengan hasil algoritma yang baru.',
    run: generatePeriod,
  },
  publish: {
    title: 'Publikasikan jadwal?',
    confirmLabel: 'Ya, publikasikan',
    variant: 'primary',
    message: 'Jadwal akan terlihat oleh semua staff dan dipakai untuk presensi.',
    run: publishPeriod,
  },
  unpublish: {
    title: 'Kembalikan ke draft?',
    confirmLabel: 'Ya, kembalikan',
    variant: 'danger',
    message: 'Jadwal akan disembunyikan dari staff sampai dipublikasikan lagi.',
    run: unpublishPeriod,
  },
  delete: {
    title: 'Hapus periode?',
    confirmLabel: 'Ya, hapus',
    variant: 'danger',
    message: 'Periode beserta seluruh slot, penugasan, dan log algoritmanya akan dihapus.',
    run: deletePeriod,
  },
}

export default function ScheduleDetail() {
  const { id } = useParams()
  const toast = useToast()
  const navigate = useNavigate()
  const { data, loading, error, reload } = useFetch(() => getPeriod(id), [id])
  const [confirm, setConfirm] = useState(null)
  const [running, setRunning] = useState(false)
  const [picker, setPicker] = useState(null) // { slot, assignment? }
  const [managing, setManaging] = useState(null) // { assignment, slot }

  const period = data?.data
  const meta = data?.meta

  if (!period && loading) {
    return (
      <div className="flex items-center justify-center gap-3 py-24 text-sm text-slate-500">
        <Spinner className="size-5 text-brand-600" /> Memuat jadwal…
      </div>
    )
  }

  if (!period) {
    return (
      <>
        <PageHeader title="Jadwal shift" backTo="/admin/jadwal" backLabel="Kembali ke daftar periode" />
        <Alert variant="error" title="Jadwal tidak dapat dimuat">
          {getErrorMessage(error)}
        </Alert>
      </>
    )
  }

  const isDraft = period.status === 'draft'
  const slots = period.slots
  const hasSlots = slots.length > 0
  const understaffed = slots.filter((slot) => slot.is_understaffed)
  const filled = slots.reduce((sum, slot) => sum + slot.assignments.length, 0)
  const required = slots.reduce((sum, slot) => sum + slot.required_staff, 0)
  const warningCount = Object.keys(meta?.warnings ?? {}).length

  async function runAction() {
    const action = ACTIONS[confirm]
    setRunning(true)
    try {
      const result = await action.run(period.id)
      toast.success(result.message)
      setConfirm(null)
      if (confirm === 'delete') navigate('/admin/jadwal', { replace: true })
      else reload()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setRunning(false)
    }
  }

  async function handlePick(candidate) {
    try {
      const result = picker.assignment
        ? await updateAssignment(picker.assignment.id, { staff_profile_id: candidate.staff_profile_id })
        : await addAssignment(picker.slot.id, candidate.staff_profile_id)
      notifyResult(toast, result)
      setPicker(null)
      reload()
    } catch (err) {
      toast.error(getErrorMessage(err))
    }
  }

  const confirmAction = confirm ? ACTIONS[confirm] : null

  return (
    <>
      <PageHeader
        backTo="/admin/jadwal"
        backLabel="Kembali ke daftar periode"
        title={`Jadwal ${formatDateRange(period.start_date, period.end_date)}`}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <PeriodStatusBadge status={period.status} />
            {period.generated_at && <span>Di-generate {formatDateTime(period.generated_at)}</span>}
            {period.published_at && <span>· Dipublikasikan {formatDateTime(period.published_at)}</span>}
          </span>
        }
        actions={
          <>
            {hasSlots && (
              <Link
                to={`/admin/jadwal/${period.id}/log`}
                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-300 transition ring-inset hover:bg-slate-50 active:scale-[0.97]"
              >
                <ScrollText className="size-4" /> Log algoritma
              </Link>
            )}
            {isDraft && hasSlots && (
              <Button variant="secondary" icon={RefreshCw} onClick={() => setConfirm('regenerate')}>
                Generate ulang
              </Button>
            )}
            {isDraft && hasSlots && (
              <Button icon={Send} onClick={() => setConfirm('publish')}>
                Publikasikan
              </Button>
            )}
            {!isDraft && (
              <Button variant="secondary" icon={Undo2} onClick={() => setConfirm('unpublish')}>
                Kembalikan ke draft
              </Button>
            )}
            {isDraft && (
              <Button variant="ghost" size="icon" onClick={() => setConfirm('delete')} aria-label="Hapus periode" title="Hapus periode">
                <Trash2 className="size-4" />
              </Button>
            )}
          </>
        }
      />

      {!hasSlots ? (
        <Card>
          <EmptyState
            icon={CalendarClock}
            title="Jadwal belum di-generate"
            description="Algoritma Round Robin akan membentuk slot dari jam operasional, lalu menugaskan staff aktif secara bergiliran dengan memperhatikan jadwal kuliah dan batas jam per minggu."
            action={
              <Button icon={Play} onClick={() => setConfirm('generate')}>
                Generate jadwal
              </Button>
            }
          />
        </Card>
      ) : (
        <div className="space-y-6">
          {!isDraft && (
            <Alert variant="info">
              Jadwal sudah dipublikasikan dan terlihat oleh staff. Perubahan manual akan langsung terlihat oleh staff.
            </Alert>
          )}
          {understaffed.length > 0 && (
            <Alert variant="warning" title={`${understaffed.length} slot kurang staff`}>
              {understaffed.map(slotLabel).join('; ')}. Tidak ada cukup staff yang kosong setelah seluruh antrian dicek.
              Klik <strong>Tambah</strong> pada slot bertanda merah untuk mengisi secara manual.
            </Alert>
          )}

          <div className="stagger grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <StatCard icon={LayoutGrid} label="Total slot" value={slots.length} tone="blue" />
            <StatCard icon={UserCheck} label="Penugasan terisi" value={`${filled}/${required}`} tone="green" />
            <StatCard icon={AlertTriangle} label="Slot kurang staff" value={understaffed.length} tone={understaffed.length ? 'brand' : 'gray'} />
            <StatCard
              icon={RotateCw}
              label="Pointer awal → akhir"
              value={
                <span className="text-base">
                  {period.start_pointer?.name?.split(' ')[0] ?? '-'} → {period.end_pointer?.name?.split(' ')[0] ?? '-'}
                </span>
              }
              tone="amber"
            />
          </div>

          <Card
            title="Jadwal per slot"
            description={
              warningCount
                ? `${warningCount} penugasan memiliki peringatan (⚠). Label M = diubah manual.`
                : 'Klik nama staff untuk mengganti, memindah, menukar, atau menghapus. Label M = diubah manual.'
            }
            bodyClassName="p-3 sm:p-4"
          >
            <ScheduleGrid
              slots={slots}
              warnings={meta?.warnings ?? {}}
              onAdd={(slot) => setPicker({ slot })}
              onManage={(assignment, slot) => setManaging({ assignment, slot })}
            />
          </Card>

          {meta?.staff_hours && <StaffHoursPanel staffHours={meta.staff_hours} />}
        </div>
      )}

      {picker && (
        <CandidatePicker
          slot={picker.slot}
          title={picker.assignment ? `Ganti ${picker.assignment.name}` : 'Tambah staff ke slot'}
          description={slotLabel(picker.slot)}
          onClose={() => setPicker(null)}
          onPick={handlePick}
        />
      )}

      {managing && (
        <AssignmentModal
          assignment={managing.assignment}
          slot={managing.slot}
          slots={slots}
          warnings={meta?.warnings?.[managing.assignment.id] ?? []}
          onClose={() => setManaging(null)}
          onReplace={() => {
            setPicker({ slot: managing.slot, assignment: managing.assignment })
            setManaging(null)
          }}
          onChanged={() => {
            setManaging(null)
            reload()
          }}
        />
      )}

      <ConfirmDialog
        open={Boolean(confirmAction)}
        onClose={() => setConfirm(null)}
        onConfirm={runAction}
        loading={running}
        variant={confirmAction?.variant}
        title={confirmAction?.title}
        confirmLabel={confirmAction?.confirmLabel}
        message={
          confirmAction?.message ??
          'Algoritma Round Robin akan membentuk slot shift dan menugaskan staff aktif secara bergiliran. Hasilnya berstatus draft dan masih bisa diedit.'
        }
      />
    </>
  )
}
