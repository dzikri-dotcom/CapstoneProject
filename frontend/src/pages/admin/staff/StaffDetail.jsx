import { CalendarRange, GraduationCap, Hourglass, IdCard, KeyRound, Mail, Pencil, Phone } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useParams } from 'react-router'
import AvailabilityTimeline from '@/components/schedule/AvailabilityTimeline'
import ClassScheduleManager from '@/components/schedule/ClassScheduleManager'
import ResetPasswordModal from '@/components/staff/ResetPasswordModal'
import StaffFormModal from '@/components/staff/StaffFormModal'
import Alert from '@/components/ui/Alert'
import Avatar from '@/components/ui/Avatar'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import PageHeader from '@/components/ui/PageHeader'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { staffClassScheduleApi } from '@/services/classScheduleService'
import { getStaff } from '@/services/staffService'
import { formatDate } from '@/utils/format'

function InfoRow({ icon: Icon, label, children }) {
  return (
    <div className="flex items-start gap-3">
      <Icon className="mt-0.5 size-4 shrink-0 text-slate-400" />
      <div className="min-w-0">
        <dt className="text-xs text-slate-500">{label}</dt>
        <dd className="text-sm break-words text-slate-800">{children}</dd>
      </div>
    </div>
  )
}

export default function StaffDetail() {
  const { id } = useParams()
  const toast = useToast()
  const scheduleApi = useMemo(() => staffClassScheduleApi(id), [id])
  const { data: staff, loading, error, reload } = useFetch(() => getStaff(id), [id])
  const [availabilityVersion, setAvailabilityVersion] = useState(0)
  const { data: availability } = useFetch(() => scheduleApi.availability(), [id, availabilityVersion])
  const [editing, setEditing] = useState(false)
  const [resetting, setResetting] = useState(false)

  const backProps = { backTo: '/admin/staff', backLabel: 'Kembali ke daftar staff' }

  if (!staff && loading) {
    return (
      <>
        <PageHeader title="Detail staff" {...backProps} />
        <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
          <Spinner className="size-5 text-brand-600" /> Memuat data staff…
        </div>
      </>
    )
  }

  if (error && !staff) {
    return (
      <>
        <PageHeader title="Detail staff" {...backProps} />
        <Alert variant="error" title="Data staff tidak dapat dimuat">
          {getErrorMessage(error)}
        </Alert>
      </>
    )
  }

  return (
    <>
      <PageHeader
        {...backProps}
        title={staff.name}
        description={staff.study_program}
        actions={
          <>
            <Button variant="secondary" icon={KeyRound} onClick={() => setResetting(true)}>
              Reset password
            </Button>
            <Button icon={Pencil} onClick={() => setEditing(true)}>
              Edit data
            </Button>
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-1">
          <Card>
            <div className="mb-5 flex items-center gap-4">
              <Avatar name={staff.name} size="lg" />
              <div>
                <p className="font-semibold text-slate-900">{staff.name}</p>
                <div className="mt-1">
                  {staff.is_active ? <Badge color="green">Aktif</Badge> : <Badge color="gray">Nonaktif</Badge>}
                </div>
              </div>
            </div>
            <dl className="space-y-4">
              <InfoRow icon={IdCard} label="NIM">
                {staff.nim}
              </InfoRow>
              <InfoRow icon={GraduationCap} label="Program studi">
                {staff.study_program}
              </InfoRow>
              <InfoRow icon={Mail} label="Email login">
                {staff.email}
              </InfoRow>
              <InfoRow icon={Phone} label="No. HP">
                {staff.phone ?? '-'}
              </InfoRow>
              <InfoRow icon={Hourglass} label="Batas jam per minggu">
                {staff.effective_max_weekly_hours} jam
                <span className="text-slate-400"> · {staff.max_weekly_hours ? 'batas khusus' : 'mengikuti batas umum'}</span>
              </InfoRow>
              <InfoRow icon={CalendarRange} label="Terdaftar sejak">
                {formatDate(staff.created_at)}
              </InfoRow>
            </dl>
          </Card>

          <Card title="Riwayat penugasan">
            <p className="text-sm text-slate-600">
              {staff.shift_assignments_count
                ? `Sudah ditugaskan di ${staff.shift_assignments_count} slot shift.`
                : 'Belum pernah dijadwalkan.'}
            </p>
          </Card>
        </div>

        <div className="space-y-6 lg:col-span-2">
          <Card title="Jadwal kuliah mingguan" description="Admin bisa menambah atau mengoreksi jadwal kuliah staff.">
            <ClassScheduleManager
              api={scheduleApi}
              onChanged={() => {
                reload()
                setAvailabilityVersion((value) => value + 1)
              }}
            />
          </Card>

          <Card title="Jam kosong" description="Jam operasional dikurangi jadwal kuliah — dasar algoritma saat menugaskan shift.">
            {availability ? (
              <AvailabilityTimeline availability={availability} />
            ) : (
              <div className="flex justify-center py-8">
                <Spinner className="size-5 text-brand-600" />
              </div>
            )}
          </Card>
        </div>
      </div>

      {editing && (
        <StaffFormModal
          staff={staff}
          onClose={() => setEditing(false)}
          onSaved={(saved) => {
            toast.success(`Data ${saved.name} berhasil diperbarui.`)
            setEditing(false)
            reload()
          }}
        />
      )}

      {resetting && <ResetPasswordModal staff={staff} onClose={() => setResetting(false)} />}
    </>
  )
}
