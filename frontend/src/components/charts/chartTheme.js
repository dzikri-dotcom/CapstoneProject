/**
 * Warna grafik. Tiga warna kehadiran (biru–oranye–merah) sudah divalidasi aman untuk
 * buta warna (CVD ΔE ≥ 8). Oranye kontrasnya < 3:1, sehingga setiap grafik selalu
 * disertai label/legenda dan tabel angka.
 */
export const CHART = {
  primary: '#2a78d6',
  onTime: '#2a78d6',
  late: '#eb8c00',
  absent: '#d03b3b',
  grid: '#e2e8f0',
  axis: '#64748b',
  reference: '#475569',
  surface: '#ffffff',
}

export const axisProps = {
  stroke: CHART.grid,
  tick: { fill: CHART.axis, fontSize: 12 },
  tickLine: false,
}
