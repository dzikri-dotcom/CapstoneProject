<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slot shift konkret per tanggal, misal Senin 29-09-2026 08:00–10:00.
     */
    public function up(): void
    {
        Schema::create('shift_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('required_staff');
            $table->timestamps();

            $table->unique(['schedule_period_id', 'date', 'start_time']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_slots');
    }
};
