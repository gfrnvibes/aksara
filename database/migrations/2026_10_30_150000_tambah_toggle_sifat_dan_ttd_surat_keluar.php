<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->boolean('gunakan_sifat')->default(false)->after('jenis');
            $table->boolean('tanda_tangan_kepala_madrasah')->default(true)->after('penanda_tangan_id');
        });

        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->string('sifat')->nullable()->default(null)->change();
        });

        DB::table('surat_keluars')
            ->whereNotNull('sifat')
            ->update(['gunakan_sifat' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn(['gunakan_sifat', 'tanda_tangan_kepala_madrasah']);
        });

        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->string('sifat')->default('biasa')->change();
        });
    }
};
