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
        Schema::create('nilai_ekstrakurikulers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ekstrakurikuler_id')->constrained()->restrictOnDelete();
            $table->string('nilai', 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['rapor_id', 'ekstrakurikuler_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_ekstrakurikulers');
    }
};
