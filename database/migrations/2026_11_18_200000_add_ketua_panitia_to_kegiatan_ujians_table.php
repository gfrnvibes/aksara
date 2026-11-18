<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->foreignId('ketua_panitia_id')->nullable()->after('penanda_tangan_id')->constrained('penanda_tangans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_ujians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ketua_panitia_id');
        });
    }
};
