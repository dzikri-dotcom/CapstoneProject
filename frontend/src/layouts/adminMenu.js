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

export const adminMenu = [
  {
    label: 'Utama',
    items: [{ to: '/admin', label: 'Dashboard', icon: LayoutDashboard, end: true }],
  },
  {
    label: 'Data',
    items: [{ to: '/admin/staff', label: 'Student Staff', icon: Users }],
  },
  {
    label: 'Penjadwalan',
    items: [{ to: '/admin/jadwal', label: 'Jadwal Shift', icon: CalendarRange }],
  },
  {
    label: 'Presensi',
    items: [
      { to: '/admin/presensi', label: 'Monitoring Presensi', icon: ClipboardCheck },
      { to: '/admin/qr', label: 'Layar QR Presensi', icon: QrCode },
    ],
  },
  {
    label: 'Penggajian',
    items: [
      { to: '/admin/penggajian', label: 'Penggajian', icon: Wallet },
      { to: '/admin/tarif', label: 'Tarif per Jam', icon: BadgeDollarSign },
    ],
  },
  {
    label: 'Laporan',
    items: [
      { to: '/admin/laporan/keadilan', label: 'Keadilan Distribusi', icon: ChartColumn },
      { to: '/admin/laporan/kehadiran', label: 'Rekap Kehadiran', icon: FileSpreadsheet },
    ],
  },
  {
    label: 'Sistem',
    items: [{ to: '/admin/pengaturan', label: 'Pengaturan', icon: Settings }],
  },
]
