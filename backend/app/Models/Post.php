<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pos jaga. Setiap shift dijaga di semua pos aktif, maksimal `capacity` orang per pos.
 */
#[Fillable(['name', 'location', 'capacity', 'is_active'])]
class Post extends Model
{
    /** Batas atas kapasitas satu pos. */
    public const MAX_CAPACITY = 2;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
