<?php

use App\Filament\Pages\BackupData;
use App\Filament\Resources\Ekstrakurikulers\EkstrakurikulerResource;
use App\Filament\Resources\Gtks\GtkResource;
use App\Filament\Resources\Jadwals\JadwalResource;
use App\Filament\Resources\JamPelajarans\JamPelajaranResource;
use App\Filament\Resources\KalenderAkademiks\KalenderAkademikResource;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\MataPelajarans\MataPelajaranResource;
use App\Filament\Resources\PembagianMengajars\PembagianMengajarResource;
use App\Filament\Resources\PermintaanJadwals\PermintaanJadwalResource;
use App\Filament\Resources\Rapors\RaporResource;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Filament\Resources\TahunAjarans\TahunAjaranResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Jadwal;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\User;
use BokshornIt\FilamentActivityTimeline\Resources\ActivityResource;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    actingAs(User::factory()->create()->assignRole(config('filament-shield.super_admin.name')));
});

it('renders every resource page for a super admin', function (Closure $url) {
    $rapor = Rapor::factory()->create();
    NilaiRapor::factory()->for($rapor)->create();

    $this->get($url($rapor))->assertOk();
})->with([
    'dashboard' => [fn (): string => filament()->getUrl()],
    'siswa index' => [fn (): string => SiswaResource::getUrl()],
    'siswa create' => [fn (): string => SiswaResource::getUrl('create')],
    'siswa edit' => [fn (Rapor $rapor): string => SiswaResource::getUrl('edit', ['record' => $rapor->siswa_id])],
    'rapor index' => [fn (): string => RaporResource::getUrl()],
    'rapor create' => [fn (Rapor $rapor): string => RaporResource::getUrl('create', ['siswa' => $rapor->siswa_id])],
    'rapor edit' => [fn (Rapor $rapor): string => RaporResource::getUrl('edit', ['record' => $rapor])],
    'tahun ajaran' => [fn (): string => TahunAjaranResource::getUrl()],
    'kelas' => [fn (): string => KelasResource::getUrl()],
    'mata pelajaran' => [fn (): string => MataPelajaranResource::getUrl()],
    'ekstrakurikuler' => [fn (): string => EkstrakurikulerResource::getUrl()],
    'pengguna' => [fn (): string => UserResource::getUrl()],
    'gtk' => [fn (): string => GtkResource::getUrl()],
    'pembagian mengajar' => [fn (): string => PembagianMengajarResource::getUrl()],
    'jadwal' => [fn (): string => JadwalResource::getUrl()],
    'atur jadwal' => [fn (): string => JadwalResource::getUrl('atur', ['record' => Jadwal::factory()->create()])],
    'jam pelajaran' => [fn (): string => JamPelajaranResource::getUrl()],
    'kalender akademik' => [fn (): string => KalenderAkademikResource::getUrl()],
    'permintaan jadwal' => [fn (): string => PermintaanJadwalResource::getUrl()],
]);

it('forbids the panel for a user without any role', function () {
    actingAs(User::factory()->create())
        ->get(SiswaResource::getUrl())
        ->assertForbidden();
});

it('hides user management from an operator', function () {
    actingAs(User::factory()->create()->assignRole('operator'))
        ->get(UserResource::getUrl())
        ->assertForbidden();

    $this->get(SiswaResource::getUrl())->assertOk();
    $this->get(BackupData::getUrl())->assertForbidden();
    $this->get(ActivityResource::getUrl())->assertForbidden();
});

it('renders the backup page and the activity log for a super admin', function () {
    $this->get(BackupData::getUrl())->assertOk();
    $this->get(ActivityResource::getUrl())->assertOk();
});
