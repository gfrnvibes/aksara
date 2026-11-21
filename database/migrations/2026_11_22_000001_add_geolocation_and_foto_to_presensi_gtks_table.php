<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi_gtks', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('catatan');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('jarak_meter')->nullable()->after('longitude');
            $table->unsignedInteger('akurasi_meter')->nullable()->after('jarak_meter');
            $table->string('foto_path')->nullable()->after('akurasi_meter');
        });

        Schema::create('pengaturan_presensis', function (Blueprint $table): void {
            $table->id();
            $table->boolean('aktif')->default(true);
            $table->string('nama_lokasi')->default('Madrasah');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meter')->default(100);
            $table->string('jam_mulai', 5)->default('06:00');
            $table->string('jam_selesai', 5)->default('08:30');
            $table->boolean('wajib_foto')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_presensis');

        Schema::table('presensi_gtks', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'jarak_meter', 'akurasi_meter', 'foto_path']);
        });
    }
};
