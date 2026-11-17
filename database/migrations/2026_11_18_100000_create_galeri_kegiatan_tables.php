<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeri_kegiatans', function (Blueprint $table): void {
            $table->id();
            $table->string('judul');
            $table->string('kategori', 100)->nullable();
            $table->date('tanggal_kegiatan')->nullable();
            $table->text('deskripsi')->nullable();
            $table->json('berkas');
            $table->boolean('is_publik')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('galeri_komentars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('galeri_kegiatan_id')->constrained('galeri_kegiatans')->cascadeOnDelete();
            $table->string('nama_pengomentar', 100);
            $table->string('peran', 50)->nullable();
            $table->text('isi');
            $table->boolean('is_tampil')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_komentars');
        Schema::dropIfExists('galeri_kegiatans');
    }
};
