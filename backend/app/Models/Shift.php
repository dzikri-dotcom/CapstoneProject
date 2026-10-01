<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shift kerja (misal Shift 1 06:30–09:30). Menentukan jam check-in/check-out;
 * gaji tetap dihitung per jam hadir, bukan per shift.
 */
#[Fillable(['name', 'start_time', 'end_time', 'is_active'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
