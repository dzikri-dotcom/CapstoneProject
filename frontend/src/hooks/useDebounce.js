import { useEffect, useState } from 'react'

/** Nilai yang baru berubah setelah pengguna berhenti mengetik selama `delay` ms. */
export default function useDebounce(value, delay = 400) {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}
