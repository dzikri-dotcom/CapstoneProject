<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif per slot jam beserta tanggal berlaku.
     *
     * Satu "versi tarif" = semua baris dengan effective_date yang sama.
     * Tarif yang berlaku pada tanggal X = versi dengan effective_date terbesar <= X,
     * sehingga penggajian periode lama tetap memakai tarif lamanya.
     */
    public function up(): void
    {
        Schema::create('hourly_rates', function (Blueprint $table) {
            $table->id();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('rate'); // Rupiah per jam
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['effective_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_rates');
    }
};
