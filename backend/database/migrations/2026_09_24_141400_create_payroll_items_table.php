<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian gaji per presensi (per tanggal/shift) untuk ditampilkan dan dicetak di slip.
     * breakdown berisi pecahan per slot jam: menit hadir × tarif slot jam tersebut.
     */
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('actual_minutes')->default(0); // hadir aktual di dalam jam shift
            $table->unsignedInteger('paid_minutes')->default(0);   // setelah pembulatan
            $table->decimal('amount', 14, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->index(['payroll_detail_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
