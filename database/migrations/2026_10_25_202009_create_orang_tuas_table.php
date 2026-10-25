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
        Schema::create('orang_tuas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');
            $table->string('nama');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pendidikan')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('penghasilan')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nomor_telepon')->nullable();
            $table->boolean('masih_hidup')->default(true);
            $table->timestamps();

            $table->unique(['siswa_id', 'jenis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orang_tuas');
    }
};
