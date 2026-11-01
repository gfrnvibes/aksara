<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gtks', function (Blueprint $table) {
            $table->unsignedSmallInteger('nomor_kode')->nullable()->unique()->after('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gtks', function (Blueprint $table) {
            $table->dropColumn('nomor_kode');
        });
    }
};
