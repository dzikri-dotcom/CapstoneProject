<?php

namespace App\Services\Reports;

/**
 * Statistik pemerataan beban kerja (dipakai laporan keadilan distribusi jadwal).
 *
 *   rata-rata      μ  = Σx / n
 *   standar deviasi σ = √( Σ(x − μ)² / n )      (deviasi standar populasi)
 *   selisih          = max − min
 *   koefisien variasi = σ / μ × 100%          (σ relatif terhadap rata-rata)
 * Semakin kecil σ, selisih, dan koefisien variasi → distribusi semakin merata.
 */
final class DistributionStats
{
    /**
     * @param  list<int|float>  $values
     * @return array{count:int, total:float, mean:float, min:float, max:float, range:float, sd:float, cv:float}
     */
    public static function of(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['count' => 0, 'total' => 0.0, 'mean' => 0.0, 'min' => 0.0, 'max' => 0.0, 'range' => 0.0, 'sd' => 0.0, 'cv' => 0.0];
        }

        $total = (float) array_sum($values);
        $mean = $total / $n;
        $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $values)) / $n;
        $sd = sqrt($variance);

        return [
            'count' => $n,
            'total' => round($total, 2),
            'mean' => round($mean, 2),
            'min' => (float) min($values),
            'max' => (float) max($values),
            'range' => (float) (max($values) - min($values)),
            'sd' => round($sd, 2),
            'cv' => $mean > 0 ? round($sd / $mean * 100, 2) : 0.0,
        ];
    }
}
