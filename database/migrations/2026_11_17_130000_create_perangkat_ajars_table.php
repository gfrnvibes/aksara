<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('perangkat_ajars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->foreignId('gtk_id')->nullable()->constrained('gtks')->nullOnDelete();

            // Identitas & Parameter Madrasah / Guru
            $table->string('satuan_pendidikan')->nullable();
            $table->string('kepala_madrasah')->nullable();
            $table->string('nip_kepala')->nullable();
            $table->string('guru_pengampu')->nullable();
            $table->string('nip_guru')->nullable();
            $table->string('kota_titimangsa')->nullable();
            $table->date('tanggal_titimangsa')->nullable();
            $table->string('jenjang', 50)->default('MTs');
            $table->string('kelas', 50)->nullable();
            $table->string('fase', 10)->default('D');
            $table->string('tahun_pelajaran', 50)->nullable();
            $table->unsignedSmallInteger('jp_per_minggu')->default(5);

            // Single Source of Truth & JSON Data
            // data_ame: rincian minggu efektif & tidak efektif Semester 1 & 2 + ringkasan
            $table->jsonb('data_ame')->nullable();

            // master_tp: sumber kebenaran tunggal yang merender ATP, PROTA, dan PROSEM
            $table->jsonb('master_tp')->nullable();

            // modul_ajars: key-value/array modul ajar per kode_tp (18 komponen + LKPD + Rubrik)
            $table->jsonb('modul_ajars')->nullable();

            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'kelas_id', 'mata_pelajaran_id'], 'perangkat_ajars_unik');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perangkat_ajars');
    }
};
