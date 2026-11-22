<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buku_perpustakaans', function (Blueprint $table): void {
            $table->string('sumber', 30)->default('manual')->after('kategori')->index();
            $table->string('sumber_url')->nullable()->after('file_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('buku_perpustakaans', function (Blueprint $table): void {
            $table->dropColumn(['sumber', 'sumber_url']);
        });
    }
};
