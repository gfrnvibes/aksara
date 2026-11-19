<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akreditasi_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->restrictOnDelete();
            $table->string('kategori', 30); // dokumen_wajib | dka_butir
            $table->string('kode', 30); // dw_1..dw_6 | butir_01..butir_14
            $table->unsignedTinyInteger('komponen')->nullable(); // 1..3
            $table->string('judul');
            $table->longText('isi_html')->nullable();
            $table->json('ringkasan_data')->nullable();
            $table->string('status', 20)->default('belum'); // belum | draf | final
            $table->boolean('dibantu_ai')->default(false);
            $table->foreignId('kepala_madrasah_id')->nullable()->constrained('penanda_tangans')->nullOnDelete();
            $table->foreignId('ketua_tim_id')->nullable()->constrained('penanda_tangans')->nullOnDelete();
            $table->string('nama_pengawas')->nullable();
            $table->string('nip_pengawas', 30)->nullable();
            $table->string('jabatan_pengawas')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'kode']);
        });

        Schema::create('akreditasi_buktis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->string('kode_butir', 20); // butir_01..butir_14
            $table->string('judul');
            $table->string('sumber_tipe', 40)->default('manual'); // manual | galeri | surat_keluar | surat_masuk | perangkat_ajar | keuangan
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->string('berkas_path')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tahun_ajaran_id', 'kode_butir']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akreditasi_buktis');
        Schema::dropIfExists('akreditasi_dokumens');
    }
};
