import { BookOpen, ChevronRight, History, LogOut } from 'lucide-react'
import { Link, useNavigate } from 'react-router'
import ChangePasswordForm from '@/components/auth/ChangePasswordForm'
import Avatar from '@/components/ui/Avatar'
import Button from '@/components/ui/Button'
import Card from '@/components/ui/Card'
import useAuth from '@/hooks/useAuth'

const links = [
  { to: '/staff/jadwal-kuliah', label: 'Jadwal kuliah saya', icon: BookOpen },
  { to: '/staff/riwayat', label: 'Riwayat presensi', icon: History },
]

export default function Account() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const profile = user.staff_profile

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-4">
        <Avatar name={user.name} size="lg" />
        <div className="min-w-0">
          <h1 className="text-lg font-semibold text-slate-900">{user.name}</h1>
          <p className="truncate text-sm text-slate-500">{user.email}</p>
        </div>
      </div>

      <Card title="Data diri" description="Hubungi koordinator logistik jika ada data yang salah.">
        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
          <dt className="text-slate-500">NIM</dt>
          <dd className="font-medium text-slate-800">{profile?.nim}</dd>
          <dt className="text-slate-500">Program studi</dt>
          <dd className="font-medium text-slate-800">{profile?.study_program}</dd>
          <dt className="text-slate-500">No. HP</dt>
          <dd className="font-medium text-slate-800">{profile?.phone ?? '-'}</dd>
        </dl>
      </Card>

      <Card bodyClassName="p-2">
        <ul>
          {links.map(({ to, label, icon: Icon }) => (
            <li key={to}>
              <Link to={to} className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-700 hover:bg-slate-50">
                <Icon className="size-5 text-slate-400" />
                <span className="flex-1 font-medium">{label}</span>
                <ChevronRight className="size-4 text-slate-300" />
              </Link>
            </li>
          ))}
        </ul>
      </Card>

      <Card title="Ganti password">
        <ChangePasswordForm />
      </Card>

      <Button variant="secondary" icon={LogOut} className="w-full" onClick={handleLogout}>
        Keluar
      </Button>
    </div>
  )
}
