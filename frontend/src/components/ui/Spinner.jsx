export default function Spinner({ className = 'size-5' }) {
  return (
    <span
      role="status"
      aria-label="Memuat"
      className={`inline-block animate-spin rounded-full border-2 border-current border-t-transparent ${className}`}
    />
  )
}
