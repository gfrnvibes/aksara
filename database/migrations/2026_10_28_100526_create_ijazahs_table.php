<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The diploma archive, one row per graduate grouped by graduation year. A row links to the student when the
     * graduate is in the Buku Induk, and otherwise stands alone for alumni from before the application.
     */
    public function up(): void
    {
        Schema::create('ijazahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('tahun_lulus')->index();
            $table->string('nama')->nullable();
            $table->string('nis', 30)->nullable()->index();
            $table->string('nisn', 20)->nullable()->index();
            $table->string('nomor_seri', 100)->nullable()->unique();
            $table->date('tanggal_ijazah')->nullable();
            $table->json('berkas')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ijazahs');
    }
};
