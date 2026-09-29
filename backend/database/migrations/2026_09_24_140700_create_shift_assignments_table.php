<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan staff ke slot shift.
     *
     * staff_profile_id memakai restrictOnDelete: staff yang sudah punya riwayat
     * jadwal tidak bisa dihapus (cukup dinonaktifkan) agar data presensi & gaji aman.
     */
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->string('source', 20)->default('auto'); // auto | manual
            $table->timestamps();

            $table->unique(['shift_slot_id', 'staff_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
