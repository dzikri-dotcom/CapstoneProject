import fieldClass from '@/components/ui/fieldClass'
import SegmentedControl from '@/components/ui/SegmentedControl'
import { RANGE_PRESETS } from './reportRanges'

const PRESETS = [
  { value: 'thisWeek', label: 'Minggu ini' },
  { value: 'last4Weeks', label: '4 minggu terakhir' },
  { value: 'thisMonth', label: 'Bulan ini' },
  { value: 'lastMonth', label: 'Bulan lalu' },
]

/**
 * Pilihan rentang tanggal laporan: preset cepat + tanggal bebas, dalam satu baris.
 */
export default function DateRangeFilter({ value, onChange }) {
  const activePreset = PRESETS.find(({ value: key }) => {
    const preset = RANGE_PRESETS[key]()
    return preset.start_date === value.start_date && preset.end_date === value.end_date
  })?.value

  return (
    <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
      <SegmentedControl
        aria-label="Rentang cepat"
        options={PRESETS}
        value={activePreset}
        onChange={(key) => onChange(RANGE_PRESETS[key]())}
      />
      <div className="flex items-center gap-2 text-[13px] text-slate-500">
        <div className="w-40">
          <input
            type="date"
            aria-label="Tanggal mulai"
            value={value.start_date}
            onChange={(event) => event.target.value && onChange({ ...value, start_date: event.target.value })}
            className={fieldClass(false)}
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
            className={fieldClass(false)}
          />
        </div>
      </div>
    </div>
  )
}
