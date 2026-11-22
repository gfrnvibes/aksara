<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapors', function (Blueprint $table): void {
            $table->text('catatan_orang_tua')->nullable()->after('catatan');
        });

        Schema::create('pengumumans', function (Blueprint $table): void {
            $table->id();
            $table->string('judul');
            $table->text('isi');
            $table->string('tujuan', 20)->default('semua')->index();
            $table->boolean('is_aktif')->default(true)->index();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->foreignId('pembuat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notifikasi_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->string('kategori', 40)->default('umum')->index();
            $table->string('judul');
            $table->text('isi');
            $table->json('data')->nullable();
            $table->timestamp('dibaca_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_siswas');
        Schema::dropIfExists('pengumumans');

        Schema::table('rapors', function (Blueprint $table): void {
            $table->dropColumn('catatan_orang_tua');
        });
    }
};
