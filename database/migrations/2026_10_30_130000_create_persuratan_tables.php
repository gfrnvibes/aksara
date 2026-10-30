<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outgoing letters (numbered per year, signed by any official) and incoming letters (scanned, numbered in the
     * agenda, with their disposition).
     */
    public function up(): void
    {
        Schema::create('penanda_tangans', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jabatan');
            $table->string('nip', 30)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('surat_keluars', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_urut');
            $table->string('nomor')->unique();
            $table->string('jenis');
            $table->string('sifat')->default('biasa');
            $table->date('tanggal');
            $table->string('perihal');
            $table->string('tujuan');
            $table->string('lampiran')->nullable();
            $table->longText('isi')->nullable();
            $table->foreignId('siswa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('penanda_tangan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama_penanda_tangan');
            $table->string('jabatan_penanda_tangan');
            $table->string('nip_penanda_tangan', 30)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tahun', 'nomor_urut']);
        });

        Schema::create('surat_masuks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_agenda');
            $table->date('tanggal_diterima');
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->string('pengirim');
            $table->string('perihal');
            $table->string('sifat')->default('biasa');
            $table->text('ringkasan')->nullable();
            $table->json('berkas')->nullable();
            $table->string('status')->default('baru');
            $table->string('disposisi_kepada')->nullable();
            $table->text('disposisi_instruksi')->nullable();
            $table->date('disposisi_tanggal')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'nomor_agenda']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_masuks');
        Schema::dropIfExists('surat_keluars');
        Schema::dropIfExists('penanda_tangans');
    }
};
