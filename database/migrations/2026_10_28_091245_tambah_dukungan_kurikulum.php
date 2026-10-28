<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each academic year follows one curriculum, which decides how its report cards are graded and printed.
     * Kurikulum Merdeka grades a subject with one final score and a written description of the competence
     * reached, next to the K13 knowledge and skill scores kept for older years.
     */
    public function up(): void
    {
        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->string('kurikulum')->default('merdeka')->after('nama');
        });

        Schema::table('nilai_rapors', function (Blueprint $table) {
            $table->unsignedTinyInteger('nilai_akhir')->nullable()->after('kkm');
            $table->text('capaian_kompetensi')->nullable()->after('nilai_akhir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai_rapors', function (Blueprint $table) {
            $table->dropColumn(['nilai_akhir', 'capaian_kompetensi']);
        });

        Schema::table('tahun_ajarans', function (Blueprint $table) {
            $table->dropColumn('kurikulum');
        });
    }
};
