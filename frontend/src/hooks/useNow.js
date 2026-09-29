import { useEffect, useState } from 'react'

/** Waktu sekarang yang diperbarui berkala (untuk jam berjalan). */
export default function useNow(intervalMs = 1000) {
  const [now, setNow] = useState(() => new Date())

  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), intervalMs)
    return () => clearInterval(id)
  }, [intervalMs])

  return now
}
