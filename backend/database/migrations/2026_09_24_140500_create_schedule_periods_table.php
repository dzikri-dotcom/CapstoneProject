<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periode jadwal shift (biasanya 1 minggu).
     *
     * start_pointer_staff_id = staff pertama yang ditunjuk pointer Round Robin saat generate,
     * end_pointer_staff_id   = posisi pointer setelah generate (dipakai periode berikutnya).
     * Menyimpan pointer awal membuat generate ulang periode draft yang sama selalu konsisten.
     */
    public function up(): void
    {
        Schema::create('schedule_periods', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->foreignId('start_pointer_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('end_pointer_staff_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_periods');
    }
};
