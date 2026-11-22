<?php

use App\Enums\Semester;
use App\Models\AnggotaOrganisasi;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiEkstrakurikuler;
use App\Models\NilaiRapor;
use App\Models\PelanggaranSiswa;
use App\Models\PresensiSiswa;
use App\Models\PrestasiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TabunganSiswa;
use App\Models\TagihanSiswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 22, 9, 0, 0, 'Asia/Jakarta'));

    $this->tahun = TahunAjaran::factory()->create(['nama' => '2026/2027', 'is_aktif' => true]);
    $this->kelas = Kelas::factory()->create(['nama' => 'VII', 'tingkat' => 7, 'is_aktif' => true]);
    $this->siswa = Siswa::factory()->create([
        'nis' => '260001',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Ahmad Fauzi',
        'kelas_id' => $this->kelas->id,
        'tanggal_lahir' => '2013-05-17',
    ]);
    $this->pencatat = User::factory()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('authenticates student/parent via NIS and birth date and serves all student portal endpoints', function (): void {
    $semester = Semester::berdasarkanTanggal(now('Asia/Jakarta'));
    $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika', 'urutan' => 1]);
    $rapor = Rapor::query()->create([
        'siswa_id' => $this->siswa->id,
        'kelas_id' => $this->kelas->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => $semester,
        'catatan' => 'Terus tingkatkan belajar.',
    ]);
    NilaiRapor::query()->create([
        'rapor_id' => $rapor->id,
        'mata_pelajaran_id' => $mapel->id,
        'kkm' => 75,
        'nilai_pengetahuan' => 90,
        'nilai_keterampilan' => 88,
    ]);

    PresensiSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'kelas_id' => $this->kelas->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => $semester->value,
        'tanggal' => '2026-11-21',
        'status' => 'hadir',
        'terlambat_menit' => 0,
        'pencatat_id' => $this->pencatat->id,
    ]);

    $ekskul = Ekstrakurikuler::query()->create(['nama' => 'Pramuka', 'is_aktif' => true]);
    AnggotaOrganisasi::query()->create([
        'ekstrakurikuler_id' => $ekskul->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'siswa_id' => $this->siswa->id,
        'jabatan' => 'Anggota',
    ]);
    NilaiEkstrakurikuler::query()->create([
        'rapor_id' => $rapor->id,
        'ekstrakurikuler_id' => $ekskul->id,
        'nilai' => 'A',
        'keterangan' => 'Sangat aktif',
    ]);

    PelanggaranSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'pencatat_id' => $this->pencatat->id,
        'tanggal' => '2026-11-20',
        'kategori' => 'Kedisiplinan',
        'pelanggaran' => 'Terlambat upacara',
        'poin' => 5,
        'status' => 'dibina',
    ]);

    PrestasiSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'pencatat_id' => $this->pencatat->id,
        'tanggal' => '2026-11-15',
        'nama_prestasi' => 'Olimpiade Matematika',
        'tingkat' => 'Kabupaten',
        'peringkat' => 'Juara 1',
    ]);

    TagihanSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'tahun_ajaran_id' => $this->tahun->id,
        'pencatat_id' => $this->pencatat->id,
        'nama' => 'SPP November 2026',
        'periode' => '2026-11',
        'nominal' => 150000,
        'jatuh_tempo' => '2026-11-10',
    ]);

    TabunganSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'pencatat_id' => $this->pencatat->id,
        'tanggal' => '2026-11-18',
        'jenis' => 'setor',
        'nominal' => 100000,
        'keterangan' => 'Setoran awal',
    ]);
    TabunganSiswa::query()->create([
        'siswa_id' => $this->siswa->id,
        'pencatat_id' => $this->pencatat->id,
        'tanggal' => '2026-11-19',
        'jenis' => 'tarik',
        'nominal' => 25000,
        'keterangan' => 'Beli alat tulis',
    ]);

    $login = $this->postJson('/api/v1/auth/login-siswa', [
        'nis' => '260001',
        'password' => '2013-05-17',
    ])->assertOk();

    $token = $login->json('token');
    expect($login->json('user.role'))->toBe('siswa')
        ->and($login->json('user.siswa.nama'))->toBe('Ahmad Fauzi');

    $this->withToken($token)->getJson('/api/v1/siswa/beranda')
        ->assertOk()
        ->assertJsonPath('ringkasan.peringkat', 1)
        ->assertJsonPath('ringkasan.saldo_tabungan', 75000)
        ->assertJsonPath('ringkasan.total_tagihan_sisa', 150000)
        ->assertJsonPath('ringkasan.poin_pelanggaran', 5)
        ->assertJsonPath('ringkasan.jumlah_prestasi', 1);

    $this->withToken($token)->getJson('/api/v1/siswa/presensi')
        ->assertOk()
        ->assertJsonPath('rekap.hadir', 1);

    $this->withToken($token)->getJson('/api/v1/siswa/nilai')
        ->assertOk()
        ->assertJsonPath('peringkat', 1)
        ->assertJsonFragment(['mapel' => 'Matematika', 'nilai_pengetahuan' => 90]);

    $this->withToken($token)->getJson('/api/v1/siswa/ekstrakurikuler')
        ->assertOk()
        ->assertJsonFragment(['nama' => 'Pramuka', 'nilai' => 'A']);

    $this->withToken($token)->getJson('/api/v1/siswa/catatan')
        ->assertOk()
        ->assertJsonPath('total_poin_pelanggaran', 5)
        ->assertJsonFragment(['nama_prestasi' => 'Olimpiade Matematika'])
        ->assertJsonFragment(['pelanggaran' => 'Terlambat upacara']);

    $this->withToken($token)->getJson('/api/v1/siswa/keuangan')
        ->assertOk()
        ->assertJsonPath('saldo_tabungan', 75000)
        ->assertJsonPath('total_sisa_tagihan', 150000)
        ->assertJsonFragment(['nama' => 'SPP November 2026']);
});
