<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students joining from another school, moving to another school, or leaving, with the letter that records it.
     */
    public function up(): void
    {
        Schema::create('mutasi_siswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');
            $table->date('tanggal');
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('sekolah')->nullable();
            $table->string('alasan')->nullable();
            $table->string('nomor_surat')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutasi_siswas');
    }
};
