import {
  BadgeDollarSign,
  CalendarRange,
  ChartColumn,
  ClipboardCheck,
  FileSpreadsheet,
  LayoutDashboard,
  QrCode,
  Settings,
  Users,
  Wallet,
} from 'lucide-react'

/** Warna ikon tiap kelompok menu (kelas statis agar terbaca Tailwind). */
export const menuTints = {
  rose: 'text-rose-400 bg-rose-500/15',
  sky: 'text-sky-400 bg-sky-500/15',
  violet: 'text-violet-400 bg-violet-500/15',
  emerald: 'text-emerald-400 bg-emerald-500/15',
  amber: 'text-amber-400 bg-amber-500/15',
  pink: 'text-pink-400 bg-pink-500/15',
  slate: 'text-slate-300 bg-slate-500/20',
}

export const adminMenu = [
  {
    label: 'Utama',
    tint: 'rose',
    items: [{ to: '/admin', label: 'Dashboard', icon: LayoutDashboard, end: true }],
  },
  {
    label: 'Data',
    tint: 'sky',
    items: [{ to: '/admin/staff', label: 'Student Staff', icon: Users }],
  },
  {
    label: 'Penjadwalan',
    tint: 'violet',
    items: [{ to: '/admin/jadwal', label: 'Jadwal Shift', icon: CalendarRange }],
  },
  {
    label: 'Presensi',
    tint: 'emerald',
    items: [
      { to: '/admin/presensi', label: 'Monitoring Presensi', icon: ClipboardCheck },
      { to: '/admin/qr', label: 'Layar QR Presensi', icon: QrCode },
    ],
  },
  {
    label: 'Penggajian',
    tint: 'amber',
    items: [
      { to: '/admin/penggajian', label: 'Penggajian', icon: Wallet },
      { to: '/admin/tarif', label: 'Tarif per Jam', icon: BadgeDollarSign },
    ],
  },
  {
    label: 'Laporan',
    tint: 'pink',
    items: [
      { to: '/admin/laporan/keadilan', label: 'Keadilan Distribusi', icon: ChartColumn },
      { to: '/admin/laporan/kehadiran', label: 'Rekap Kehadiran', icon: FileSpreadsheet },
    ],
  },
  {
    label: 'Sistem',
    tint: 'slate',
    items: [{ to: '/admin/pengaturan', label: 'Pengaturan', icon: Settings }],
  },
]
