/**
 * Ikon logo Logistik Asset Management: rumah berlapis (bilah-bilah di sisi kiri) yang terbuka di depan.
 * Digambar ulang sebagai SVG agar tajam di semua ukuran. `inverted` untuk latar gelap.
 */
const PALETTE = {
  dark: { body: '#1c1917', slat: '#3a3532', inside: '#262321', wall: '#34302d', edge: '#4a4541' },
  light: { body: '#f5f5f4', slat: '#a8a29e', inside: '#d6d3d1', wall: '#e7e5e4', edge: '#ffffff' },
}

// Titik-titik muka depan (F) dan belakang (B = F + kedalaman), dalam satuan viewBox 60×64.
const F = { peak: [41.7, 12.5], rEave: [58.8, 26.1], rBottom: [57.9, 48.3], lBottom: [24.1, 63.6], lEave: [24.1, 33.5] }
const DEPTH = [-23.9, -12.5]
const shift = ([x, y], t = 1) => [+(x + DEPTH[0] * t).toFixed(2), +(y + DEPTH[1] * t).toFixed(2)]
const B = Object.fromEntries(Object.entries(F).map(([key, point]) => [key, shift(point)]))
const pts = (...points) => points.map(([x, y]) => `${x},${y}`).join(' ')
const SLATS = [1, 2, 3, 4, 5, 6, 7].map((k) => k / 8)

export default function BrandMark({ className, inverted = false, title }) {
  const c = PALETTE[inverted ? 'light' : 'dark']

  return (
    <svg viewBox="0 0 60 64" className={className} role={title ? 'img' : undefined} aria-hidden={title ? undefined : true}>
      {title && <title>{title}</title>}
      {/* Bagian dalam yang terlihat dari depan, dengan bukaan belakang dibiarkan tembus. */}
      <path
        fillRule="evenodd"
        fill={c.inside}
        d={`M${pts(F.peak, F.rEave, F.rBottom, F.lBottom, F.lEave)}Z M24.3,33.5 33.8,22 33.8,43.4 24.3,44.9Z`}
      />
      <polygon fill={c.wall} points={pts(F.rEave, F.rBottom, B.rBottom, B.rEave)} />
      {/* Atap kiri & dinding kiri berupa bilah-bilah. */}
      <polygon fill={c.body} points={pts(B.peak, F.peak, F.lEave, B.lEave)} />
      <polygon fill={c.body} points={pts(B.lEave, F.lEave, F.lBottom, B.lBottom)} />
      {SLATS.map((t) => (
        <polyline key={t} fill="none" stroke={c.slat} strokeWidth="0.55" points={pts(shift(F.peak, t), shift(F.lEave, t), shift(F.lBottom, t))} />
      ))}
      {/* Tepi muka depan. */}
      <polyline fill="none" stroke={c.edge} strokeWidth="0.6" strokeLinejoin="round" points={pts(F.peak, F.rEave, F.rBottom, F.lBottom, F.lEave, F.peak)} />
    </svg>
  )
}
