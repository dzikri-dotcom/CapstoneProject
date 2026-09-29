import { CalendarRange, LogIn, QrCode, Wallet } from 'lucide-react'
import { useState } from 'react'
import Logo from '@/components/Logo'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import useAuth from '@/hooks/useAuth'
import { getErrorMessage } from '@/services/api'

const features = [
  { icon: CalendarRange, text: 'Jadwal shift otomatis dengan algoritma Round Robin', tint: 'from-violet-500 to-fuchsia-500 shadow-violet-500/40' },
  { icon: QrCode, text: 'Presensi dengan QR Code dinamis, anti titip absen', tint: 'from-emerald-400 to-teal-500 shadow-emerald-500/40' },
  { icon: Wallet, text: 'Penggajian otomatis berdasarkan jam hadir & tarif per jam', tint: 'from-amber-400 to-orange-500 shadow-amber-500/40' },
]

// Akun hasil seeder, hanya ditampilkan saat mode pengembangan (npm run dev).
const demoAccounts = [
  { label: 'Admin', email: 'admin@logistik.test' },
  { label: 'Staff', email: 'andi.pratama@logistik.test' },
]

export default function Login() {
  const { login } = useAuth()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      // Setelah berhasil, GuestRoute otomatis mengarahkan ke halaman sesuai role.
      await login(form.email, form.password)
    } catch (err) {
      setError(getErrorMessage(err))
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-mesh grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      {/* Panel informasi (desktop) */}
      <aside className="bg-shell relative m-3 hidden overflow-hidden rounded-[28px] p-12 text-white shadow-2xl shadow-slate-900/30 lg:flex lg:flex-col lg:justify-between">
        <div className="absolute -top-32 -left-24 size-[28rem] animate-float rounded-full bg-rose-600/50 blur-3xl" aria-hidden="true" />
        <div className="absolute -right-24 bottom-0 size-96 animate-float rounded-full bg-orange-500/35 blur-3xl [animation-delay:-5s]" aria-hidden="true" />
        <div className="absolute top-1/3 left-1/2 size-72 animate-float rounded-full bg-violet-600/40 blur-3xl [animation-delay:-9s]" aria-hidden="true" />
        <div
          className="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(white_1px,transparent_1px),linear-gradient(90deg,white_1px,transparent_1px)] [background-size:44px_44px]"
          aria-hidden="true"
        />
        <div className="relative">
          <Logo inverted />
        </div>
        <div className="relative max-w-lg">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80 ring-1 ring-white/15">
            <span className="size-1.5 animate-pulse rounded-full bg-emerald-400" /> Sistem penjadwalan Round Robin
          </p>
          <h1 className="text-[2.6rem] leading-[1.15] font-extrabold tracking-tight">
            Kelola shift, presensi, dan gaji student staff{' '}
            <span className="bg-gradient-to-r from-rose-400 via-orange-300 to-amber-200 bg-clip-text text-transparent">tanpa Excel manual.</span>
          </h1>
          <ul className="stagger mt-10 space-y-3">
            {features.map(({ icon: Icon, text, tint }) => (
              <li key={text} className="flex items-center gap-4 rounded-2xl bg-white/[0.06] p-3.5 text-white/90 ring-1 ring-white/10 backdrop-blur-sm transition hover:translate-x-1 hover:bg-white/10">
                <span className={`flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg ${tint}`}>
                  <Icon className="size-5" />
                </span>
                {text}
              </li>
            ))}
          </ul>
        </div>
        <p className="relative text-sm text-white/50">Unit Logistik · Telkom University Surabaya</p>
      </aside>

      {/* Form login */}
      <main className="flex items-center justify-center px-4 py-12 sm:px-6">
        <div className="w-full max-w-sm animate-slide-up rounded-[28px] border border-white bg-white/85 p-7 shadow-lift ring-1 ring-slate-200/60 backdrop-blur sm:p-8">
          <div className="mb-8 lg:hidden">
            <Logo />
          </div>
          <h2 className="text-2xl font-extrabold tracking-tight text-slate-900">Masuk ke akun <span className="text-brand-gradient">Anda</span></h2>
          <p className="mt-1 text-sm text-slate-500">Gunakan email dan password yang diberikan koordinator logistik.</p>

          <form onSubmit={handleSubmit} className="mt-8 space-y-5">
            {error && <Alert variant="error">{error}</Alert>}
            <Input
              label="Email"
              type="email"
              autoComplete="email"
              placeholder="nama@logistik.test"
              value={form.email}
              onChange={update('email')}
              required
              autoFocus
            />
            <Input
              label="Password"
              type="password"
              autoComplete="current-password"
              placeholder="••••••••"
              value={form.password}
              onChange={update('password')}
              required
            />
            <Button type="submit" icon={LogIn} loading={submitting} className="w-full">
              Masuk
            </Button>
          </form>

          {import.meta.env.DEV && (
            <div className="mt-8 rounded-xl border border-dashed border-slate-300 p-4">
              <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Akun demo (mode pengembangan)</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {demoAccounts.map((account) => (
                  <Button
                    key={account.email}
                    variant="secondary"
                    size="sm"
                    onClick={() => setForm({ email: account.email, password: 'password' })}
                  >
                    Isi akun {account.label}
                  </Button>
                ))}
              </div>
              <p className="mt-2 text-xs text-slate-500">Password semua akun demo: password</p>
            </div>
          )}
        </div>
      </main>
    </div>
  )
}
