<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gaji_gurus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->string('periode', 7);
            $table->string('nama_guru');
            $table->string('nip')->nullable();
            $table->unsignedBigInteger('gaji_pokok');
            $table->unsignedBigInteger('tunjangan')->default(0);
            $table->unsignedBigInteger('potongan')->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['gtk_id', 'periode']);
        });
        Schema::table('transaksi_keuangans', function (Blueprint $table): void {
            $table->foreignId('gaji_guru_id')->nullable()->index()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_keuangans', fn (Blueprint $table) => $table->dropConstrainedForeignId('gaji_guru_id'));
        Schema::dropIfExists('gaji_gurus');
    }
};
