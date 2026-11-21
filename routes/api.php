<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GuruDashboardController;
use App\Http\Controllers\Api\V1\GuruHarianController;
use App\Http\Controllers\Api\V1\GuruJadwalController;
use App\Http\Controllers\Api\V1\GuruJurnalController;
use App\Http\Controllers\Api\V1\GuruNilaiController;
use App\Http\Controllers\Api\V1\GuruNotifikasiController;
use App\Http\Controllers\Api\V1\GuruPerangkatController;
use App\Http\Controllers\Api\V1\GuruPresensiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::prefix('guru')->group(function (): void {
            Route::get('/beranda', [GuruDashboardController::class, 'index']);
            Route::get('/jadwal', [GuruJadwalController::class, 'index']);

            Route::get('/presensi/opsi', [GuruPresensiController::class, 'opsi']);
            Route::get('/presensi', [GuruPresensiController::class, 'index']);
            Route::post('/presensi', [GuruPresensiController::class, 'store']);

            Route::get('/jurnal', [GuruJurnalController::class, 'index']);
            Route::post('/jurnal/hadir', [GuruJurnalController::class, 'hadir']);
            Route::post('/jurnal/rapikan', [GuruJurnalController::class, 'rapikan']);
            Route::post('/jurnal', [GuruJurnalController::class, 'store']);

            Route::get('/kehadiran-gtk', [GuruHarianController::class, 'kehadiranGtkIndex']);
            Route::post('/kehadiran-gtk/hadir', [GuruHarianController::class, 'kehadiranGtkHadir']);
            Route::get('/kehadiran-gtk/{presensiGtk}/foto', [GuruHarianController::class, 'kehadiranGtkFoto']);
            Route::post('/kehadiran-gtk', [GuruHarianController::class, 'kehadiranGtkStore']);

            Route::get('/piket', [GuruHarianController::class, 'piketIndex']);
            Route::get('/piket/siswa', [GuruHarianController::class, 'piketSiswa']);
            Route::post('/piket', [GuruHarianController::class, 'piketStore']);

            Route::get('/permintaan-jadwal', [GuruHarianController::class, 'permintaanJadwalIndex']);
            Route::post('/permintaan-jadwal', [GuruHarianController::class, 'permintaanJadwalStore']);
            Route::delete('/permintaan-jadwal/{id}', [GuruHarianController::class, 'permintaanJadwalDestroy']);

            Route::get('/nilai/opsi', [GuruNilaiController::class, 'opsi']);
            Route::get('/nilai', [GuruNilaiController::class, 'index']);
            Route::post('/nilai/buat-capaian', [GuruNilaiController::class, 'buatCapaian']);
            Route::post('/nilai', [GuruNilaiController::class, 'store']);
            Route::get('/leger', [GuruNilaiController::class, 'leger']);
            Route::get('/rapor', [GuruNilaiController::class, 'raporIndex']);
            Route::post('/rapor/{id}', [GuruNilaiController::class, 'raporUpdate']);

            Route::get('/perangkat', [GuruPerangkatController::class, 'index']);
            Route::get('/organisasi', [GuruPerangkatController::class, 'organisasiIndex']);
            Route::post('/organisasi/kegiatan', [GuruPerangkatController::class, 'organisasiKegiatanStore']);

            Route::post('/push-token', [GuruNotifikasiController::class, 'simpanToken']);
            Route::delete('/push-token', [GuruNotifikasiController::class, 'hapusToken']);
            Route::get('/sesi-hari-ini', [GuruNotifikasiController::class, 'sesiHariIni']);
        });
    });
});
