<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['start_time', 'end_time', 'rate', 'effective_date', 'created_by'])]
class HourlyRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'effective_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Versi tarif yang berlaku pada tanggal tertentu:
     * baris dengan effective_date terbesar yang <= tanggal tersebut.
     */
    #[Scope]
    protected function effectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_date', '=', function ($sub) use ($date) {
            $sub->selectRaw('MAX(effective_date)')
                ->from('hourly_rates')
                ->where('effective_date', '<=', $date);
        })->orderBy('start_time');
    }
}
