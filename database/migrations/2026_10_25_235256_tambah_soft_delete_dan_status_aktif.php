<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students and report cards move to the trash instead of being deleted, because a hard delete cascades
     * to every grade. Classes and extracurriculars can be retired instead of deleted.
     */
    public function up(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('rapors', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->boolean('is_aktif')->default(true)->after('nama');
        });

        Schema::table('ekstrakurikulers', function (Blueprint $table) {
            $table->boolean('is_aktif')->default(true)->after('is_wajib');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('rapors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('is_aktif');
        });

        Schema::table('ekstrakurikulers', function (Blueprint $table) {
            $table->dropColumn('is_aktif');
        });
    }
};
