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
        Schema::create('nilai_rapors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('kkm');
            $table->unsignedTinyInteger('nilai_pengetahuan')->nullable();
            $table->string('predikat_pengetahuan', 1)->nullable();
            $table->unsignedTinyInteger('nilai_keterampilan')->nullable();
            $table->string('predikat_keterampilan', 1)->nullable();
            $table->string('sikap', 2)->nullable();
            $table->timestamps();

            $table->unique(['rapor_id', 'mata_pelajaran_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_rapors');
    }
};
