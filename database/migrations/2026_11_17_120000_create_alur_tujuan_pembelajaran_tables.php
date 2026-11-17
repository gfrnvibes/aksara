<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alur_tujuan_pembelajarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->string('jenjang', 20)->default('SMP')->comment('SD / SMP / SMA / SMK');
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gtk_id')->constrained()->cascadeOnDelete();
            $table->string('fase', 10)->default('D')->comment('Fase Kurikulum Merdeka (A-F)');
            $table->text('capaian_pembelajaran_fase')->nullable()->comment('Capaian Pembelajaran (CP) Fase/Umum');
            $table->text('catatan')->nullable()->comment('Catatan / Rasional / Karakteristik Peserta Didik');
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'kelas_id', 'mata_pelajaran_id'], 'atp_unik');
        });

        Schema::create('alur_tujuan_pembelajaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alur_tujuan_pembelajaran_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('elemen_cp')->comment('1. Elemen CP');
            $table->text('capaian_pembelajaran')->comment('2. Capaian Pembelajaran');
            $table->text('tujuan_pembelajaran')->comment('3. Tujuan Pembelajaran');
            $table->text('materi_pembelajaran')->comment('4. Materi Pembelajaran');
            $table->text('alur_tujuan_pembelajaran')->comment('5. Alur Tujuan Pembelajaran');
            $table->unsignedSmallInteger('alokasi_waktu_jp')->default(0)->comment('6. Perkiraan alokasi waktu JP');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alur_tujuan_pembelajaran_items');
        Schema::dropIfExists('alur_tujuan_pembelajarans');
    }
};
