<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_tahunans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('gtk_id')->constrained()->cascadeOnDelete();
            $table->string('fase', 10)->nullable()->comment('Fase capaian pembelajaran, misal A, B, C, D, E');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'mata_pelajaran_id', 'kelas_id'], 'prota_unik');
        });

        Schema::create('program_tahunan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_tahunan_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('semester')->comment('1 = Ganjil, 2 = Genap');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('elemen_cp')->comment('Elemen capaian pembelajaran');
            $table->text('materi_pokok');
            $table->text('tujuan_pembelajaran');
            $table->unsignedSmallInteger('alokasi_waktu_jp')->default(0)->comment('Jumlah jam pelajaran');
            $table->unsignedSmallInteger('minggu_efektif')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_tahunan_items');
        Schema::dropIfExists('program_tahunans');
    }
};
