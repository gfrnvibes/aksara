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
        Schema::create('siswas', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->unique();
            $table->string('nisn', 10)->nullable()->unique();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('status')->default('aktif');
            $table->string('foto')->nullable();

            // A. Keterangan tentang diri siswa
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('jenis_kelamin', 1);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->default('WNI');
            $table->unsignedTinyInteger('anak_ke')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara_kandung')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara_tiri')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara_angkat')->nullable();
            $table->string('status_yatim')->nullable();
            $table->string('bahasa_sehari_hari')->nullable();

            // B. Keterangan tempat tinggal
            $table->text('alamat')->nullable();
            $table->string('nomor_telepon')->nullable();
            $table->string('tinggal_dengan')->nullable();
            $table->string('jarak_ke_sekolah')->nullable();

            // C. Keterangan kesehatan
            $table->string('golongan_darah', 3)->nullable();
            $table->string('riwayat_penyakit')->nullable();
            $table->string('kelainan_jasmani')->nullable();
            $table->unsignedSmallInteger('tinggi_badan')->nullable();
            $table->unsignedSmallInteger('berat_badan')->nullable();

            // D. Keterangan pendidikan
            $table->string('lulusan_dari')->nullable();
            $table->date('tanggal_sttb')->nullable();
            $table->string('nomor_sttb')->nullable();
            $table->string('lama_belajar')->nullable();
            $table->string('pindahan_dari')->nullable();
            $table->string('alasan_pindah')->nullable();
            $table->unsignedTinyInteger('diterima_di_tingkat')->nullable();
            $table->date('tanggal_diterima')->nullable();

            // H. Kegemaran siswa
            $table->string('kegemaran_kesenian')->nullable();
            $table->string('kegemaran_olahraga')->nullable();
            $table->string('kegemaran_organisasi')->nullable();
            $table->string('kegemaran_lain')->nullable();

            // I. Keterangan perkembangan siswa
            $table->json('beasiswa')->nullable();
            $table->date('tanggal_meninggalkan_sekolah')->nullable();
            $table->string('alasan_meninggalkan_sekolah')->nullable();
            $table->date('tanggal_tamat')->nullable();
            $table->string('nomor_sttb_tamat')->nullable();

            // J. Keterangan setelah selesai pendidikan
            $table->string('melanjutkan_di')->nullable();
            $table->date('tanggal_mulai_bekerja')->nullable();
            $table->string('nama_tempat_bekerja')->nullable();
            $table->string('penghasilan_bekerja')->nullable();

            // K & L. Catatan prestasi dan lain-lain
            $table->json('prestasi')->nullable();
            $table->text('catatan_penting')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
