<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type'])]
class Setting extends Model
{
    /**
     * Nilai bawaan setiap pengaturan sistem (dipakai seeder & sebagai cadangan
     * jika baris di database belum ada). Semua bisa diubah admin di halaman Pengaturan.
     */
    public const DEFAULTS = [
        'shift_duration_minutes' => ['value' => 120, 'type' => 'integer'], // durasi 1 shift
        'staff_per_shift' => ['value' => 2, 'type' => 'integer'],          // kebutuhan staff per slot
        'max_weekly_hours' => ['value' => 12, 'type' => 'integer'],        // batas jam kerja per staff per minggu
        'late_tolerance_minutes' => ['value' => 10, 'type' => 'integer'],  // toleransi terlambat
        'checkin_early_minutes' => ['value' => 15, 'type' => 'integer'],   // check-in boleh sebelum shift
        'checkout_grace_minutes' => ['value' => 30, 'type' => 'integer'],  // batas check-out setelah shift
        'rounding_unit_minutes' => ['value' => 30, 'type' => 'integer'],   // satuan pembulatan (1/15/30/60)
        'rounding_mode' => ['value' => 'down', 'type' => 'string'],        // down (ke bawah) | nearest (terdekat)
        'qr_refresh_seconds' => ['value' => 30, 'type' => 'integer'],      // token QR berganti tiap N detik
    ];

    /**
     * Nilai yang sudah dikonversi sesuai kolom type.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }
}
