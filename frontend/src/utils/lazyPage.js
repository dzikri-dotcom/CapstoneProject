import { lazy } from 'react'

const RELOAD_KEY = 'page-chunk-reload-at'

/**
 * React.lazy untuk halaman, dengan pemulihan otomatis saat file halaman gagal diunduh.
 *
 * Penyebab paling umum: aplikasi baru saja di-deploy ulang (nama file lama sudah tidak ada) atau
 * server dev Vite baru mengoptimasi ulang dependensi. Halaman dimuat ulang SEKALI agar mendapat
 * versi terbaru; jika masih gagal dalam 10 detik, error diteruskan ke ErrorBoundary.
 */
export default function lazyPage(load) {
  return lazy(() =>
    load().catch((error) => {
      let lastReload = 0
      try {
        lastReload = Number(sessionStorage.getItem(RELOAD_KEY)) || 0
      } catch {
        // Penyimpanan diblokir: anggap belum pernah dimuat ulang.
      }

      if (Date.now() - lastReload > 10_000) {
        try {
          sessionStorage.setItem(RELOAD_KEY, String(Date.now()))
        } catch {
          // Abaikan; paling buruk halaman dimuat ulang sekali lagi.
        }
        window.location.reload()
        return new Promise(() => {}) // tunggu sampai halaman dimuat ulang
      }

      throw error
    }),
  )
}
