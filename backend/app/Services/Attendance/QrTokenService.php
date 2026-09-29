<?php

namespace App\Services\Attendance;

use App\Models\QrToken;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * QR Code dinamis anti titip absen.
 *
 * Layar QR di ruang logistik meminta token aktif; token berganti setiap
 * `qr_refresh_seconds` detik. Staff harus memindai QR yang sedang tampil,
 * sehingga foto/tangkapan layar QR lama tidak bisa dipakai untuk titip absen.
 */
class QrTokenService
{
    /** Token lama masih diterima sebentar setelah berganti, agar scan tepat saat pergantian tidak gagal. */
    public const GRACE_SECONDS = 10;

    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * Token yang sedang berlaku; buat baru jika sudah kedaluwarsa.
     * Beberapa layar QR yang dibuka bersamaan akan menampilkan token yang sama.
     */
    public function current(?User $user = null): QrToken
    {
        $active = QrToken::query()->where('expires_at', '>', now())->latest('expires_at')->first();
        if ($active) {
            return $active;
        }

        // Bersihkan token lama agar tabel tidak terus membesar.
        QrToken::query()->where('expires_at', '<', now()->subDay())->delete();

        return QrToken::query()->create([
            'token' => Str::random(40),
            'expires_at' => now()->addSeconds($this->settings->get('qr_refresh_seconds')),
            'created_by' => $user?->id,
        ]);
    }

    public function assertValid(?string $token): QrToken
    {
        $record = $token ? QrToken::query()->where('token', $token)->first() : null;

        if (! $record) {
            throw ValidationException::withMessages([
                'token' => 'Kode QR tidak dikenali. Scan QR yang tampil di layar ruang logistik.',
            ]);
        }

        if ($record->expires_at->copy()->addSeconds(self::GRACE_SECONDS)->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'Kode QR sudah kedaluwarsa. Scan ulang QR yang sedang tampil di layar.',
            ]);
        }

        return $record;
    }
}
