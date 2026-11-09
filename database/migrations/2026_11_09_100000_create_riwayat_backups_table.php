<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_backups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch')->index();
            $table->string('disk');
            $table->string('jenis');
            $table->string('sumber');
            $table->string('status')->default('menunggu')->index();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->text('kesalahan')->nullable();
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_backups');
    }
};
