<?php

use App\Http\Controllers\CetakBukuIndukController;
use App\Http\Controllers\CetakRaporController;
use App\Http\Controllers\CetakSuratController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PpdbController;
use App\Http\Controllers\UnduhBackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::view('/ppdb', 'ppdb')->name('ppdb');
Route::post('/ppdb', [PpdbController::class, 'store'])->name('ppdb.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('siswa/{siswa}/cetak-buku-induk', [CetakBukuIndukController::class, 'siswa'])->name('siswa.cetak');
    Route::get('rapor/{rapor}/cetak', CetakRaporController::class)->name('rapor.cetak');
    Route::get('siswa/{siswa}/surat-keterangan-aktif', [CetakSuratController::class, 'keteranganAktif'])->name('surat.keterangan-aktif');
    Route::get('siswa/{siswa}/transkrip', [CetakSuratController::class, 'transkrip'])->name('surat.transkrip');
    Route::get('mutasi/{mutasi}/surat-pindah', [CetakSuratController::class, 'pindah'])->name('surat.pindah');
    Route::get('surat-keluar/{surat}/cetak', [CetakSuratController::class, 'keluar'])->name('surat.keluar');
    Route::get('cetak-buku-induk/{token}', [CetakBukuIndukController::class, 'massal'])->whereUuid('token')->name('buku-induk.cetak-massal');
    Route::get('backup/unduh', UnduhBackupController::class)->middleware('signed')->name('backup.unduh');
});

require __DIR__.'/settings.php';
