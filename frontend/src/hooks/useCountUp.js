import { useEffect, useState } from 'react'

/**
 * Angka yang "menghitung naik" dengan halus saat nilainya muncul atau berubah.
 * Nilai non-angka dikembalikan apa adanya.
 */
export default function useCountUp(value, duration = 700) {
  const isNumber = typeof value === 'number' && Number.isFinite(value)
  const [display, setDisplay] = useState(isNumber ? 0 : value)

  useEffect(() => {
    if (!isNumber) return undefined
    let frame
    const start = performance.now()
    const tick = (now) => {
      const progress = Math.min((now - start) / duration, 1)
      const eased = 1 - (1 - progress) ** 3
      setDisplay(Math.round(value * eased))
      if (progress < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [value, isNumber, duration])

  return isNumber ? display : value
}
