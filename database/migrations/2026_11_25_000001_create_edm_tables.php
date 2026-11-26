<?php

use App\Models\EdmEvaluasi;
use App\Models\EdmIndikator;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edm_evaluasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->unique()->constrained()->cascadeOnDelete();
            $table->string('judul');
            $table->string('status', 20)->default('draf');
            $table->longText('ringkasan_ai_html')->nullable();
            $table->foreignIdFor(User::class, 'pembuat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('edm_indikators', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(EdmEvaluasi::class)->constrained()->cascadeOnDelete();
            $table->string('aspek', 2);
            $table->string('kode', 8);
            $table->text('pertanyaan');
            $table->unsignedTinyInteger('skor_saran')->nullable();
            $table->unsignedTinyInteger('skor_final')->nullable();
            $table->json('metrik_sistem')->nullable();
            $table->json('rekap_tabel')->nullable();
            $table->longText('kondisi_nyata')->nullable();
            $table->longText('tindak_lanjut')->nullable();
            $table->boolean('dibantu_ai')->default(false);
            $table->timestamp('terakhir_sinkron_at')->nullable();
            $table->foreignIdFor(User::class, 'pengisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['edm_evaluasi_id', 'kode']);
        });

        Schema::create('edm_buktis', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(EdmIndikator::class)->constrained()->cascadeOnDelete();
            $table->string('judul');
            $table->string('berkas_path');
            $table->string('keterangan')->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('galeri_kegiatans', function (Blueprint $table): void {
            $table->json('kode_butir_edm')->nullable()->after('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('galeri_kegiatans', function (Blueprint $table): void {
            $table->dropColumn('kode_butir_edm');
        });

        Schema::dropIfExists('edm_buktis');
        Schema::dropIfExists('edm_indikators');
        Schema::dropIfExists('edm_evaluasis');
    }
};
