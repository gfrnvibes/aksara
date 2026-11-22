<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('siswa_id')->nullable()->unique()->after('id')->constrained('siswas')->nullOnDelete();
        });

        Schema::create('tabungan_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal')->index();
            $table->string('jenis', 10); // setor | tarik
            $table->unsignedBigInteger('nominal');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabungan_siswas');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('siswa_id');
        });
    }
};
