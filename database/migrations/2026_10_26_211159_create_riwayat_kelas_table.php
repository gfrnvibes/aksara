<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The class a student sat in for each academic year, so promoting a student keeps the earlier years.
     *
     * Existing data is backfilled from report cards (the class of the latest semester of each year) and, for
     * students without a report card in the active year, from their current class.
     */
    public function up(): void
    {
        Schema::create('riwayat_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained()->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id']);
        });

        $sekarang = now();

        DB::table('rapors')
            ->select(['siswa_id', 'tahun_ajaran_id', 'kelas_id', 'semester'])
            ->orderBy('siswa_id')
            ->orderBy('tahun_ajaran_id')
            ->orderByDesc('semester')
            ->get()
            ->unique(fn (object $rapor): string => "{$rapor->siswa_id}-{$rapor->tahun_ajaran_id}")
            ->chunk(500)
            ->each(fn ($rapor) => DB::table('riwayat_kelas')->insert($rapor->map(fn (object $baris): array => [
                'siswa_id' => $baris->siswa_id,
                'tahun_ajaran_id' => $baris->tahun_ajaran_id,
                'kelas_id' => $baris->kelas_id,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->values()->all()));

        $tahunAktif = DB::table('tahun_ajarans')->where('is_aktif', true)->value('id');

        if ($tahunAktif === null) {
            return;
        }

        DB::table('siswas')
            ->whereNotNull('kelas_id')
            ->whereNotExists(fn ($query) => $query
                ->from('riwayat_kelas')
                ->whereColumn('riwayat_kelas.siswa_id', 'siswas.id')
                ->where('riwayat_kelas.tahun_ajaran_id', $tahunAktif))
            ->select(['id', 'kelas_id'])
            ->get()
            ->chunk(500)
            ->each(fn ($siswa) => DB::table('riwayat_kelas')->insert($siswa->map(fn (object $baris): array => [
                'siswa_id' => $baris->id,
                'tahun_ajaran_id' => $tahunAktif,
                'kelas_id' => $baris->kelas_id,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->values()->all()));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_kelas');
    }
};
