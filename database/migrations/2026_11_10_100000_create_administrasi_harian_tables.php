<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_pikets', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->string('catatan', 500)->nullable();
            $table->timestamps();
            $table->unique(['tanggal', 'gtk_id']);
        });
        Schema::create('presensi_siswas', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('siswa_id')->constrained('siswas')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('status', 20);
            $table->unsignedSmallInteger('terlambat_menit')->default(0);
            $table->string('catatan', 500)->nullable();
            $table->string('alasan_koreksi', 500)->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tanggal', 'siswa_id']);
            $table->index(['kelas_id', 'tahun_ajaran_id', 'semester']);
        });
        Schema::create('presensi_gtks', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('dicatat_pada');
            $table->string('catatan', 500)->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tanggal', 'gtk_id']);
        });
        Schema::create('jurnal_mengajars', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('sumber_jadwal');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->foreignId('guru_id')->constrained('gtks')->restrictOnDelete();
            $table->foreignId('pelaksana_id')->nullable()->constrained('gtks')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->restrictOnDelete();
            $table->string('nama_guru');
            $table->string('nama_kelas');
            $table->string('nama_mapel');
            $table->unsignedSmallInteger('jp_mulai');
            $table->unsignedSmallInteger('jp_selesai');
            $table->string('waktu')->nullable();
            $table->string('status', 30);
            $table->timestamp('hadir_pada')->nullable();
            $table->text('catatan_asli')->nullable();
            $table->text('materi')->nullable();
            $table->text('kegiatan')->nullable();
            $table->text('refleksi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->boolean('dibantu_ai')->default(false);
            $table->string('alasan', 500)->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tanggal', 'sumber_jadwal']);
            $table->index(['guru_id', 'tanggal']);
        });
        Schema::create('catatan_pikets', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->string('jenis', 30);
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->restrictOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswas')->restrictOnDelete();
            $table->foreignId('gtk_id')->nullable()->constrained('gtks')->restrictOnDelete();
            $table->unsignedSmallInteger('terlambat_menit')->default(0);
            $table->string('jam_pelajaran', 100)->nullable();
            $table->text('kejadian');
            $table->text('tindak_lanjut')->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('rapors', function (Blueprint $table): void {
            $table->timestamp('presensi_dikunci_at')->nullable();
            $table->string('alasan_koreksi_presensi', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rapors', function (Blueprint $table): void {
            $table->dropColumn(['presensi_dikunci_at', 'alasan_koreksi_presensi']);
        });
        foreach (['catatan_pikets', 'jurnal_mengajars', 'presensi_gtks', 'presensi_siswas', 'penugasan_pikets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
