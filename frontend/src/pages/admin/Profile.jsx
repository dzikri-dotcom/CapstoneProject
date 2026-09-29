import ChangePasswordForm from '@/components/auth/ChangePasswordForm'
import Avatar from '@/components/ui/Avatar'
import Card from '@/components/ui/Card'
import PageHeader from '@/components/ui/PageHeader'
import useAuth from '@/hooks/useAuth'

export default function Profile() {
  const { user } = useAuth()

  return (
    <>
      <PageHeader title="Akun saya" description="Informasi akun dan keamanan login." />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card>
          <div className="flex items-center gap-4">
            <Avatar name={user.name} size="lg" />
            <div className="min-w-0">
              <p className="font-semibold text-slate-900">{user.name}</p>
              <p className="truncate text-sm text-slate-500">{user.email}</p>
              <p className="mt-1 text-xs font-medium text-brand-700">{user.role_label}</p>
            </div>
          </div>
        </Card>

        <Card title="Ganti password" className="lg:col-span-2">
          <div className="max-w-md">
            <ChangePasswordForm />
          </div>
        </Card>
      </div>
    </>
  )
}
