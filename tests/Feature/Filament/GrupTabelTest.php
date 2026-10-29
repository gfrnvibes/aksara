<?php

use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Filament\Resources\Rapors\Pages\ListRapors;
use App\Filament\Resources\Siswas\Pages\ListSiswas;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'ViewAny:Rapor']));

    $this->tahunLalu = TahunAjaran::factory()->create(['nama' => '2025/2026']);
    $this->tahunAktif = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
    $this->kelasTujuh = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $this->kelasDelapan = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);

    $this->naikKelas = Siswa::factory()->for($this->kelasTujuh)->create(['nama_lengkap' => 'Ahmad Naik Kelas']);
    $this->naikKelas->update(['kelas_id' => $this->kelasDelapan->id]);
    RiwayatKelas::query()->updateOrCreate(['siswa_id' => $this->naikKelas->id, 'tahun_ajaran_id' => $this->tahunLalu->id], ['kelas_id' => $this->kelasTujuh->id]);

    $this->siswaBaru = Siswa::factory()->create(['nama_lengkap' => 'Budi Belum Berkelas', 'kelas_id' => null]);
    $this->alumni = Siswa::factory()->create(['nama_lengkap' => 'Citra Alumni', 'status' => StatusSiswa::Lulus, 'kelas_id' => null]);
});

it('groups students by their class in the active year by default', function () {
    Livewire::test(ListSiswas::class)
        ->assertSeeText(['2026/2027 · VIII-A', '2026/2027 · Tanpa kelas'])
        ->assertDontSeeText('2026/2027 · VII-A')
        ->assertCanSeeTableRecords([$this->naikKelas, $this->siswaBaru])
        ->assertCanNotSeeTableRecords([$this->alumni]);
});

it('groups students by the class they sat in during an earlier year', function () {
    Livewire::test(ListSiswas::class)
        ->filterTable('tahun_ajaran', $this->tahunLalu->id)
        ->assertSeeText('2025/2026 · VII-A')
        ->assertCanSeeTableRecords([$this->naikKelas])
        ->assertCanNotSeeTableRecords([$this->siswaBaru, $this->alumni]);
});

it('shows every student by their current class once the year filter is cleared', function () {
    Livewire::test(ListSiswas::class)
        ->removeTableFilter('tahun_ajaran')
        ->assertSeeText(['VIII-A', 'Tanpa kelas'])
        ->assertCanSeeTableRecords([$this->naikKelas, $this->siswaBaru, $this->alumni]);
});

it('groups report cards by year, semester and class, starting with the active year', function () {
    $raporLalu = Rapor::factory()->for($this->naikKelas)->for($this->tahunLalu)->for($this->kelasTujuh)->create(['semester' => Semester::Genap]);
    $raporGanjil = Rapor::factory()->for($this->naikKelas)->for($this->tahunAktif)->for($this->kelasDelapan)->create(['semester' => Semester::Ganjil]);

    Livewire::test(ListRapors::class)
        ->assertSeeText('2026/2027 · Semester 1 (Ganjil) · VIII-A')
        ->assertCanSeeTableRecords([$raporGanjil])
        ->assertCanNotSeeTableRecords([$raporLalu])
        ->removeTableFilter('tahun_ajaran')
        ->assertCanSeeTableRecords([$raporGanjil, $raporLalu], inOrder: true)
        ->assertSeeText('2025/2026 · Semester 2 (Genap) · VII-A');
});

it('can group report cards by student instead', function () {
    Rapor::factory()->for($this->naikKelas)->for($this->tahunAktif)->for($this->kelasDelapan)->create(['semester' => Semester::Ganjil]);

    Livewire::test(ListRapors::class)
        ->set('tableGrouping', 'siswa_id')
        ->assertSeeText("{$this->naikKelas->nis} - Ahmad Naik Kelas");
});
