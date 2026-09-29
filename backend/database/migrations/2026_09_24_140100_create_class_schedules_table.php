<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal kuliah mingguan staff (berulang setiap minggu).
     */
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Senin ... 7 = Minggu
            $table->time('start_time');
            $table->time('end_time');
            $table->string('course_name', 150)->nullable();
            $table->timestamps();

            $table->index(['staff_profile_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
