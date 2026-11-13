<?php

use App\Http\Controllers\CetakBukuIndukController;
use App\Http\Controllers\CetakHarianController;
use App\Http\Controllers\CetakJadwalController;
use App\Http\Controllers\CetakRaporController;
use App\Http\Controllers\CetakSuratController;
use App\Http\Controllers\CetakUjianController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PpdbController;
use App\Http\Controllers\UnduhBackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
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
    Route::get('cetak-ujian/{ujian}/{dokumen}/{paket?}', CetakUjianController::class)
        ->whereIn('dokumen', ['naskah', 'kunci', 'kisi-kisi'])
        ->name('ujian.cetak');
    Route::get('cetak-buku-induk/{token}', [CetakBukuIndukController::class, 'massal'])->whereUuid('token')->name('buku-induk.cetak-massal');
    Route::get('backup/unduh', UnduhBackupController::class)->middleware('signed')->name('backup.unduh');
});

require __DIR__.'/settings.php';
