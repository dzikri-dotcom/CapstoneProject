<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posisi pointer Round Robin terakhir (hanya 1 baris), agar antrian periode
     * berikutnya dilanjutkan dari staff setelah staff terakhir yang dicek.
     * Disimpan sebagai ID staff (bukan indeks) supaya tetap benar ketika
     * ada staff baru atau staff yang dinonaktifkan.
     */
    public function up(): void
    {
        Schema::create('round_robin_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('next_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('last_schedule_period_id')->nullable()->constrained('schedule_periods')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_robin_state');
    }
};
