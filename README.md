# Presensi Logistik — Telkom University Surabaya

Aplikasi web penjadwalan shift, presensi, dan penggajian student staff Unit Logistik Telkom University Surabaya, pengganti pengelolaan manual di Microsoft Excel.

> Tugas akhir: *Penjadwalan Shift Otomatis Menggunakan Algoritma Round Robin untuk Mengatasi Inefisiensi Pengelolaan Manual Microsoft Excel pada Presensi dan Penggajian Student Staff Logistik Telkom University Surabaya.*

## Fitur

| Admin (koordinator logistik) | Student staff |
|---|---|
| Kelola data & akun staff | Isi jadwal kuliah, lihat jam kosong |
| Pengaturan jam operasional, durasi shift, batas jam, toleransi, pembulatan | Lihat jadwal shift yang dipublikasikan |
| Tarif per slot jam dengan riwayat tanggal berlaku | Presensi dengan scan QR (check-in/check-out) |
| **Generate jadwal otomatis (Round Robin)**, edit manual, log algoritma, publish | Riwayat presensi |
| Layar QR dinamis & monitoring/koreksi presensi | Rekap & slip gaji PDF |
| Penggajian otomatis, finalisasi, ekspor Excel & slip PDF | Estimasi gaji bulan berjalan |
| Dashboard, laporan keadilan distribusi, rekap kehadiran | |

## Teknologi

- **Backend:** Laravel 13 (REST API), Laravel Sanctum (token), MySQL 8, `maatwebsite/excel`, `barryvdh/laravel-dompdf`
- **Frontend:** React 19 + Vite 8, React Router, Axios, Tailwind CSS 4, Recharts, `qrcode.react`, `html5-qrcode`

## Prasyarat

- PHP **8.3+** dengan ekstensi `pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`, `intl`, `bcmath`
- Composer 2
- Node.js **20.19+ atau 22.12+** dan npm
- MySQL 8 (misal lewat **Laragon**, dikelola dengan phpMyAdmin)

## Instalasi

### 1. Database

Buat dua database (lewat phpMyAdmin atau terminal MySQL):

```sql
CREATE DATABASE presensi_logistik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE presensi_logistik_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- khusus pengujian
```

### 2. Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Isi `.env` bawaan sudah mengarah ke MySQL Laragon (`root` tanpa password, database `presensi_logistik`, zona waktu `Asia/Jakarta`, `FRONTEND_URL=http://localhost:5173`). Ubah bila berbeda.

`migrate --seed` mengisi: 1 admin, 10 student staff dengan jadwal kuliah (sebagian sengaja bentrok), jam operasional Senin–Jumat 08.00–17.00, dua versi tarif per jam, dan pengaturan bawaan. Setelah itu jadwal bisa langsung di-generate.

**Opsional — data demo** (jadwal 2 minggu terakhir + presensi simulasi) agar penggajian dan laporan langsung terisi:

```bash
php artisan db:seed --class=DemoDataSeeder
```

### 3. Frontend (React)

```bash
cd frontend
npm install
cp .env.example .env          # berisi VITE_API_URL=http://localhost:8000/api
```

## Menjalankan

Buka dua terminal:

```bash
# Terminal 1 — API di http://localhost:8000
cd backend
php artisan serve

# Terminal 2 — aplikasi di http://localhost:5173
cd frontend
npm run dev
```

Opsional, terminal ketiga agar shift yang lewat tanpa check-in otomatis ditandai **tidak hadir** setiap 5 menit:

```bash
cd backend
php artisan schedule:work
```

(Tanpa scheduler pun, penandaan tetap dilakukan setiap kali halaman presensi, monitoring, dashboard, atau penggajian dibuka.)

### Akun demo

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@logistik.test` | `password` |
| Staff | `andi.pratama@logistik.test` (dan 9 staff lain, lihat menu Student Staff) | `password` |

Di mode pengembangan, halaman login menyediakan tombol **Isi akun Admin/Staff**.

## Mencoba alur utama

1. **Admin → Jadwal Shift → Buat periode → Generate jadwal.** Slot *Rabu 08–10* akan ditandai kurang staff karena 9 dari 10 staff kuliah. Lihat **Log algoritma** untuk proses langkah demi langkah (bisa diunduh CSV).
2. Edit manual bila perlu, lalu **Publikasikan**.
3. **Layar QR Presensi** ditampilkan di perangkat ruang logistik; staff membuka menu **Presensi** dan memindai QR saat shift-nya berlangsung.
4. **Penggajian → Buat periode gaji → Hitung gaji → Finalkan**, lalu ekspor Excel / unduh slip PDF.
5. **Laporan → Keadilan Distribusi** untuk rata-rata, selisih, standar deviasi, dan koefisien variasi jam shift per staff.

## Tes presensi dari HP (jaringan Wi-Fi yang sama)

Kamera browser HP hanya bisa dipakai lewat HTTPS, jadi tersedia mode khusus:

```bash
# Terminal 1
cd backend && php artisan serve

# Terminal 2
cd frontend && npm run dev:lan
```

`dev:lan` menjalankan Vite dengan HTTPS (sertifikat lokal) dan meneruskan `/api` ke Laravel. Buka alamat **Network** yang tercetak di terminal (misal `https://192.168.1.10:5173`) di laptop untuk Layar QR dan di HP untuk login staff. Saat muncul peringatan sertifikat, pilih *Advanced → Proceed*. Memindai QR dengan aplikasi kamera bawaan HP juga bisa — link presensi terbuka otomatis.

## Pengujian

```bash
cd backend
php artisan test
```

124 test (memakai database `presensi_logistik_test`, data utama tidak tersentuh), antara lain:

- `tests/Unit/RoundRobinSchedulerTest.php` — antrian melingkar, staff bentrok kuliah dilewati, batas jam per minggu, slot kurang staff, pointer berlanjut antar periode, distribusi merata, hasil deterministik
- `tests/Unit/PayrollCalculatorTest.php` — pembulatan (per menit / 30 menit, ke bawah / terdekat), tarif berbeda per slot jam, jam hadir dibatasi jam shift, tidak hadir / belum check-out
- `tests/Unit/AvailabilityServiceTest.php`, `SlotBuilderTest.php`, `DistributionStatsTest.php`
- `tests/Feature/*` — autentikasi, manajemen staff, jadwal kuliah, pengaturan, tarif, generate & edit jadwal, presensi QR, penggajian, laporan

Frontend: `npm run lint` dan `npm run build`.

## Letak kode inti (untuk laporan)

| Bagian | File |
|---|---|
| **Algoritma Round Robin** | `backend/app/Services/Scheduling/RoundRobinScheduler.php` |
| Pembentukan slot shift | `backend/app/Services/Scheduling/SlotBuilder.php` |
| Jam kosong (jam operasional − kuliah) | `backend/app/Services/Scheduling/AvailabilityService.php` |
| Generate & simpan hasil + log + pointer | `backend/app/Services/Scheduling/ScheduleGenerator.php` |
| Edit manual & peringatan | `backend/app/Services/Scheduling/ScheduleEditor.php` |
| Presensi & QR dinamis | `backend/app/Services/Attendance/` |
| **Perhitungan gaji** | `backend/app/Services/Payroll/PayrollCalculator.php` |
| Statistik keadilan distribusi | `backend/app/Services/Reports/DistributionStats.php` |
| Skema database | `backend/database/migrations/` |

`RoundRobinScheduler` dan `PayrollCalculator` tidak mengakses database, sehingga mudah diuji dan dijelaskan.

## Struktur folder

```
backend/    Laravel 13 — app/{Http,Models,Services,Enums,Exports}, database/{migrations,seeders}, tests/
frontend/   React + Vite — src/{pages,components,layouts,services,context,hooks,routes,utils}
```

## Mengatur ulang data

```bash
cd backend
php artisan migrate:fresh --seed            # kembali ke data awal
php artisan db:seed --class=DemoDataSeeder  # (opsional) tambah data demo
```

## Masalah umum

| Gejala | Solusi |
|---|---|
| Halaman "Tidak dapat terhubung ke server" | Pastikan `php artisan serve` berjalan dan `VITE_API_URL` di `frontend/.env` benar. |
| Error CORS di console browser | Samakan `FRONTEND_URL` di `backend/.env` dengan alamat frontend (boleh beberapa, dipisah koma), lalu restart `php artisan serve`. |
| `npm install` gagal `EPERM` | Folder berada di OneDrive yang sedang menyinkronkan — jeda sinkronisasi atau pindahkan project ke luar OneDrive. |
| Test gagal koneksi database | Buat database `presensi_logistik_test` (lihat langkah 1). |
| Kamera tidak terbuka di HP | Gunakan `npm run dev:lan` (HTTPS) atau pindai QR dengan aplikasi kamera bawaan HP. |
