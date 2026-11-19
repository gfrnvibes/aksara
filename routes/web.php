<?php

use App\Http\Controllers\CetakAkreditasiController;
use App\Http\Controllers\CetakAtpController;
use App\Http\Controllers\CetakBukuIndukController;
use App\Http\Controllers\CetakDenahDanKartuController;
use App\Http\Controllers\CetakHarianController;
use App\Http\Controllers\CetakJadwalController;
use App\Http\Controllers\CetakPerangkatAjarController;
use App\Http\Controllers\CetakProsemController;
use App\Http\Controllers\CetakProtaController;
use App\Http\Controllers\CetakRaporController;
use App\Http\Controllers\CetakSuratController;
use App\Http\Controllers\CetakUjianController;
use App\Http\Controllers\GaleriKomentarController;
use App\Http\Controllers\GaleriPublikController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PpdbController;
use App\Http\Controllers\UnduhBackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::get('/galeri', GaleriPublikController::class)->name('galeri.index');
Route::post('/galeri/{galeri}/komentar', [GaleriKomentarController::class, 'store'])->middleware('throttle:10,1')->name('galeri.komentar.store');
Route::get('/spmb', [PpdbController::class, 'create'])->name('ppdb');
Route::post('/spmb', [PpdbController::class, 'store'])->middleware('throttle:10,60')->name('ppdb.store');
Route::post('/spmb/status', [PpdbController::class, 'cekStatus'])->middleware('throttle:10,1')->name('ppdb.status');
Route::permanentRedirect('/ppdb', '/spmb');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('administrasi-harian/cetak', CetakHarianController::class)->name('harian.cetak');
    Route::get('keuangan/kuitansi/{transaksi}', [KeuanganController::class, 'kuitansi'])->name('keuangan.kuitansi');
    Route::get('keuangan/slip-gaji/{gaji}', [KeuanganController::class, 'slipGaji'])->name('keuangan.slip-gaji');
    Route::get('keuangan/laporan', [KeuanganController::class, 'laporan'])->name('keuangan.laporan');
    Route::get('siswa/{siswa}/cetak-buku-induk', [CetakBukuIndukController::class, 'siswa'])->name('siswa.cetak');
    Route::get('rapor/{rapor}/cetak', CetakRaporController::class)->name('rapor.cetak');
    Route::get('siswa/{siswa}/surat-keterangan-aktif', [CetakSuratController::class, 'keteranganAktif'])->name('surat.keterangan-aktif');
    Route::get('siswa/{siswa}/transkrip', [CetakSuratController::class, 'transkrip'])->name('surat.transkrip');
    Route::get('mutasi/{mutasi}/surat-pindah', [CetakSuratController::class, 'pindah'])->name('surat.pindah');
    Route::get('surat-keluar/{surat}/cetak', [CetakSuratController::class, 'keluar'])->name('surat.keluar');
    Route::get('jadwal/{jadwal}/cetak', CetakJadwalController::class)->name('jadwal.cetak');
    Route::get('program-tahunan/{prota}/cetak', CetakProtaController::class)->name('prota.cetak');
    Route::get('program-semester/{prosem}/cetak', CetakProsemController::class)->name('prosem.cetak');
    Route::get('alur-tujuan-pembelajaran/{atp}/cetak', CetakAtpController::class)->name('atp.cetak');
    Route::get('perangkat-ajar/{perangkatAjar}/cetak/{tab}/{kodeTp?}', [CetakPerangkatAjarController::class, 'cetakSatuan'])->name('perangkat-ajar.cetak-satuan');
    Route::get('perangkat-ajar/{perangkatAjar}/cetak-bundel', [CetakPerangkatAjarController::class, 'cetakBundel'])->name('perangkat-ajar.cetak-bundel');
    Route::get('cetak-ujian/{ujian}/{dokumen}/{paket?}', CetakUjianController::class)
        ->whereIn('dokumen', ['naskah', 'kunci', 'kisi-kisi'])
        ->name('ujian.cetak');
    Route::get('cetak-kartu-ujian/{kegiatan}', [CetakDenahDanKartuController::class, 'kartu'])->name('ujian.kartu');
    Route::get('cetak-denah-ujian/{kegiatan}', [CetakDenahDanKartuController::class, 'denah'])->name('ujian.denah');
    Route::get('cetak-daftar-hadir-ujian/{kegiatan}', [CetakDenahDanKartuController::class, 'daftarHadir'])->name('ujian.daftar-hadir');
    Route::get('cetak-paket-ujian/{kegiatan}', [CetakDenahDanKartuController::class, 'semuanya'])->name('ujian.semuanya');
    Route::get('akreditasi/dokumen/{dokumen}/cetak', [CetakAkreditasiController::class, 'dokumen'])->name('akreditasi.dokumen.cetak');
    Route::get('akreditasi/bundel/{tahunAjaran}', [CetakAkreditasiController::class, 'bundel'])->name('akreditasi.bundel');
    Route::get('cetak-buku-induk/{token}', [CetakBukuIndukController::class, 'massal'])->whereUuid('token')->name('buku-induk.cetak-massal');
    Route::get('backup/unduh', UnduhBackupController::class)->middleware('signed')->name('backup.unduh');
});

require __DIR__.'/settings.php';
