<?php

namespace App\Http\Resources;

use App\Enums\DayOfWeek;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rincian gaji satu shift (per tanggal).
 *
 * @mixin PayrollItem
 */
class PayrollItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'day_label' => DayOfWeek::from($this->date->dayOfWeekIso)->label(),
            'shift_start' => substr($this->shift_start, 0, 5),
            'shift_end' => substr($this->shift_end, 0, 5),
            'check_in' => $this->check_in_at?->format('H:i'),
            'check_out' => $this->check_out_at?->format('H:i'),
            'actual_minutes' => $this->actual_minutes,
            'paid_minutes' => $this->paid_minutes,
            'amount' => (float) $this->amount,
            'segments' => $this->breakdown['segments'] ?? [],
            'note' => $this->note(),
        ];
    }

    /** Keterangan singkat mengapa shift tidak/kurang dibayar. */
    private function note(): ?string
    {
        return match (true) {
            ! $this->check_in_at => 'Tidak hadir',
            ! $this->check_out_at => 'Belum check-out, tidak dibayar',
            ($this->breakdown['unpaid_minutes'] ?? 0) > 0 => 'Sebagian jam belum memiliki tarif',
            ($this->breakdown['status'] ?? null) === 'late' => 'Terlambat',
            default => null,
        };
    }
}
