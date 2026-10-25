<?php

use App\Http\Controllers\CetakBukuIndukController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('siswa/{siswa}/cetak-buku-induk', [CetakBukuIndukController::class, 'siswa'])->name('siswa.cetak');
    Route::get('cetak-buku-induk/{token}', [CetakBukuIndukController::class, 'massal'])->whereUuid('token')->name('buku-induk.cetak-massal');
});

require __DIR__.'/settings.php';
