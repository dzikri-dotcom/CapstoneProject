import Badge from '@/components/ui/Badge'

export default function PeriodStatusBadge({ status }) {
  return status === 'published' ? <Badge color="green">Dipublikasikan</Badge> : <Badge color="amber">Draft</Badge>
}
