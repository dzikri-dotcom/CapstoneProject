import { CalendarClock, CheckCircle2, History, LogIn, LogOut, ScanLine } from 'lucide-react'
import { lazy, Suspense, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import AttendanceBadges from '@/components/attendance/AttendanceBadges'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import ErrorBoundary from '@/components/ui/ErrorBoundary'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import useToast from '@/hooks/useToast'
import { getErrorMessage } from '@/services/api'
import { checkIn, checkOut, getMyAttendanceStatus } from '@/services/attendanceService'
import { formatShortDate } from '@/utils/format'

// Library pemindai cukup besar, jadi baru diunduh saat tombol "Scan QR Code" ditekan.
const QrScanner = lazy(() => import('@/components/attendance/QrScanner'))

/** Ambil token dari isi QR (link .../staff/presensi?t=TOKEN atau token mentah). */
function extractToken(text) {
  try {
    const token = new URL(text).searchParams.get('t')
    if (token) return token
  } catch {
    // bukan URL
  }
  return /^[A-Za-z0-9]{20,100}$/.test(text) ? text : null
}

function ShiftSummary({ item, children }) {
  return (
    <div className="flex items-start gap-4">
      <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
        <CalendarClock className="size-6" />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-lg font-semibold text-slate-900 tabular-nums">
          {item.start_time}–{item.end_time}
        </p>
        <p className="text-sm text-slate-500">{formatShortDate(item.date)}</p>
        <div className="mt-2">{children}</div>
      </div>
    </div>
  )
}

export default function Attendance() {
  const toast = useToast()
  const [searchParams, setSearchParams] = useSearchParams()
  const [scannedToken, setScannedToken] = useState(null)
  const [scanning, setScanning] = useState(false)
  const [submitting, setSubmitting] = useState(null)
  const [actionError, setActionError] = useState(null)
  const [version, setVersion] = useState(0)
  const { data: status, loading, error } = useFetch(getMyAttendanceStatus, [version])

  // Token dari link QR (dipindai dengan kamera bawaan HP) atau dari pemindai di aplikasi.
  const token = scannedToken ?? searchParams.get('t')

  function clearToken() {
    setScannedToken(null)
    if (searchParams.get('t')) setSearchParams({}, { replace: true })
  }

  async function submit(kind, tokenValue = token) {
    setSubmitting(kind)
    setActionError(null)
    try {
      const result = kind === 'in' ? await checkIn(tokenValue) : await checkOut(tokenValue)
      toast.success(result.message)
      clearToken()
      setVersion((value) => value + 1)
    } catch (err) {
      setActionError(getErrorMessage(err))
      if (err.response?.data?.errors?.token) clearToken()
    } finally {
      setSubmitting(null)
    }
  }

  function handleScan(text) {
    setScanning(false)
    const value = extractToken(text)
    if (!value) {
      setActionError('QR tidak dikenali. Pastikan Anda memindai QR presensi di ruang logistik.')
      return
    }
    // Jika hanya satu aksi yang mungkin, langsung jalankan.
    if (status?.check_in && !status?.check_out) submit('in', value)
    else if (status?.check_out && !status?.check_in) submit('out', value)
    else {
      setActionError(null)
      setScannedToken(value)
    }
  }

  if (!status) {
    return (
      <div className="py-16">
        {loading ? (
          <div className="flex justify-center">
            <Spinner className="size-6 text-brand-600" />
          </div>
        ) : (
          <Alert variant="error">{getErrorMessage(error)}</Alert>
        )}
      </div>
    )
  }

  const hasAction = Boolean(status.check_in || status.check_out)

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Presensi</h1>
        <p className="mt-1 text-sm text-slate-500">Scan QR Code di layar ruang logistik untuk check-in dan check-out.</p>
      </div>

      {actionError && (
        <Alert variant="error" title="Presensi belum berhasil">
          {actionError}
        </Alert>
      )}

      {token && (
        <Card className="border-emerald-200 bg-emerald-50/50">
          <div className="flex items-start gap-3">
            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
            <div className="flex-1 space-y-3">
              <div>
                <p className="font-medium text-emerald-900">Kode QR terbaca</p>
                <p className="text-sm text-emerald-800">Kode hanya berlaku sebentar — segera konfirmasi.</p>
              </div>
              {hasAction ? (
                <div className="flex flex-col gap-2">
                  {status.check_in && (
                    <Button icon={LogIn} loading={submitting === 'in'} disabled={Boolean(submitting)} onClick={() => submit('in')}>
                      Check-in shift {status.check_in.start_time}–{status.check_in.end_time}
                    </Button>
                  )}
                  {status.check_out && (
                    <Button
                      variant={status.check_in ? 'secondary' : 'primary'}
                      icon={LogOut}
                      loading={submitting === 'out'}
                      disabled={Boolean(submitting)}
                      onClick={() => submit('out')}
                    >
                      Check-out shift {status.check_out.start_time}–{status.check_out.end_time}
                    </Button>
                  )}
                </div>
              ) : (
                <p className="text-sm text-slate-600">Tidak ada shift yang bisa di-check-in atau di-check-out saat ini.</p>
              )}
              <button type="button" onClick={clearToken} className="text-sm text-slate-500 underline">
                Batal
              </button>
            </div>
          </div>
        </Card>
      )}

      {status.check_out && (
        <Card title="Sedang bertugas">
          <ShiftSummary item={status.check_out}>
            <AttendanceBadges item={status.check_out} />
            <p className="mt-2 text-sm text-slate-600">
              Check-in pukul <strong>{status.check_out.attendance.check_in_time}</strong>. Scan QR untuk check-out paling
              lambat pukul <strong>{status.check_out.check_out_closes}</strong>.
            </p>
          </ShiftSummary>
        </Card>
      )}

      {status.check_in && (
        <Card title={status.check_out ? 'Shift berikutnya' : 'Siap check-in'}>
          <ShiftSummary item={status.check_in}>
            <p className="text-sm text-slate-600">
              Check-in dibuka sejak {status.check_in.check_in_opens}. Lewat pukul <strong>{status.check_in.late_after}</strong>{' '}
              dianggap terlambat.
            </p>
          </ShiftSummary>
        </Card>
      )}

      {!hasAction && (
        <Card>
          {status.next ? (
            <ShiftSummary item={status.next}>
              <p className="text-sm text-slate-600">
                Shift berikutnya. Check-in dibuka pukul <strong>{status.next.check_in_opens}</strong>.
              </p>
            </ShiftSummary>
          ) : (
            <p className="text-sm text-slate-600">Belum ada shift terjadwal yang dipublikasikan untuk Anda.</p>
          )}
        </Card>
      )}

      {!token && (
        <div className="space-y-2">
          <Button icon={ScanLine} className="w-full py-3.5 text-base" onClick={() => setScanning(true)}>
            Scan QR Code
          </Button>
          <p className="text-center text-xs text-slate-500">
            Bisa juga memakai aplikasi kamera HP — link presensi akan terbuka otomatis.
          </p>
        </div>
      )}

      {status.today.length > 0 && (
        <Card title="Shift hari ini" bodyClassName="p-0">
          <ul className="divide-y divide-slate-100">
            {status.today.map((item) => (
              <li key={item.assignment_id} className="flex items-center justify-between gap-3 px-5 py-3">
                <div>
                  <p className="font-medium text-slate-800 tabular-nums">
                    {item.start_time}–{item.end_time}
                  </p>
                  <p className="text-xs text-slate-500 tabular-nums">
                    Masuk {item.attendance?.check_in_time ?? '—'} · Pulang {item.attendance?.check_out_time ?? '—'}
                  </p>
                </div>
                <AttendanceBadges item={item} />
              </li>
            ))}
          </ul>
        </Card>
      )}

      <Link to="/staff/riwayat" className="flex items-center justify-center gap-2 py-2 text-sm font-medium text-brand-700">
        <History className="size-4" /> Lihat riwayat presensi
      </Link>

      {scanning && (
        <ErrorBoundary
          fallback={() => (
            <Modal open onClose={() => setScanning(false)} title="Pemindai gagal dimuat">
              <Alert variant="error">
                Periksa koneksi internet lalu coba lagi, atau pindai QR dengan aplikasi kamera HP.
              </Alert>
            </Modal>
          )}
        >
          <Suspense fallback={null}>
            <QrScanner onResult={handleScan} onClose={() => setScanning(false)} />
          </Suspense>
        </ErrorBoundary>
      )}
    </div>
  )
}
