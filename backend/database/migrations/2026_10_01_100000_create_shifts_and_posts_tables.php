<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shift (jam kerja untuk presensi) dan pos jaga, keduanya diatur admin.
     * Slot jadwal kini = tanggal × shift × pos; kebutuhan staff slot = kapasitas pos.
     *
     * Nama shift & pos ikut disalin ke slot agar jadwal lama tetap terbaca
     * walau shift/pos diubah atau dihapus kemudian. Data awalnya diisi oleh ShiftPostSeeder.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedTinyInteger('capacity')->default(2); // maksimal 2 orang per pos
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('shift_slots', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('schedule_period_id')->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->after('shift_id')->constrained()->nullOnDelete();
            $table->string('shift_name', 50)->nullable()->after('post_id');
            $table->string('post_name', 50)->nullable()->after('shift_name');

            // Indeks baru dibuat lebih dulu karena foreign key schedule_period_id memakainya.
            $table->index(['schedule_period_id', 'date', 'start_time', 'post_id'], 'shift_slots_period_date_time_post_index');
            $table->dropUnique(['schedule_period_id', 'date', 'start_time']);
        });

    }

    public function down(): void
    {
        Schema::table('shift_slots', function (Blueprint $table) {
            $table->unique(['schedule_period_id', 'date', 'start_time']);
            $table->dropIndex('shift_slots_period_date_time_post_index');
            $table->dropConstrainedForeignId('post_id');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['shift_name', 'post_name']);
        });

        Schema::dropIfExists('posts');
        Schema::dropIfExists('shifts');
    }
};
