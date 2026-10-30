<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every outgoing letter is signed by the principal of its academic year; another official only signs
     * alongside, when chosen. Letters that named the principal as their signer move that name to the principal.
     */
    public function up(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->string('nama_kepala_madrasah')->nullable()->after('penanda_tangan_id');
            $table->string('nip_kepala_madrasah', 30)->nullable()->after('nama_kepala_madrasah');
            $table->string('nama_penanda_tangan')->nullable()->change();
            $table->string('jabatan_penanda_tangan')->nullable()->change();
        });

        DB::table('surat_keluars')
            ->whereRaw("lower(trim(jabatan_penanda_tangan)) in ('kepala madrasah', 'plt. kepala madrasah', 'plt kepala madrasah')")
            ->update([
                'nama_kepala_madrasah' => DB::raw('nama_penanda_tangan'),
                'nip_kepala_madrasah' => DB::raw('nip_penanda_tangan'),
                'penanda_tangan_id' => null,
                'nama_penanda_tangan' => null,
                'jabatan_penanda_tangan' => null,
                'nip_penanda_tangan' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn(['nama_kepala_madrasah', 'nip_kepala_madrasah']);
        });
    }
};
