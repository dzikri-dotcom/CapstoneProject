import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router'
import LoadingScreen from '@/components/ui/LoadingScreen'
import useAuth from '@/hooks/useAuth'
import AdminLayout from '@/layouts/AdminLayout'
import StaffLayout from '@/layouts/StaffLayout'
import Login from '@/pages/auth/Login'
import NotFound from '@/pages/NotFound'
import { ROLE_HOME } from '@/utils/constants'

// Halaman dimuat saat dibuka (code splitting), sehingga HP staff tidak perlu mengunduh halaman admin.
const AdminDashboard = lazy(() => import('@/pages/admin/Dashboard'))
const AttendanceMonitor = lazy(() => import('@/pages/admin/AttendanceMonitor'))
const HourlyRates = lazy(() => import('@/pages/admin/HourlyRates'))
const PayrollDetail = lazy(() => import('@/pages/admin/payroll/PayrollDetail'))
const PayrollPeriods = lazy(() => import('@/pages/admin/payroll/PayrollPeriods'))
const AttendanceReport = lazy(() => import('@/pages/admin/reports/AttendanceReport'))
const FairnessReport = lazy(() => import('@/pages/admin/reports/FairnessReport'))
const AdminProfile = lazy(() => import('@/pages/admin/Profile'))
const QrDisplay = lazy(() => import('@/pages/admin/QrDisplay'))
const ScheduleDetail = lazy(() => import('@/pages/admin/schedule/ScheduleDetail'))
const SchedulePeriods = lazy(() => import('@/pages/admin/schedule/SchedulePeriods'))
const SchedulerLog = lazy(() => import('@/pages/admin/schedule/SchedulerLog'))
const Settings = lazy(() => import('@/pages/admin/Settings'))
const StaffDetail = lazy(() => import('@/pages/admin/staff/StaffDetail'))
const StaffList = lazy(() => import('@/pages/admin/staff/StaffList'))
const StaffAccount = lazy(() => import('@/pages/staff/Account'))
const StaffAttendance = lazy(() => import('@/pages/staff/Attendance'))
const AttendanceHistory = lazy(() => import('@/pages/staff/AttendanceHistory'))
const ClassSchedules = lazy(() => import('@/pages/staff/ClassSchedules'))
const StaffHome = lazy(() => import('@/pages/staff/Home'))
const StaffPayroll = lazy(() => import('@/pages/staff/Payroll'))
const StaffShifts = lazy(() => import('@/pages/staff/Shifts'))
import GuestRoute from './GuestRoute'
import ProtectedRoute from './ProtectedRoute'

function RoleHomeRedirect() {
  const { user } = useAuth()
  return <Navigate to={user ? ROLE_HOME[user.role] : '/login'} replace />
}

export default function AppRouter() {
  const { status, retry } = useAuth()

  if (status === 'loading') return <LoadingScreen />
  if (status === 'error') {
    return <LoadingScreen error="Pastikan backend Laravel sudah berjalan, lalu coba lagi." onRetry={retry} />
  }

  return (
    <BrowserRouter>
      <Suspense fallback={<LoadingScreen />}>
        <Routes>
        <Route path="/" element={<RoleHomeRedirect />} />

        <Route element={<GuestRoute />}>
          <Route path="/login" element={<Login />} />
        </Route>

        {/* Admin (koordinator logistik) */}
        <Route element={<ProtectedRoute role="admin" />}>
          {/* Layar QR tampil penuh tanpa sidebar (perangkat di ruang logistik). */}
          <Route path="/admin/qr" element={<QrDisplay />} />
          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<AdminDashboard />} />
            <Route path="staff" element={<StaffList />} />
            <Route path="staff/:id" element={<StaffDetail />} />
            <Route path="jadwal" element={<SchedulePeriods />} />
            <Route path="jadwal/:id" element={<ScheduleDetail />} />
            <Route path="jadwal/:id/log" element={<SchedulerLog />} />
            <Route path="presensi" element={<AttendanceMonitor />} />
            <Route path="penggajian" element={<PayrollPeriods />} />
            <Route path="penggajian/:id" element={<PayrollDetail />} />
            <Route path="laporan/keadilan" element={<FairnessReport />} />
            <Route path="laporan/kehadiran" element={<AttendanceReport />} />
            <Route path="tarif" element={<HourlyRates />} />
            <Route path="pengaturan" element={<Settings />} />
            <Route path="profil" element={<AdminProfile />} />
            <Route path="*" element={<NotFound embedded />} />
          </Route>
        </Route>

        {/* Student staff */}
        <Route element={<ProtectedRoute role="staff" />}>
          <Route path="/staff" element={<StaffLayout />}>
            <Route index element={<StaffHome />} />
            <Route path="jadwal-kuliah" element={<ClassSchedules />} />
            <Route path="jadwal-shift" element={<StaffShifts />} />
            <Route path="presensi" element={<StaffAttendance />} />
            <Route path="riwayat" element={<AttendanceHistory />} />
            <Route path="gaji" element={<StaffPayroll />} />
            <Route path="profil" element={<StaffAccount />} />
            <Route path="*" element={<NotFound embedded />} />
          </Route>
        </Route>

        <Route path="*" element={<NotFound />} />
        </Routes>
      </Suspense>
    </BrowserRouter>
  )
}
