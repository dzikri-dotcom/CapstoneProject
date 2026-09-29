/**
 * Tooltip Recharts bergaya aplikasi. `rows(payload)` mengembalikan [[label, nilai, warna?], ...].
 */
export default function ChartTooltip({ active, payload, title, rows }) {
  if (!active || !payload?.length) return null
  const datum = payload[0].payload

  return (
    <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg">
      <p className="mb-1 font-semibold text-slate-800">{title(datum)}</p>
      <dl className="space-y-0.5">
        {rows(datum).map(([label, value, color]) => (
          <div key={label} className="flex items-center gap-2">
            {color && <span className="size-2.5 rounded-sm" style={{ background: color }} />}
            <dt className="text-slate-500">{label}</dt>
            <dd className="ml-auto pl-3 font-medium text-slate-800 tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}
