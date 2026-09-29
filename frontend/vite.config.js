import tailwindcss from '@tailwindcss/vite'
import basicSsl from '@vitejs/plugin-basic-ssl'
import react from '@vitejs/plugin-react'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [
    react(),
    tailwindcss(),
    // Mode "lan" (npm run dev:lan): HTTPS dengan sertifikat lokal agar kamera HP bisa dipakai
    // untuk scan QR (browser hanya mengizinkan kamera di halaman HTTPS).
    mode === 'lan' && basicSsl(),
  ].filter(Boolean),
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  // Pemindai QR dimuat secara lazy; disiapkan sejak server dev menyala agar
  // pemuatan pertamanya tidak memicu reload halaman.
  optimizeDeps: {
    include: ['html5-qrcode'],
  },
  server: {
    // Port dikunci agar selalu cocok dengan FRONTEND_URL di CORS backend.
    port: 5173,
    strictPort: true,
    // Dipakai mode "lan" (VITE_API_URL=/api): request API diteruskan ke Laravel di laptop ini,
    // sehingga HP cukup mengakses satu alamat (https://IP-laptop:5173).
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
}))
