<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->restrictOnDelete();
            $table->unsignedTinyInteger('tingkat');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis', 20);
            $table->string('materi');
            $table->text('indikator')->nullable();
            $table->string('level_kognitif', 2)->nullable();
            $table->text('pertanyaan');
            $table->string('gambar')->nullable();
            $table->json('opsi')->nullable();
            $table->text('pembahasan')->nullable();
            $table->boolean('is_siap')->default(false);
            $table->timestamps();

            $table->index(['mata_pelajaran_id', 'tingkat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soals');
    }
};
