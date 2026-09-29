<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jam operasional ruang logistik per hari; hari bisa diaktifkan/nonaktifkan.
     */
    public function up(): void
    {
        Schema::create('operational_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique(); // 1 = Senin ... 7 = Minggu
            $table->boolean('is_active')->default(true);
            $table->time('open_time');
            $table->time('close_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_hours');
    }
};
