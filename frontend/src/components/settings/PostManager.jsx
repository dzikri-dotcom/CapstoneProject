import { MapPin } from 'lucide-react'
import Input from '@/components/ui/Input'
import Select from '@/components/ui/Select'
import { postApi } from '@/services/shiftPostService'
import ManagedList from './ManagedList'

const EMPTY = { name: '', location: '', capacity: '2', is_active: true }

const CAPACITY_OPTIONS = [
  { value: '1', label: '1 orang' },
  { value: '2', label: '2 orang (maksimal)' },
]

/** Kelola pos jaga: setiap shift dijaga di semua pos aktif, maksimal 2 orang per pos. */
export default function PostManager({ posts, onChange }) {
  const activeCapacity = posts.filter((post) => post.is_active).reduce((sum, post) => sum + post.capacity, 0)

  return (
    <ManagedList
      title="Pos jaga"
      description="Setiap shift dijaga di semua pos yang aktif, maksimal 2 orang per pos."
      icon={MapPin}
      noun="pos"
      items={posts}
      onChange={onChange}
      api={postApi}
      emptyForm={EMPTY}
      toForm={({ name, location, capacity, is_active }) => ({ name, location: location ?? '', capacity: String(capacity), is_active })}
      toPayload={(form) => ({ ...form, location: form.location.trim() || null, capacity: Number(form.capacity) })}
      renderSummary={(post) => [post.location, `${post.capacity} orang per shift`].filter(Boolean).join(' · ')}
      footnote={`Kebutuhan staff per shift saat ini: ${activeCapacity} orang. Pos dengan lokasi yang sama dikelompokkan di tabel jadwal.`}
      renderFields={({ form, set, errors }) => (
        <>
          <Input
            label="Nama pos"
            placeholder="Misal: Pos 1"
            value={form.name}
            onChange={(event) => set('name')(event.target.value)}
            error={errors.name}
            required
            autoFocus
          />
          <Input
            label="Lokasi"
            placeholder="Misal: Gedung A"
            value={form.location}
            onChange={(event) => set('location')(event.target.value)}
            error={errors.location}
            hint="Opsional. Dipakai untuk mengelompokkan pos di tabel jadwal."
          />
          <Select
            label="Jumlah penjaga"
            options={CAPACITY_OPTIONS}
            value={form.capacity}
            onChange={(event) => set('capacity')(event.target.value)}
            error={errors.capacity}
            required
          />
        </>
      )}
    />
  )
}
