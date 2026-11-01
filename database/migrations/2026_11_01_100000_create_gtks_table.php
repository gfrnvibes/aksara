<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The madrasah's teachers and staff (GTK), who teaches which subject in which class each academic year, and who
     * looks after each class. A GTK may be linked to a login account, which opens the teacher panel.
     */
    public function up(): void
    {
        Schema::create('gtks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nama');
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nuptk', 30)->nullable()->unique();
            $table->string('peg_id', 30)->nullable()->unique();
            $table->string('nik', 16)->nullable();
            $table->string('jenis_kelamin', 1);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_gtk');
            $table->string('status_kepegawaian');
            $table->string('jabatan')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('program_studi')->nullable();
            $table->boolean('is_sertifikasi')->default(false);
            $table->date('tmt_tugas')->nullable();
            $table->string('nomor_hp', 20)->nullable()->unique();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('wali_kelas_id')->nullable()->after('nama')->constrained('gtks')->nullOnDelete();
        });

        Schema::create('pembagian_mengajars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gtk_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('jp_per_minggu')->default(2);
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'kelas_id', 'mata_pelajaran_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->boolean('wajib_ganti_password')->default(false)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wajib_ganti_password');
        });

        Schema::dropIfExists('pembagian_mengajars');

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wali_kelas_id');
        });

        Schema::dropIfExists('gtks');
    }
};
