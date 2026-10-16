import { Maximize, X } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import AttendanceBadges from '@/components/attendance/AttendanceBadges'
import Logo from '@/components/Logo'
import Alert from '@/components/ui/Alert'
import Spinner from '@/components/ui/Spinner'
import useFetch from '@/hooks/useFetch'
import { getErrorMessage } from '@/services/api'
import { getQrToken, listAttendances } from '@/services/attendanceService'
import { formatLongDate } from '@/utils/format'
import { todayISO } from '@/utils/time'

const clockFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
const pad = (value) => String(value).padStart(2, '0')

/** Kelompokkan penugasan per slot jam (08:00–10:00, dst). */
function groupBySlot(items) {
  const groups = new Map()
  items.forEach((item) => {
    const key = `${item.start_time}-${item.end_time}`
    if (!groups.has(key)) groups.set(key, { ...item, members: [] })
    groups.get(key).members.push(item)
  })
  return [...groups.values()]
}

/**
 * Layar QR presensi untuk perangkat di ruang logistik (login sebagai admin).
 * Token QR diminta ulang otomatis setiap kali masa berlakunya habis.
 */
export default function QrDisplay() {
  const [qr, setQr] = useState(null)
  const [qrError, setQrError] = useState(null)
  const [now, setNow] = useState(() => Date.now())
  const [refreshTick, setRefreshTick] = useState(0)
  const { data: attendance } = useFetch(() => listAttendances({ date: todayISO() }), [refreshTick])

  // Jam dan hitung mundur.
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 250)
    return () => clearInterval(id)
  }, [])

  // Status check-in staff diperbarui tiap 20 detik.
  useEffect(() => {
    const id = setInterval(() => setRefreshTick((value) => value + 1), 20000)
    return () => clearInterval(id)
  }, [])

  // Minta token baru tepat saat token lama habis.
  useEffect(() => {
    let active = true
    let timer

    const fetchToken = () => {
      getQrToken().then(
        (data) => {
          if (!active) return
          // Selisih jam server vs perangkat, agar hitung mundur tetap akurat walau jam perangkat meleset.
          const offset = new Date(data.server_time).getTime() - Date.now()
          const expiresAt = new Date(data.expires_at).getTime() - offset
          setQr({ token: data.token, expiresAt, duration: Math.max(expiresAt - Date.now(), 1000) })
          setQrError(null)
          timer = setTimeout(fetchToken, Math.max(expiresAt - Date.now(), 500) + 300)
        },
        (error) => {
          if (!active) return
          setQrError(getErrorMessage(error))
          timer = setTimeout(fetchToken, 5000)
        },
      )
    }

    timer = setTimeout(fetchToken, 0)
    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [])

  const secondsLeft = qr ? Math.max(Math.ceil((qr.expiresAt - now) / 1000), 0) : 0
  const progress = qr ? Math.max(Math.min((qr.expiresAt - now) / qr.duration, 1), 0) : 0
  const qrValue = qr ? `${window.location.origin}/staff/presensi?t=${qr.token}` : ''

  const current = new Date(now)
  const nowTime = `${pad(current.getHours())}:${pad(current.getMinutes())}`
  const groups = groupBySlot(attendance?.data ?? [])
  const active = groups.filter((group) => nowTime >= group.check_in_opens && nowTime <= group.check_out_closes)
  const upcoming = groups.find((group) => group.start_time > nowTime)
  const shown = active.length ? active : upcoming ? [upcoming] : []

  return (
    <div className="min-h-screen bg-slate-950 text-white">
      <header className="flex items-center justify-between px-6 py-4">
        <Logo inverted />
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => document.documentElement.requestFullscreen?.()}
            className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white"
          >
            <Maximize className="size-4" /> Layar penuh
          </button>
          <Link
            to="/admin/presensi"
            className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white"
          >
            <X className="size-4" /> Tutup
          </Link>
        </div>
      </header>

      <main className="mx-auto grid max-w-6xl gap-6 px-4 pb-10 sm:px-6 lg:grid-cols-[1.25fr_1fr] lg:items-start">
        <section className="rounded-2xl bg-white p-6 text-center text-slate-900 shadow-2xl sm:p-10">
          <h1 className="text-2xl font-semibold sm:text-3xl">Scan untuk presensi</h1>
          <p className="mt-2 text-slate-500">
            Buka menu <strong>Presensi</strong> di aplikasi atau kamera HP, lalu arahkan ke QR ini.
          </p>

          <div className="mx-auto mt-6 flex aspect-square w-full max-w-[400px] items-center justify-center">
            {qr ? (
              <QRCodeSVG value={qrValue} size={512} level="M" marginSize={2} className="h-full w-full" title="QR Code presensi" />
            ) : (
              <Spinner className="size-10 text-brand-600" />
            )}
          </div>

          <div className="mx-auto mt-6 max-w-[400px]">
            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
              <div className="h-full rounded-full bg-brand-600 transition-[width] duration-200" style={{ width: `${progress * 100}%` }} />
            </div>
            <p className="mt-2 text-sm text-slate-500 tabular-nums">
              QR berganti otomatis dalam <strong className="text-slate-800">{secondsLeft} detik</strong> — foto QR tidak bisa dipakai untuk titip absen.
            </p>
          </div>

          {qrError && (
            <Alert variant="error" className="mt-4 text-left">
              {qrError} Mencoba lagi…
            </Alert>
          )}
        </section>

        <aside className="space-y-6">
          <div className="rounded-2xl bg-white/[0.06] p-6 ring-1 ring-white/10">
            <p className="text-5xl font-semibold tracking-tight tabular-nums">{clockFormatter.format(current)}</p>
            <p className="mt-1 text-white/70">{formatLongDate(current)}</p>
          </div>

          <div className="rounded-2xl bg-white p-6 text-slate-900 shadow-xl">
            <h2 className="font-semibold">{active.length ? 'Shift saat ini' : 'Shift berikutnya hari ini'}</h2>
            {shown.length === 0 ? (
              <p className="mt-2 text-sm text-slate-500">Tidak ada shift lagi hari ini.</p>
            ) : (
              <div className="mt-3 space-y-4">
                {shown.map((group) => (
                  <div key={group.start_time}>
                    <p className="text-sm font-medium text-slate-500 tabular-nums">
                      {group.start_time}–{group.end_time} · check-in dibuka {group.check_in_opens}
                    </p>
                    <ul className="mt-2 space-y-2">
                      {group.members.map((member) => (
                        <li key={member.assignment_id} className="flex items-center justify-between gap-3">
                          <span className="min-w-0">
                            <span className="block truncate font-medium">{member.staff?.name}</span>
                            {member.post_name && <span className="block text-xs text-slate-500">{member.post_name}</span>}
                          </span>
                          <span className="flex items-center gap-2 text-xs text-slate-500">
                            {member.attendance?.check_in_time && <span className="tabular-nums">{member.attendance.check_in_time}</span>}
                            <AttendanceBadges item={member} />
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            )}
          </div>
        </aside>
      </main>
    </div>
  )
}
