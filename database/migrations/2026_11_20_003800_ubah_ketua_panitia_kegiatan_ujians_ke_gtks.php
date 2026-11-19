<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->dropForeign(['ketua_panitia_id']);
        });

        DB::table('kegiatan_ujians')->update(['ketua_panitia_id' => null]);

        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->foreign('ketua_panitia_id')->references('id')->on('gtks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->dropForeign(['ketua_panitia_id']);
        });

        DB::table('kegiatan_ujians')->update(['ketua_panitia_id' => null]);

        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->foreign('ketua_panitia_id')->references('id')->on('penanda_tangans')->nullOnDelete();
        });
    }
};
