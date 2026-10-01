<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi pos jaga (misal "Gedung A") untuk mengelompokkan pos di tabel jadwal.
     * Seperti nama pos, lokasinya ikut disalin ke slot agar jadwal lama tetap terbaca.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('location', 50)->nullable()->after('name');
        });

        Schema::table('shift_slots', function (Blueprint $table) {
            $table->string('post_location', 50)->nullable()->after('post_name');
        });
    }

    public function down(): void
    {
        Schema::table('shift_slots', function (Blueprint $table) {
            $table->dropColumn('post_location');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
