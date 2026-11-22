<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_perpustakaans', function (Blueprint $table): void {
            $table->id();
            $table->string('judul');
            $table->string('penulis')->nullable();
            $table->string('penerbit')->nullable();
            $table->unsignedSmallInteger('tahun_terbit')->nullable();
            $table->string('isbn', 30)->nullable()->index();
            $table->string('kategori', 50)->default('paket')->index();
            $table->unsignedTinyInteger('tingkat')->nullable()->index();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajarans')->nullOnDelete();
            $table->text('deskripsi')->nullable();
            $table->string('sampul')->nullable();
            $table->string('file_pdf');
            $table->unsignedInteger('jumlah_dibaca')->default(0);
            $table->boolean('is_aktif')->default(true)->index();
            $table->foreignId('pengunggah_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_perpustakaans');
    }
};
