<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batas jam kerja per minggu khusus untuk satu staff.
     * NULL = mengikuti batas umum di Pengaturan (max_weekly_hours).
     */
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_weekly_hours')->nullable()->after('study_program');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('max_weekly_hours');
        });
    }
};
