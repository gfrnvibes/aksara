<?php

use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_ibadah_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Kelas::class)->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 40);
            $table->unsignedInteger('jumlah_siswa');
            $table->unsignedInteger('jumlah_ikut');
            $table->string('alasan_ketidakikutsertaan')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supervisi_pembelajarans', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Gtk::class)->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nomor_sk')->nullable();
            $table->string('supervisor')->nullable();
            $table->boolean('penilaian_otentik')->default(true);
            $table->text('catatan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->string('berkas_path')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rapat_madrasahs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('judul');
            $table->string('jenis', 40);
            $table->boolean('melibatkan_guru')->default(true);
            $table->boolean('melibatkan_tendik')->default(true);
            $table->boolean('melibatkan_komite')->default(false);
            $table->boolean('melibatkan_yayasan')->default(false);
            $table->text('notulensi')->nullable();
            $table->string('berkas_path')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pelatihan_gtks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Gtk::class)->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('nama_kegiatan');
            $table->string('jenis', 40);
            $table->string('penyelenggara')->nullable();
            $table->boolean('dapat_bantuan_biaya')->default(false);
            $table->string('berkas_path')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('program_rkams', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->string('nama_program');
            $table->unsignedInteger('jumlah_guru_sasaran')->default(0);
            $table->boolean('pakai_edm_rkam')->default(true);
            $table->boolean('disahkan_kepala')->default(true);
            $table->text('keterangan')->nullable();
            $table->string('berkas_path')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kegiatan_remedials', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TahunAjaran::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Gtk::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Kelas::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(MataPelajaran::class)->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 30);
            $table->unsignedInteger('jumlah_sasaran')->default(0);
            $table->unsignedInteger('jumlah_hadir')->default(0);
            $table->text('catatan')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventaris_media', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('kategori', 30);
            $table->foreignIdFor(MataPelajaran::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('jumlah')->default(1);
            $table->boolean('kondisi_baik')->default(true);
            $table->boolean('digunakan_siswa')->default(true);
            $table->string('keterangan')->nullable();
            $table->string('berkas_path')->nullable();
            $table->foreignIdFor(User::class, 'pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('jurnal_mengajars', function (Blueprint $table): void {
            $table->string('metode_pembelajaran', 60)->nullable()->after('materi');
            $table->string('media_pembelajaran', 60)->nullable()->after('metode_pembelajaran');
            $table->boolean('pakai_buku_teks')->default(true)->after('media_pembelajaran');
        });

        Schema::table('prestasi_siswas', function (Blueprint $table): void {
            $table->string('berkas_path')->nullable()->after('keterangan');
        });

        Schema::table('presensi_gtks', function (Blueprint $table): void {
            $table->string('surat_keterangan_path')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('presensi_gtks', function (Blueprint $table): void {
            $table->dropColumn('surat_keterangan_path');
        });
        Schema::table('prestasi_siswas', function (Blueprint $table): void {
            $table->dropColumn('berkas_path');
        });
        Schema::table('jurnal_mengajars', function (Blueprint $table): void {
            $table->dropColumn(['metode_pembelajaran', 'media_pembelajaran', 'pakai_buku_teks']);
        });

        Schema::dropIfExists('inventaris_media');
        Schema::dropIfExists('kegiatan_remedials');
        Schema::dropIfExists('program_rkams');
        Schema::dropIfExists('pelatihan_gtks');
        Schema::dropIfExists('rapat_madrasahs');
        Schema::dropIfExists('supervisi_pembelajarans');
        Schema::dropIfExists('kegiatan_ibadah_siswas');
    }
};
