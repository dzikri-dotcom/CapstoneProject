import { useCallback, useEffect, useState } from 'react'

/**
 * Ambil data dari API dan kelola status loading/error.
 * Data lama tetap ditampilkan selama memuat ulang (misal saat ganti halaman),
 * sehingga tabel tidak "berkedip".
 *
 * const { data, loading, error, reload } = useFetch(() => listStaff(params), [params])
 */
export default function useFetch(fetcher, deps = []) {
  const [version, setVersion] = useState(0)
  const key = JSON.stringify([...deps, version])
  const [state, setState] = useState({ key: null, data: undefined, error: null })

  useEffect(() => {
    let active = true
    fetcher().then(
      (data) => active && setState({ key, data, error: null }),
      (error) => active && setState((previous) => ({ key, data: previous.data, error })),
    )
    return () => {
      active = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- `key` sudah mewakili deps
  }, [key])

  const reload = useCallback(() => setVersion((value) => value + 1), [])

  return {
    data: state.data,
    error: state.key === key ? state.error : null,
    loading: state.key !== key,
    reload,
  }
}
