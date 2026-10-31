<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties each registration to an academic year with its own number and status, records the uploaded documents and
 * the student it became, and gives each academic year its registration period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->date('ppdb_dibuka')->nullable();
            $table->date('ppdb_ditutup')->nullable();
        });

        Schema::table('ppdbs', function (Blueprint $table) {
            $table->dropUnique(['nisn']);
            $table->dropUnique(['nik']);

            $table->foreignId('tahun_ajaran_id')->after('id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('nomor_urut')->after('tahun_ajaran_id');
            $table->string('nomor')->after('nomor_urut')->unique();
            $table->string('status')->default('menunggu')->index();
            $table->text('catatan')->nullable();
            $table->string('agama')->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->json('berkas')->nullable();
            $table->foreignId('siswa_id')->nullable()->constrained()->nullOnDelete();

            $table->unique(['tahun_ajaran_id', 'nomor_urut']);
            $table->unique(['tahun_ajaran_id', 'nisn']);
            $table->unique(['tahun_ajaran_id', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::table('ppdbs', function (Blueprint $table) {
            $table->dropUnique(['tahun_ajaran_id', 'nomor_urut']);
            $table->dropUnique(['tahun_ajaran_id', 'nisn']);
            $table->dropUnique(['tahun_ajaran_id', 'nik']);
            $table->dropConstrainedForeignId('siswa_id');
            $table->dropConstrainedForeignId('tahun_ajaran_id');
            $table->dropColumn(['nomor_urut', 'nomor', 'status', 'catatan', 'agama', 'asal_sekolah', 'berkas']);

            $table->unique('nisn');
            $table->unique('nik');
        });

        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->dropColumn(['ppdb_dibuka', 'ppdb_ditutup']);
        });
    }
};
