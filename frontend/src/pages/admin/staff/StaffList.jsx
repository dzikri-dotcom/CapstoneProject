import { Eye, KeyRound, Pencil, Plus, Power, Search, Trash2, Users } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import ResetPasswordModal from '@/components/staff/ResetPasswordModal'
import StaffFormModal from '@/components/staff/StaffFormModal'
import Alert from '@/components/ui/Alert'
import Avatar from '@/components/ui/Avatar'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import EmptyState from '@/components/ui/EmptyState'
import PageHeader from '@/components/ui/PageHeader'
import Pagination from '@/components/ui/Pagination'
import SegmentedControl from '@/components/ui/SegmentedControl'
import { SkeletonRows } from '@/components/ui/Skeleton'
import useDebounce from '@/hooks/useDebounce'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { deleteStaff, listStaff, updateStaff } from '@/services/staffService'
import cn from '@/utils/cn'

const STATUS_TABS = [
  { value: '', label: 'Semua', summaryKey: 'total' },
  { value: 'active', label: 'Aktif', summaryKey: 'active' },
  { value: 'inactive', label: 'Nonaktif', summaryKey: 'inactive' },
]

function StatusBadge({ active }) {
  return active ? <Badge color="green">Aktif</Badge> : <Badge color="gray">Nonaktif</Badge>
}

function IconAction({ icon: Icon, label, onClick, to, danger }) {
  const className = cn(
    'rounded-md p-1.5 transition-colors',
    danger ? 'text-slate-400 hover:bg-red-50 hover:text-red-600' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
  )
  if (to) {
    return (
      <Link to={to} className={className} title={label} aria-label={label}>
        <Icon className="size-4" />
      </Link>
    )
  }
  return (
    <button type="button" onClick={onClick} className={className} title={label} aria-label={label}>
      <Icon className="size-4" />
    </button>
  )
}

export default function StaffList() {
  const toast = useToast()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const debouncedSearch = useDebounce(search.trim())

  const { data, loading, error, reload } = useFetch(
    () => listStaff({ search: debouncedSearch || undefined, status: status || undefined, page, per_page: 10 }),
    [debouncedSearch, status, page],
  )

  const [form, setForm] = useState(null) // { staff: null } = tambah, { staff } = edit
  const [resetTarget, setResetTarget] = useState(null)
  const [confirm, setConfirm] = useState(null) // { type: 'delete' | 'toggle', staff }
  const [confirming, setConfirming] = useState(false)

  const rows = data?.data ?? []
  const summary = data?.summary

  function handleSaved(saved) {
    toast.success(form.staff ? `Data ${saved.name} berhasil diperbarui.` : `${saved.name} berhasil ditambahkan.`)
    setForm(null)
    reload()
  }

  async function handleConfirm() {
    const { type, staff } = confirm
    setConfirming(true)
    try {
      if (type === 'delete') {
        await deleteStaff(staff.id)
        toast.success(`${staff.name} berhasil dihapus.`)
        // Jika baris terakhir di halaman ini dihapus, mundur satu halaman.
        if (rows.length === 1 && page > 1) setPage(page - 1)
      } else {
        await updateStaff(staff.id, {
          name: staff.name,
          email: staff.email,
          nim: staff.nim,
          phone: staff.phone,
          study_program: staff.study_program,
          is_active: !staff.is_active,
        })
        toast.success(`${staff.name} ${staff.is_active ? 'dinonaktifkan' : 'diaktifkan kembali'}.`)
      }
      setConfirm(null)
      reload()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setConfirming(false)
    }
  }

  function actionsFor(staff) {
    return (
      <>
        <IconAction icon={Eye} label="Lihat detail" to={`/admin/staff/${staff.id}`} />
        <IconAction icon={Pencil} label="Edit" onClick={() => setForm({ staff })} />
        <IconAction icon={KeyRound} label="Reset password" onClick={() => setResetTarget(staff)} />
        <IconAction
          icon={Power}
          label={staff.is_active ? 'Nonaktifkan' : 'Aktifkan'}
          onClick={() => setConfirm({ type: 'toggle', staff })}
        />
        <IconAction icon={Trash2} label="Hapus" danger onClick={() => setConfirm({ type: 'delete', staff })} />
      </>
    )
  }

  const isFiltered = Boolean(debouncedSearch || status)

  return (
    <>
      <PageHeader
        title="Student Staff"
        description="Kelola data dan akun login student staff logistik."
        actions={
          <Button icon={Plus} onClick={() => setForm({ staff: null })}>
            Tambah staff
          </Button>
        }
      />

      <div className="rounded-xl border border-slate-200/80 bg-white shadow-card">
        {/* Filter */}
        <div className="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center md:justify-between">
          <SegmentedControl
            aria-label="Filter status"
            options={STATUS_TABS.map((tab) => ({ value: tab.value, label: tab.label, count: summary?.[tab.summaryKey] }))}
            value={status}
            onChange={(value) => {
              setStatus(value)
              setPage(1)
            }}
          />
          <div className="relative md:w-80">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
              placeholder="Cari nama, email, atau NIM…"
              className="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-sm shadow-card outline-none transition hover:border-slate-300 focus:border-slate-400 focus:ring-[3px] focus:ring-slate-200/60"
            />
          </div>
        </div>

        {error && (
          <div className="p-4">
            <Alert variant="error" title="Gagal memuat data staff">
              {getErrorMessage(error)}{' '}
              <button type="button" onClick={reload} className="font-semibold underline">
                Coba lagi
              </button>
            </Alert>
          </div>
        )}

        {!data && loading && <SkeletonRows rows={6} />}

        {data && rows.length === 0 && (
          <EmptyState
            icon={Users}
            title={isFiltered ? 'Tidak ada staff yang cocok' : 'Belum ada staff'}
            description={isFiltered ? 'Coba ubah kata kunci atau filter status.' : 'Tambahkan student staff pertama Anda.'}
          />
        )}

        {rows.length > 0 && (
          <div className={cn('transition-opacity', loading && 'opacity-60')}>
            {/* Tabel (tablet & desktop) */}
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full text-left text-sm">
                <thead className="border-y border-slate-100 bg-slate-50/70 text-xs whitespace-nowrap text-slate-500">
                  <tr>
                    <th className="px-4 py-3 font-medium">Nama</th>
                    <th className="px-4 py-3 font-medium">NIM</th>
                    <th className="px-4 py-3 font-medium">Program studi</th>
                    <th className="px-4 py-3 font-medium">No. HP</th>
                    <th className="px-4 py-3 font-medium">Jadwal kuliah</th>
                    <th className="px-4 py-3 font-medium">Batas / minggu</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    <th className="px-4 py-3 text-right font-medium">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {rows.map((staff) => (
                    <tr key={staff.id} className="hover:bg-slate-50/60">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <Avatar name={staff.name} size="sm" />
                          <div className="min-w-0">
                            <Link to={`/admin/staff/${staff.id}`} className="font-medium text-slate-900 hover:text-brand-700">
                              {staff.name}
                            </Link>
                            <p className="truncate text-xs text-slate-500">{staff.email}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 font-mono text-xs text-slate-600">{staff.nim}</td>
                      <td className="px-4 py-3 text-slate-600">{staff.study_program}</td>
                      <td className="px-4 py-3 text-slate-600">{staff.phone ?? '-'}</td>
                      <td className="px-4 py-3 text-slate-600">
                        {staff.class_schedules_count ? `${staff.class_schedules_count} kelas` : <span className="text-slate-400">Belum diisi</span>}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap text-slate-600 tabular-nums">
                        {staff.effective_max_weekly_hours} jam
                        {staff.max_weekly_hours && <span className="ml-1 text-xs text-brand-700">khusus</span>}
                      </td>
                      <td className="px-4 py-3">
                        <StatusBadge active={staff.is_active} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-end">{actionsFor(staff)}</div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Kartu (HP) */}
            <ul className="divide-y divide-slate-100 md:hidden">
              {rows.map((staff) => (
                <li key={staff.id} className="p-4">
                  <div className="flex items-start gap-3">
                    <Avatar name={staff.name} size="sm" />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-start justify-between gap-2">
                        <Link to={`/admin/staff/${staff.id}`} className="font-medium text-slate-900">
                          {staff.name}
                        </Link>
                        <StatusBadge active={staff.is_active} />
                      </div>
                      <p className="text-xs text-slate-500">
                        {staff.nim} · {staff.study_program}
                      </p>
                      <div className="-ml-2 mt-2 flex">{actionsFor(staff)}</div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        <Pagination meta={data?.meta} onPageChange={setPage} itemLabel="staff" />
      </div>

      {form && <StaffFormModal staff={form.staff} onClose={() => setForm(null)} onSaved={handleSaved} />}

      {resetTarget && <ResetPasswordModal staff={resetTarget} onClose={() => setResetTarget(null)} />}

      <ConfirmDialog
        open={Boolean(confirm)}
        onClose={() => setConfirm(null)}
        onConfirm={handleConfirm}
        loading={confirming}
        variant={confirm?.type === 'toggle' && !confirm.staff.is_active ? 'primary' : 'danger'}
        title={
          confirm?.type === 'delete'
            ? 'Hapus staff?'
            : confirm?.staff.is_active
              ? 'Nonaktifkan staff?'
              : 'Aktifkan kembali staff?'
        }
        confirmLabel={
          confirm?.type === 'delete' ? 'Ya, hapus' : confirm?.staff.is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan'
        }
        message={
          confirm?.type === 'delete' ? (
            <>
              Data <strong>{confirm.staff.name}</strong> beserta akun login dan jadwal kuliahnya akan dihapus permanen.
              Staff yang sudah pernah dijadwalkan tidak bisa dihapus — nonaktifkan saja.
            </>
          ) : confirm?.staff.is_active ? (
            <>
              <strong>{confirm.staff.name}</strong> tidak akan bisa login dan tidak dimasukkan ke jadwal shift berikutnya.
              Riwayat presensi dan gaji tetap tersimpan.
            </>
          ) : (
            <>
              <strong>{confirm?.staff.name}</strong> akan bisa login kembali dan masuk ke antrian jadwal shift berikutnya.
            </>
          )
        }
      />
    </>
  )
}
