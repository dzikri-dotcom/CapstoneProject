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
  // Halaman dimuat secara lazy, jadi Vite tidak melihat dependensinya saat start. Semua file sumber
  // dipindai sejak server dev menyala agar dependensi tidak dioptimasi ulang di tengah jalan
  // (yang membuat halaman lazy gagal dimuat: "Outdated Optimize Dep").
  optimizeDeps: {
    entries: ['index.html', 'src/**/*.{js,jsx}'],
    include: ['html5-qrcode', 'recharts', 'qrcode.react'],
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
