<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log langkah demi langkah algoritma Round Robin (bukti proses untuk laporan TA):
     * slot mana, staff siapa yang dicek, posisi pointer, hasil, dan alasan dilewati.
     */
    public function up(): void
    {
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_slot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step');
            $table->foreignId('staff_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('pointer_position')->nullable();
            $table->string('action', 20);            // assigned | skipped | understaffed
            $table->string('reason', 50)->nullable(); // alasan dilewati
            $table->string('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['schedule_period_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
