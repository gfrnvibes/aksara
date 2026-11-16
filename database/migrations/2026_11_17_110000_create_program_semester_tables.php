<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('semester')->comment('1 = Ganjil, 2 = Genap');
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gtk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_tahunan_id')->nullable()->constrained('program_tahunans')->nullOnDelete();
            $table->string('fase', 10)->default('D')->comment('Fase Kurikulum Merdeka (A-F)');
            $table->unsignedSmallInteger('jp_per_minggu')->default(4)->comment('Alokasi JP per minggu');
            $table->unsignedSmallInteger('total_minggu_efektif')->default(18)->comment('Jumlah minggu efektif semester');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'semester', 'kelas_id', 'mata_pelajaran_id'], 'prosem_unik');
        });

        Schema::create('program_semester_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->text('tujuan_pembelajaran')->comment('Tujuan Pembelajaran');
            $table->text('materi_pokok')->comment('Materi pokok / lingkup materi');
            $table->unsignedSmallInteger('alokasi_waktu_jp')->default(0)->comment('Jumlah jam pelajaran');
            $table->string('bulan', 100)->comment('Bulan pelaksanaan');
            $table->string('minggu_ke', 100)->comment('Minggu ke- berapa');
            $table->json('distribusi_minggu')->nullable()->comment('Matriks JP per bulan dan minggu');
            $table->text('keterangan')->nullable()->comment('Asesmen / P5 / Cadangan / Keterangan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_semester_items');
        Schema::dropIfExists('program_semesters');
    }
};
