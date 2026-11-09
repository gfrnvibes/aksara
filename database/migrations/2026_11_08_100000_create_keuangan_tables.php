<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained()->restrictOnDelete();
            $table->string('nama');
            $table->string('periode', 50);
            $table->unsignedBigInteger('nominal');
            $table->date('jatuh_tempo');
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('pembatal_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['siswa_id', 'tahun_ajaran_id', 'nama', 'periode'], 'tagihan_siswa_periode_unique');
        });

        Schema::create('transaksi_keuangans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('nomor')->unique();
            $table->foreignId('tagihan_siswa_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->date('tanggal')->index();
            $table->string('jenis', 20);
            $table->string('kategori');
            $table->string('uraian');
            $table->unsignedBigInteger('nominal');
            $table->string('metode', 20);
            $table->string('referensi')->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('pembatal_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_keuangans');
        Schema::dropIfExists('tagihan_siswas');
    }
};
