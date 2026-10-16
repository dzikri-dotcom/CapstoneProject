import { CalendarClock, History, LogIn, LogOut, Repeat, ScanLine } from 'lucide-react'
import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react'
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
import { getMyAttendanceStatus, scanAttendance } from '@/services/attendanceService'
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
        <p className="text-sm text-slate-500">
          {formatShortDate(item.date)}
          {item.post_name && (
            <>
              {' · '}
              <span className="font-medium text-slate-700">
                {item.post_name}
                {item.post_location && `, ${item.post_location}`}
              </span>
            </>
          )}
        </p>
        <div className="mt-2">{children}</div>
      </div>
    </div>
  )
}

/** Keterangan aksi yang akan dijalankan saat staff scan QR sekarang. */
const ACTION_HINT = {
  check_in: { icon: LogIn, label: 'Scan untuk check-in' },
  check_out: { icon: LogOut, label: 'Scan untuk check-out' },
  handover: { icon: Repeat, label: 'Scan untuk pergantian sesi' },
}

export default function Attendance() {
  const toast = useToast()
  const [searchParams, setSearchParams] = useSearchParams()
  const [scanning, setScanning] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [actionError, setActionError] = useState(null)
  const [version, setVersion] = useState(0)
  const [fresh, setFresh] = useState(null) // status terbaru dari respons scan
  const { data: fetched, loading, error } = useFetch(getMyAttendanceStatus, [version])
  const status = fresh ?? fetched
  const linkToken = searchParams.get('t')
  const handledLink = useRef(null)

  const submit = useCallback(
    async (token) => {
      setSubmitting(true)
      setActionError(null)
      try {
        // Server yang menentukan aksinya berdasarkan sesi: check-in, check-out, atau pergantian sesi.
        const result = await scanAttendance(token)
        toast.success(result.message)
        setFresh(result.data)
      } catch (err) {
        setActionError(getErrorMessage(err))
        setFresh(null)
        setVersion((value) => value + 1)
      } finally {
        setSubmitting(false)
      }
    },
    [toast],
  )

  // QR dipindai dengan kamera bawaan HP membuka link ?t=TOKEN → langsung diproses sekali (QR hanya berlaku sebentar).
  useEffect(() => {
    if (!linkToken || handledLink.current === linkToken) return
    handledLink.current = linkToken
    setSearchParams({}, { replace: true })
    submit(linkToken)
  }, [linkToken, setSearchParams, submit])

  function handleScan(text) {
    setScanning(false)
    const value = extractToken(text)
    if (!value) {
      setActionError('QR tidak dikenali. Pastikan Anda memindai QR presensi di ruang logistik.')
      return
    }
    submit(value)
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
  const hint = ACTION_HINT[status.next_action]

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Presensi</h1>
        <p className="mt-1 text-sm text-slate-500">
          Cukup scan QR Code di layar ruang logistik — sistem otomatis menentukan check-in, check-out, atau pergantian sesi
          sesuai shift Anda.
        </p>
      </div>

      {actionError && (
        <Alert variant="error" title="Presensi belum berhasil">
          {actionError}
        </Alert>
      )}

      {status.check_out && (
        <Card title="Sedang bertugas">
          <ShiftSummary item={status.check_out}>
            <AttendanceBadges item={status.check_out} />
            <p className="mt-2 text-sm text-slate-600">
              Check-in pukul <strong>{status.check_out.attendance.check_in_time}</strong>. Scan QR untuk check-out paling
              lambat pukul <strong>{status.check_out.check_out_closes}</strong>.
              {status.next_action === 'handover' &&
                ' Shift berikutnya sudah bisa dimulai — satu kali scan akan menutup sesi ini dan membuka sesi berikutnya.'}
            </p>
          </ShiftSummary>
        </Card>
      )}

      {status.check_in && (
        <Card title={status.check_out ? 'Shift berikutnya' : 'Siap check-in'}>
          <ShiftSummary item={status.check_in}>
            <p className="text-sm text-slate-600">
              Check-in dibuka sejak {status.check_in.check_in_opens}. Lewat pukul <strong>{status.check_in.late_after}</strong>{' '}
              dianggap terlambat, dan check-in ditutup pukul <strong>{status.check_in.check_in_closes}</strong> (lewat dari itu
              dianggap tidak hadir).
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

      <div className="space-y-2">
        <Button
          icon={hint?.icon ?? ScanLine}
          loading={submitting}
          className="h-12 w-full text-base"
          onClick={() => setScanning(true)}
        >
          {submitting ? 'Memproses presensi…' : (hint?.label ?? 'Scan QR Code')}
        </Button>
        <p className="text-center text-xs text-slate-500">
          Bisa juga memakai aplikasi kamera HP — presensi langsung diproses saat link terbuka.
        </p>
      </div>

      {status.today.length > 0 && (
        <Card title="Shift hari ini" bodyClassName="p-0">
          <ul className="divide-y divide-slate-100">
            {status.today.map((item) => (
              <li key={item.assignment_id} className="flex items-center justify-between gap-3 px-5 py-3">
                <div>
                  <p className="font-medium text-slate-800 tabular-nums">
                    {item.start_time}–{item.end_time}
                    {item.post_name && <span className="font-normal text-slate-500"> · {item.post_name}</span>}
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
