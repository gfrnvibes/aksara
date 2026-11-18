<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('provider')->default('gemini');
            $table->text('api_key');
            $table->text('daftar_model');
            $table->unsignedSmallInteger('timeout_detik')->default(15);
            $table->unsignedSmallInteger('prioritas')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->unsignedBigInteger('total_permintaan')->default(0);
            $table->timestamp('terakhir_dipakai_at')->nullable();
            $table->string('status_terakhir')->nullable();
            $table->text('pesan_error_terakhir')->nullable();
            $table->timestamps();

            $table->index(['provider', 'is_aktif', 'prioritas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_api_keys');
    }
};
