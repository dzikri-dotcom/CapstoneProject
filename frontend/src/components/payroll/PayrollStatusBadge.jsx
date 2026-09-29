import Badge from '@/components/ui/Badge'

export default function PayrollStatusBadge({ status }) {
  return status === 'final' ? <Badge color="green">Final</Badge> : <Badge color="amber">Draft</Badge>
}
