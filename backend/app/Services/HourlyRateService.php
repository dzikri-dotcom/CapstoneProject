<?php

namespace App\Services;

use App\Enums\PayrollStatus;
use App\Models\HourlyRate;
use App\Models\OperationalHour;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\Time;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tarif per slot jam dengan riwayat versi (effective_date).
 */
class HourlyRateService
{
    /**
     * Daftar slot per jam yang perlu diberi tarif, mengikuti jam operasional di Pengaturan:
     * dimulai TEPAT dari jam buka paling awal (misal 06.30 → 06.30–07.30, 07.30–08.30, …)
     * sampai jam tutup paling akhir; slot terakhir dipendekkan bila tidak genap satu jam.
     *
     * @return array<int, array{start_time:string, end_time:string}>
     */
    public function hourSlots(): array
    {
        $hours = OperationalHour::active()->get();
        if ($hours->isEmpty()) {
            return [];
        }

        $start = $hours->min(fn ($h) => Time::toMinutes($h->open_time));
        $end = $hours->max(fn ($h) => Time::toMinutes($h->close_time));

        $slots = [];
        for ($minute = $start; $minute < $end; $minute += 60) {
            $slots[] = ['start_time' => Time::format($minute), 'end_time' => Time::format(min($minute + 60, $end))];
        }

        return $slots;
    }

    /**
     * Tarif yang berlaku hari ini, dipetakan ke slot jam operasional.
     */
    public function current(): array
    {
        $rates = HourlyRate::effectiveOn(today()->toDateString())->get();

        // Tarif tiap slot = tarif yang berlaku pada jam mulai slot. Versi lama dengan batas jam
        // berbeda (misal 06.00–07.00) tetap terbaca setelah jam operasional diubah.
        $slots = array_map(fn (array $slot) => [
            ...$slot,
            'rate' => $rates->first(fn (HourlyRate $rate) => Time::toMinutes($rate->start_time) <= Time::toMinutes($slot['start_time'])
                && Time::toMinutes($slot['start_time']) < Time::toMinutes($rate->end_time))?->rate,
        ], $this->hourSlots());

        return [
            'effective_date' => $rates->first()?->effective_date->toDateString(),
            'slots' => $slots,
            'missing_count' => count(array_filter($slots, fn ($slot) => $slot['rate'] === null)),
            'locked_until' => $this->lockedUntil(),
        ];
    }

    /**
     * Semua versi tarif, terbaru di atas.
     */
    public function versions(): Collection
    {
        $today = today()->toDateString();
        $currentDate = HourlyRate::query()->where('effective_date', '<=', $today)->max('effective_date');

        return HourlyRate::query()
            ->with('creator:id,name')
            ->orderByDesc('effective_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (HourlyRate $rate) => $rate->effective_date->toDateString())
            ->map(fn (Collection $rates, string $date) => [
                'effective_date' => $date,
                'status' => match (true) {
                    $date > $today => 'upcoming',
                    $date === $currentDate => 'current',
                    default => 'past',
                },
                'created_by' => $rates->first()->creator?->name,
                'created_at' => $rates->max('created_at')?->toIso8601String(),
                'min_rate' => $rates->min('rate'),
                'max_rate' => $rates->max('rate'),
                'rates' => $rates->map(fn (HourlyRate $rate) => [
                    'start_time' => substr($rate->start_time, 0, 5),
                    'end_time' => substr($rate->end_time, 0, 5),
                    'rate' => $rate->rate,
                ])->values()->all(),
            ])
            ->values();
    }

    /**
     * Tanggal akhir periode gaji final terakhir. Tarif pada/sebelum tanggal ini
     * tidak boleh diubah karena gajinya sudah difinalkan.
     */
    public function lockedUntil(): ?string
    {
        $date = PayrollPeriod::query()->where('status', PayrollStatus::Final)->max('end_date');

        return $date ? substr((string) $date, 0, 10) : null;
    }

    /**
     * Simpan satu versi tarif. Jika versi dengan tanggal yang sama sudah ada, versi itu diganti.
     *
     * @param  array<int, array{start_time:string, end_time:string, rate:int}>  $rates
     */
    public function saveVersion(string $effectiveDate, array $rates, ?User $user): void
    {
        DB::transaction(function () use ($effectiveDate, $rates, $user) {
            HourlyRate::query()->where('effective_date', $effectiveDate)->delete();

            foreach ($rates as $rate) {
                HourlyRate::query()->create([
                    'effective_date' => $effectiveDate,
                    'start_time' => $rate['start_time'],
                    'end_time' => $rate['end_time'],
                    'rate' => $rate['rate'],
                    'created_by' => $user?->id,
                ]);
            }
        });
    }

    public function deleteVersion(string $effectiveDate): void
    {
        if (! HourlyRate::query()->where('effective_date', $effectiveDate)->exists()) {
            abort(404, 'Versi tarif tidak ditemukan.');
        }

        $lockedUntil = $this->lockedUntil();
        if ($lockedUntil && $effectiveDate <= $lockedUntil) {
            throw ValidationException::withMessages([
                'effective_date' => 'Versi tarif ini sudah dipakai pada periode gaji yang final sehingga tidak bisa dihapus.',
            ]);
        }

        if (HourlyRate::query()->distinct()->count('effective_date') <= 1) {
            throw ValidationException::withMessages([
                'effective_date' => 'Minimal harus ada satu versi tarif.',
            ]);
        }

        HourlyRate::query()->where('effective_date', $effectiveDate)->delete();
    }
}
