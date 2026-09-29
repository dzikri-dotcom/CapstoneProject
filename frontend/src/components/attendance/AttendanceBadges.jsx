import Badge from '@/components/ui/Badge'

/**
 * Status presensi satu shift: hasil presensi (tepat waktu/terlambat/tidak hadir)
 * + keadaan saat ini (belum check-in, sedang bertugas, belum check-out) + tanda koreksi admin.
 */
export default function AttendanceBadges({ item }) {
  const { state, attendance } = item
  const badges = []

  if (state === 'absent' || state === 'missed') badges.push(['red', 'Tidak hadir'])
  else if (attendance?.status === 'late') badges.push(['amber', 'Terlambat'])
  else if (attendance?.status === 'on_time') badges.push(['green', 'Tepat waktu'])

  if (state === 'upcoming') badges.push(['gray', 'Belum dimulai'])
  if (state === 'open') badges.push(['blue', 'Belum check-in'])
  if (state === 'in_progress') badges.push(['blue', 'Sedang bertugas'])
  if (state === 'missing_checkout') badges.push(['amber', 'Belum check-out'])
  if (attendance?.is_corrected) badges.push(['gray', 'Dikoreksi admin'])

  return (
    <span className="inline-flex flex-wrap gap-1">
      {badges.map(([color, label]) => (
        <Badge key={label} color={color}>
          {label}
        </Badge>
      ))}
    </span>
  )
}
