<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bell schedule of each school day, the academic calendar, the teachers' scheduling requests, and the
     * weekly lesson timetables built from the teaching assignments.
     */
    public function up(): void
    {
        Schema::create('jam_pelajarans', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('hari');
            $table->time('mulai');
            $table->time('selesai');
            $table->string('jenis');
            $table->unsignedTinyInteger('jp_ke')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['hari', 'mulai']);
        });

        Schema::create('kalender_akademiks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->string('nama');
            $table->string('jenis');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });

        Schema::create('permintaan_jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');
            $table->string('sifat');
            $table->json('hari')->nullable();
            $table->unsignedTinyInteger('jp_mulai')->nullable();
            $table->unsignedTinyInteger('jp_selesai')->nullable();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('keterangan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('nama')->nullable();
            $table->string('status')->default('draf');
            $table->timestamp('diterbitkan_pada')->nullable();
            $table->json('laporan')->nullable();
            $table->timestamps();
        });

        Schema::create('isi_jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('pembagian_mengajar_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->unsignedTinyInteger('jp_ke');
            $table->boolean('is_terkunci')->default(false);
            $table->timestamps();

            $table->unique(['jadwal_id', 'kelas_id', 'hari', 'jp_ke']);
            $table->index(['jadwal_id', 'hari', 'jp_ke']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isi_jadwals');
        Schema::dropIfExists('jadwals');
        Schema::dropIfExists('permintaan_jadwals');
        Schema::dropIfExists('kalender_akademiks');
        Schema::dropIfExists('jam_pelajarans');
    }
};
