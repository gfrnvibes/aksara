<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujians', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('tingkat');
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('durasi_menit');
            $table->unsignedTinyInteger('jumlah_paket')->default(1);
            $table->text('petunjuk')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('butir_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soals')->restrictOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedSmallInteger('bobot')->default(1);
            $table->timestamps();

            $table->unique(['ujian_id', 'soal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butir_ujians');
        Schema::dropIfExists('ujians');
    }
};
