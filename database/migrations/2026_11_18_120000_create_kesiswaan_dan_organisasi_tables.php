<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekstrakurikulers', function (Blueprint $table): void {
            $table->foreignId('pembina_id')->nullable()->after('nama')->constrained('gtks')->nullOnDelete();
        });

        Schema::create('pelanggaran_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('pencatat_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('kategori', 20)->default('ringan');
            $table->string('pelanggaran');
            $table->unsignedSmallInteger('poin')->default(0);
            $table->text('tindak_lanjut')->nullable();
            $table->string('status', 20)->default('proses');
            $table->timestamps();
        });

        Schema::create('prestasi_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('pencatat_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama_prestasi');
            $table->string('tingkat', 30)->default('kabupaten');
            $table->string('peringkat', 50)->nullable();
            $table->string('penyelenggara')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('anggota_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ekstrakurikuler_id')->constrained('ekstrakurikulers')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->string('jabatan', 50)->default('Anggota');
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['ekstrakurikuler_id', 'tahun_ajaran_id', 'siswa_id'], 'anggota_organisasi_unik');
        });

        Schema::create('kegiatan_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ekstrakurikuler_id')->constrained('ekstrakurikulers')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('pembina_id')->constrained('gtks')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('materi');
            $table->unsignedSmallInteger('jumlah_hadir')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_organisasis');
        Schema::dropIfExists('anggota_organisasis');
        Schema::dropIfExists('prestasi_siswas');
        Schema::dropIfExists('pelanggaran_siswas');

        Schema::table('ekstrakurikulers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pembina_id');
        });
    }
};
