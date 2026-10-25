<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rapors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained()->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->unsignedSmallInteger('sakit')->default(0);
            $table->unsignedSmallInteger('izin')->default(0);
            $table->unsignedSmallInteger('tanpa_keterangan')->default(0);
            $table->string('nilai_akhlak', 2)->nullable();
            $table->string('nilai_kepribadian', 2)->nullable();
            $table->json('prestasi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('keputusan')->nullable();
            $table->date('tanggal_keputusan')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rapors');
    }
};
