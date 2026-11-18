<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_ujians', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('prefix_nomor_peserta', 30)->nullable();
            $table->foreignId('penanda_tangan_id')->nullable()->constrained('penanda_tangans')->nullOnDelete();
            $table->foreignId('kop_surat_id')->nullable()->constrained('kop_surats')->nullOnDelete();
            $table->text('catatan_kartu')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ruang_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_id')->constrained('kegiatan_ujians')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 100);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->unsignedTinyInteger('jumlah_kolom')->default(4);
            $table->unsignedTinyInteger('kursi_per_meja')->default(2);
            $table->unsignedSmallInteger('kapasitas')->default(32);
            $table->timestamps();
        });

        Schema::create('penempatan_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_id')->constrained('kegiatan_ujians')->cascadeOnDelete();
            $table->foreignId('ruang_ujian_id')->nullable()->constrained('ruang_ujians')->nullOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('nomor_peserta', 50);
            $table->unsignedSmallInteger('nomor_kursi')->nullable();
            $table->unsignedSmallInteger('urutan_posisi')->nullable();
            $table->timestamps();

            $table->unique(['kegiatan_ujian_id', 'siswa_id']);
            $table->unique(['kegiatan_ujian_id', 'nomor_peserta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penempatan_ujians');
        Schema::dropIfExists('ruang_ujians');
        Schema::dropIfExists('kegiatan_ujians');
    }
};
