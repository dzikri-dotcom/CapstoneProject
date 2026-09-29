<?php

namespace Tests\Unit;

use App\Services\Reports\DistributionStats;
use PHPUnit\Framework\TestCase;

class DistributionStatsTest extends TestCase
{
    public function test_statistik_pemerataan(): void
    {
        // Jam: 2, 4, 4, 4, 5, 5, 7, 9 → μ = 5, σ populasi = 2
        $stats = DistributionStats::of([2, 4, 4, 4, 5, 5, 7, 9]);

        $this->assertSame(8, $stats['count']);
        $this->assertSame(5.0, $stats['mean']);
        $this->assertSame(2.0, $stats['sd']);
        $this->assertSame(7.0, $stats['range']);
        $this->assertSame(40.0, $stats['cv']);
    }

    public function test_distribusi_merata_sempurna(): void
    {
        $stats = DistributionStats::of([600, 600, 600]);

        $this->assertSame(0.0, $stats['sd']);
        $this->assertSame(0.0, $stats['range']);
    }

    public function test_tanpa_data(): void
    {
        $this->assertSame(0, DistributionStats::of([])['count']);
    }
}
