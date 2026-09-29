<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presensi per penugasan shift (maksimal 1 baris per penugasan).
     * Waktu memakai dateTime (bukan timestamp) agar tersimpan apa adanya dalam WIB.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('check_in_method', 20)->nullable();  // qr | manual
            $table->string('check_out_method', 20)->nullable(); // qr | manual
            $table->string('status', 20)->index();              // on_time | late | absent
            $table->boolean('is_corrected')->default(false);
            $table->text('correction_note')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->index(['staff_profile_id', 'check_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
