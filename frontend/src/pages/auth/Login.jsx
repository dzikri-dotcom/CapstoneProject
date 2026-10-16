import { CalendarRange, QrCode, Wallet } from 'lucide-react'
import { useState } from 'react'
import Logo from '@/components/Logo'
import Alert from '@/components/ui/Alert'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import useAuth from '@/hooks/useAuth'
import { getErrorMessage } from '@/services/api'
import cn from '@/utils/cn'

const features = [
  { icon: CalendarRange, title: 'Jadwal otomatis', text: 'Round Robin bergilir adil, menghindari bentrok jam kuliah.' },
  { icon: QrCode, title: 'Presensi QR dinamis', text: 'Kode berganti tiap beberapa detik, tidak bisa dititipkan.' },
  { icon: Wallet, title: 'Gaji terhitung sendiri', text: 'Dari jam hadir × tarif per jam, siap diekspor.' },
]

// Ilustrasi pratinjau jadwal: giliran staff berputar dari slot ke slot.
const PREVIEW_DAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum']
const PREVIEW_ROWS = [
  ['06.30', ['BI', 'DZ'], ['TA', 'HA'], ['FI', 'RA'], ['KH', 'AN'], ['RU', 'DI']],
  ['09.30', ['TA', 'HA'], ['FI', 'RA'], ['KH', 'AN'], ['RU', 'DI'], ['BI', 'DZ']],
  ['11.30', ['FI', 'RA'], ['KH', null], ['RU', 'DI'], ['BI', 'DZ'], ['TA', 'HA']],
]
const CHIP_TONES = {
  BI: 'bg-rose-100 text-rose-700',
  DZ: 'bg-sky-100 text-sky-700',
  TA: 'bg-amber-100 text-amber-800',
  HA: 'bg-emerald-100 text-emerald-700',
  FI: 'bg-indigo-100 text-indigo-700',
  RA: 'bg-teal-100 text-teal-700',
  KH: 'bg-orange-100 text-orange-700',
  AN: 'bg-slate-200 text-slate-700',
  RU: 'bg-rose-100 text-rose-700',
  DI: 'bg-sky-100 text-sky-700',
}

function SchedulePreview() {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-pop" aria-hidden="true">
      <div className="mb-3 flex items-center justify-between">
        <p className="text-[13px] font-semibold text-slate-900">Jadwal minggu ini</p>
        <span className="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-600/15">
          Dipublikasikan
        </span>
      </div>
      <div className="grid grid-cols-[40px_repeat(5,1fr)] gap-1.5 text-[11px]">
        <span />
        {PREVIEW_DAYS.map((day) => (
          <span key={day} className="pb-0.5 font-medium text-slate-400">
            {day}
          </span>
        ))}
        {PREVIEW_ROWS.map(([time, ...cells]) => (
          <div key={time} className="contents">
            <span className="pt-1.5 text-slate-400 tabular-nums">{time}</span>
            {cells.map((pair, index) => (
              <div key={index} className="space-y-1 rounded-md border border-slate-100 bg-slate-50/60 p-1">
                {pair.map((code, slot) =>
                  code ? (
                    <span key={slot} className={cn('block rounded px-1 py-0.5 text-center font-semibold', CHIP_TONES[code])}>
                      {code}
                    </span>
                  ) : (
                    <span key={slot} className="block rounded border border-dashed border-red-300 px-1 py-0.5 text-center text-red-500">
                      +1
                    </span>
                  ),
                )}
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  )
}

// Akun hasil seeder, hanya ditampilkan saat mode pengembangan (npm run dev).
const demoAccounts = [
  { label: 'Admin', email: 'admin@logistik.test' },
  { label: 'Staff', email: 'bintang@logistik.test' },
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
    <div className="grid min-h-screen bg-white lg:grid-cols-2">
      {/* Form login */}
      <main className="flex flex-col px-6 py-8 sm:px-10">
        <Logo />
        <div className="flex flex-1 items-center justify-center py-10">
          <div className="w-full max-w-sm animate-rise">
            <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Masuk ke akun Anda</h1>
            <p className="mt-1.5 text-sm text-slate-500">Gunakan email dan password yang diberikan koordinator logistik.</p>

            <form onSubmit={handleSubmit} className="mt-8 space-y-4">
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
              <Button type="submit" loading={submitting} className="w-full">
                Masuk
              </Button>
            </form>

            {import.meta.env.DEV && (
              <div className="mt-8 border-t border-slate-100 pt-6">
                <p className="text-xs font-medium text-slate-500">Akun demo (mode pengembangan) · password: password</p>
                <div className="mt-2.5 flex flex-wrap gap-2">
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
              </div>
            )}
          </div>
        </div>
        <p className="text-xs text-slate-400">Unit Logistik · Telkom University Surabaya</p>
      </main>

      {/* Panel informasi (desktop) */}
      <aside className="hidden border-l border-slate-200/80 bg-[#f4f4f5] lg:flex lg:items-center lg:justify-center lg:p-12">
        <div className="w-full max-w-md">
          <p className="text-[13px] font-medium text-brand-700">Sistem Presensi & Penjadwalan Shift</p>
          <h2 className="mt-2 text-3xl leading-tight font-semibold tracking-tight text-balance text-slate-900">
            Shift yang adil, presensi yang jujur, gaji yang tepat.
          </h2>

          <div className="mt-8">
            <SchedulePreview />
          </div>

          <ul className="mt-8 space-y-4">
            {features.map(({ icon: Icon, title, text }) => (
              <li key={title} className="flex gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-card">
                  <Icon className="size-4" />
                </span>
                <div>
                  <p className="text-sm font-medium text-slate-900">{title}</p>
                  <p className="text-[13px] text-slate-500">{text}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </aside>
    </div>
  )
}
