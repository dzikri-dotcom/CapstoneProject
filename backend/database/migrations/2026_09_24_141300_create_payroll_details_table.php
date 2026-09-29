<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekap gaji per staff dalam satu periode penggajian.
     */
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_shifts')->default(0);
            $table->unsignedInteger('total_minutes')->default(0); // menit yang dibayar (setelah pembulatan)
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'staff_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
