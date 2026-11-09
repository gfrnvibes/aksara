<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kop_surats', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->json('konfigurasi');
            $table->timestamps();
        });
        Schema::create('default_kop_surats', function (Blueprint $table): void {
            $table->string('jenis')->primary();
            $table->foreignId('kop_surat_id')->constrained('kop_surats')->restrictOnDelete();
            $table->json('konfigurasi');
            $table->timestamps();
        });
        Schema::table('surat_keluars', fn (Blueprint $table) => $table->json('kop_surat_snapshot')->nullable());
        // Preserve the old letterhead before any custom default is published.
        DB::table('surat_keluars')->whereNull('kop_surat_snapshot')->update(['kop_surat_snapshot' => json_encode([
            'legacy' => true, 'instansi' => 'KEMENTERIAN AGAMA REPUBLIK INDONESIA',
            'nama' => config('sekolah.nama'), 'alamat' => config('sekolah.alamat'), 'wilayah' => config('sekolah.wilayah'),
        ], JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        Schema::table('surat_keluars', fn (Blueprint $table) => $table->dropColumn('kop_surat_snapshot'));
        Schema::dropIfExists('default_kop_surats');
        Schema::dropIfExists('kop_surats');
    }
};
