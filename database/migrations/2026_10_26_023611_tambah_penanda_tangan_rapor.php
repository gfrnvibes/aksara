<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who signs the printed grades: the principal of the academic year, and the homeroom teacher copied onto each
     * report card from its class, so older report cards keep the people who signed them.
     */
    public function up(): void
    {
        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->string('kepala_madrasah')->nullable()->after('is_aktif');
            $table->string('nip_kepala_madrasah', 30)->nullable()->after('kepala_madrasah');
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->string('wali_kelas')->nullable()->after('nama');
            $table->string('nip_wali_kelas', 30)->nullable()->after('wali_kelas');
        });

        Schema::table('rapors', function (Blueprint $table) {
            $table->string('wali_kelas')->nullable()->after('catatan');
            $table->string('nip_wali_kelas', 30)->nullable()->after('wali_kelas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->dropColumn(['kepala_madrasah', 'nip_kepala_madrasah']);
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn(['wali_kelas', 'nip_wali_kelas']);
        });

        Schema::table('rapors', function (Blueprint $table) {
            $table->dropColumn(['wali_kelas', 'nip_wali_kelas']);
        });
    }
};
