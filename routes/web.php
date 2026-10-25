<?php

use App\Http\Controllers\CetakBukuIndukController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\UnduhBackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('siswa/{siswa}/cetak-buku-induk', [CetakBukuIndukController::class, 'siswa'])->name('siswa.cetak');
    Route::get('cetak-buku-induk/{token}', [CetakBukuIndukController::class, 'massal'])->whereUuid('token')->name('buku-induk.cetak-massal');
    Route::get('backup/unduh', UnduhBackupController::class)->middleware('signed')->name('backup.unduh');
});

require __DIR__.'/settings.php';
