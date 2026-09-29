<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'start_date', 'end_date', 'status', 'total_amount', 'settings_snapshot',
    'calculated_at', 'finalized_at', 'created_by', 'finalized_by',
])]
class PayrollPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PayrollStatus::class,
            'total_amount' => 'decimal:2',
            'settings_snapshot' => 'array',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinal(): bool
    {
        return $this->status === PayrollStatus::Final;
    }
}
