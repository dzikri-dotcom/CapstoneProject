import { Html5Qrcode } from 'html5-qrcode'
import { useEffect, useRef, useState } from 'react'
import Alert from '@/components/ui/Alert'
import Modal from '@/components/ui/Modal'

const READER_ID = 'qr-reader'

function cameraErrorMessage(error) {
  const text = String(error?.name ?? error)
  if (text.includes('NotAllowed')) {
    return 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser, atau pindai QR dengan aplikasi kamera HP.'
  }
  if (text.includes('NotFound') || text.includes('OverconstrainedError')) {
    return 'Kamera tidak ditemukan di perangkat ini.'
  }
  return 'Kamera tidak dapat dibuka. Coba lagi, atau pindai QR dengan aplikasi kamera HP.'
}

/**
 * Pemindai QR memakai kamera belakang HP. Memanggil onResult(teks) sekali saat QR terbaca.
 * Browser hanya mengizinkan kamera pada halaman HTTPS (atau localhost).
 */
export default function QrScanner({ onResult, onClose }) {
  const secure = window.isSecureContext
  const [error, setError] = useState(null)
  const onResultRef = useRef(onResult)

  useEffect(() => {
    onResultRef.current = onResult
  })

  useEffect(() => {
    if (!secure) return undefined

    let cancelled = false
    let scanner = null
    let started = null

    // Ditunda sebentar agar mount ganda React (StrictMode) tidak membuka kamera dua kali.
    const timer = setTimeout(() => {
      scanner = new Html5Qrcode(READER_ID, { verbose: false })
      started = scanner
        .start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 },
          (text) => {
            if (cancelled) return
            cancelled = true
            scanner
              .stop()
              .catch(() => {})
              .finally(() => onResultRef.current(text))
          },
          () => {}, // frame tanpa QR — abaikan
        )
        .catch((err) => {
          if (!cancelled) setError(cameraErrorMessage(err))
        })
    }, 60)

    return () => {
      cancelled = true
      clearTimeout(timer)
      started?.then(() => (scanner.isScanning ? scanner.stop() : undefined)).catch(() => {})
    }
  }, [secure])

  return (
    <Modal open onClose={onClose} title="Scan QR presensi" description="Arahkan kamera ke QR Code di layar ruang logistik.">
      {secure ? (
        <div className="space-y-3">
          <div id={READER_ID} className="aspect-square w-full overflow-hidden rounded-xl bg-slate-900" />
          {error && <Alert variant="error">{error}</Alert>}
        </div>
      ) : (
        <Alert variant="warning" title="Kamera tidak bisa dibuka dari halaman ini">
          Browser hanya mengizinkan kamera pada halaman HTTPS. Buka aplikasi <strong>kamera HP</strong> lalu arahkan ke
          QR di layar — halaman presensi akan terbuka otomatis.
        </Alert>
      )}
    </Modal>
  )
}
