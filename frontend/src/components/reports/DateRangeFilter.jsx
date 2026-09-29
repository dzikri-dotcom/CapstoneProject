import fieldClass from '@/components/ui/fieldClass'
import cn from '@/utils/cn'
import { RANGE_PRESETS } from './reportRanges'

const PRESET_LABELS = [
  ['thisWeek', 'Minggu ini'],
  ['last4Weeks', '4 minggu terakhir'],
  ['thisMonth', 'Bulan ini'],
  ['lastMonth', 'Bulan lalu'],
]

/**
 * Pilihan rentang tanggal laporan: preset cepat + tanggal bebas, dalam satu baris.
 */
export default function DateRangeFilter({ value, onChange }) {
  const activePreset = PRESET_LABELS.find(([key]) => {
    const preset = RANGE_PRESETS[key]()
    return preset.start_date === value.start_date && preset.end_date === value.end_date
  })?.[0]

  return (
    <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
      <div className="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
        {PRESET_LABELS.map(([key, label]) => (
          <button
            key={key}
            type="button"
            onClick={() => onChange(RANGE_PRESETS[key]())}
            className={cn(
              'rounded-md px-3 py-1.5 text-sm font-medium transition',
              activePreset === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800',
            )}
          >
            {label}
          </button>
        ))}
      </div>
      <div className="flex items-center gap-2 text-sm text-slate-500">
        <div className="w-40">
          <input
            type="date"
            aria-label="Tanggal mulai"
            value={value.start_date}
            onChange={(event) => event.target.value && onChange({ ...value, start_date: event.target.value })}
            className={cn(fieldClass(false), 'py-2')}
          />
        </div>
        s.d.
        <div className="w-40">
          <input
            type="date"
            aria-label="Tanggal selesai"
            min={value.start_date}
            value={value.end_date}
            onChange={(event) => event.target.value && onChange({ ...value, end_date: event.target.value })}
            className={cn(fieldClass(false), 'py-2')}
          />
        </div>
      </div>
    </div>
  )
}
