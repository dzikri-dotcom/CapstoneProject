import Badge from '@/components/ui/Badge'
import { formatDuration, formatRupiah, formatShortDate } from '@/utils/format'

function Segments({ item }) {
  if (!item.segments.length) return <span className="text-slate-400">—</span>
  return (
    <ul className="space-y-0.5">
      {item.segments.map((segment) => (
        <li key={segment.from} className="tabular-nums">
          {segment.from}–{segment.to}: {segment.minutes} mnt × {formatRupiah(segment.rate)}
        </li>
      ))}
    </ul>
  )
}

function Note({ note }) {
  if (!note) return null
  const color = note === 'Terlambat' ? 'amber' : note === 'Tidak hadir' ? 'red' : 'gray'
  return <Badge color={color}>{note}</Badge>
}

/**
 * Rincian gaji per tanggal: jam hadir, menit dibayar, pecahan per slot jam × tarif, dan nominal.
 */
export default function PayrollItemsTable({ items }) {
  const total = items.reduce((sum, item) => sum + item.amount, 0)

  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full text-left text-sm">
          <thead className="bg-slate-50 text-xs tracking-wide whitespace-nowrap text-slate-500 uppercase">
            <tr>
              <th className="px-3 py-2.5 font-medium">Tanggal</th>
              <th className="px-3 py-2.5 font-medium">Shift</th>
              <th className="px-3 py-2.5 font-medium">Masuk–Pulang</th>
              <th className="px-3 py-2.5 text-right font-medium">Dibayar</th>
              <th className="px-3 py-2.5 font-medium">Rincian (menit × tarif/jam)</th>
              <th className="px-3 py-2.5 text-right font-medium">Nominal</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {items.map((item) => (
              <tr key={item.id} className="align-top">
                <td className="px-3 py-2.5 whitespace-nowrap text-slate-700">{formatShortDate(item.date)}</td>
                <td className="px-3 py-2.5 whitespace-nowrap text-slate-600 tabular-nums">
                  {item.shift_start}–{item.shift_end}
                </td>
                <td className="px-3 py-2.5 text-slate-600 tabular-nums">
                  {item.check_in ?? '—'}–{item.check_out ?? '—'}
                  <div className="mt-1">
                    <Note note={item.note} />
                  </div>
                </td>
                <td className="px-3 py-2.5 text-right whitespace-nowrap text-slate-600 tabular-nums">
                  {item.paid_minutes} mnt
                  {item.paid_minutes !== item.actual_minutes && (
                    <span className="block text-xs text-slate-400">hadir {item.actual_minutes} mnt</span>
                  )}
                </td>
                <td className="px-3 py-2.5 text-xs text-slate-500">
                  <Segments item={item} />
                </td>
                <td className="px-3 py-2.5 text-right font-medium whitespace-nowrap text-slate-800 tabular-nums">
                  {formatRupiah(item.amount)}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="border-t-2 border-slate-200">
              <td colSpan={5} className="px-3 py-2.5 text-right font-semibold text-slate-700">
                Total
              </td>
              <td className="px-3 py-2.5 text-right font-semibold whitespace-nowrap text-slate-900 tabular-nums">
                {formatRupiah(total)}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <ul className="divide-y divide-slate-100 md:hidden">
        {items.map((item) => (
          <li key={item.id} className="space-y-1 py-3">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="font-medium text-slate-800">{formatShortDate(item.date)}</p>
                <p className="text-xs text-slate-500 tabular-nums">
                  Shift {item.shift_start}–{item.shift_end} · {item.check_in ?? '—'}–{item.check_out ?? '—'}
                </p>
              </div>
              <p className="font-semibold text-slate-900 tabular-nums">{formatRupiah(item.amount)}</p>
            </div>
            <p className="text-xs text-slate-500">Dibayar {formatDuration(item.paid_minutes)}</p>
            <div className="text-xs text-slate-500">
              <Segments item={item} />
            </div>
            <Note note={item.note} />
          </li>
        ))}
      </ul>
    </>
  )
}
