<?php

use App\Enums\Hari;
use App\Enums\Semester;
use App\Enums\SifatPermintaanJadwal;
use App\Enums\StatusJadwal;
use App\Models\Gtk;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\PengaturanPresensi;
use App\Models\PresensiGtk;
use App\Models\PresensiSiswa;
use App\Models\PushToken;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Notifikasi\KlienExpoPush;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));

    $this->tahun = TahunAjaran::factory()->create(['nama' => '2026/2027', 'is_aktif' => true]);
    $this->user = User::factory()->create(['email' => 'guru@aksara.test', 'password' => bcrypt('password')]);
    $this->gtk = Gtk::factory()->create([
        'user_id' => $this->user->id,
        'nama' => 'Ustadz Ahmad',
        'nip' => '198801012015011001',
        'is_aktif' => true,
    ]);
    $this->kelas = Kelas::factory()->create([
        'nama' => 'VII',
        'tingkat' => 7,
        'wali_kelas_id' => $this->gtk->id,
        'is_aktif' => true,
    ]);
    $this->mapel = MataPelajaran::factory()->create(['nama' => 'Matematika', 'singkatan' => 'MAT']);
    $this->pembagian = PembagianMengajar::factory()->create([
        'tahun_ajaran_id' => $this->tahun->id,
        'gtk_id' => $this->gtk->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
    ]);
    $this->jadwal = Jadwal::query()->create([
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::berdasarkanTanggal(now()),
        'status' => StatusJadwal::Terbit,
    ]);
    IsiJadwal::query()->create([
        'jadwal_id' => $this->jadwal->id,
        'kelas_id' => $this->kelas->id,
        'pembagian_mengajar_id' => $this->pembagian->id,
        'hari' => Hari::Sabtu,
        'jp_ke' => 1,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('authenticates an active teacher via login and supports me and logout', function (): void {
    $nonGtk = User::factory()->create(['email' => 'non@aksara.test', 'password' => bcrypt('password')]);
    $this->postJson('/api/v1/auth/login', ['login' => 'non@aksara.test', 'password' => 'password'])
        ->assertForbidden();

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '198801012015011001',
        'password' => 'password',
        'device_name' => 'expo-test',
    ])->assertOk();

    $token = $response->json('token');
    expect($token)->toBeString()->not->toBeEmpty()
        ->and($response->json('user.gtk.nama'))->toBe('Ustadz Ahmad');

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonPath('tahun_ajaran.id', $this->tahun->id);

    $this->withToken($token)->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($this->user->tokens()->count())->toBe(0);
});

it('allows authenticated teachers to change their password', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/auth/password', [
        'current_password' => 'wrong-pass',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/password', [
        'current_password' => 'password',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'login' => 'guru@aksara.test',
        'password' => 'newpass123',
    ])->assertOk();
});

it('returns the published teaching schedule for the authenticated teacher', function (): void {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/guru/jadwal')
        ->assertOk()
        ->assertJsonPath('jadwal_terbit', true)
        ->assertJsonFragment([
            'kelas' => 'VII',
            'mapel' => 'Matematika',
            'singkatan' => 'MAT',
        ]);
});

it('lists class options and saves student attendance for the homeroom teacher', function (): void {
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Budi Santoso']);
    RiwayatKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'kelas_id' => $this->kelas->id,
        'tahun_ajaran_id' => $this->tahun->id,
    ]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/guru/presensi/opsi?tanggal=2026-11-21')
        ->assertOk()
        ->assertJsonFragment(['id' => $this->kelas->id, 'nama' => 'VII']);

    $sheet = $this->getJson('/api/v1/guru/presensi?kelas_id='.$this->kelas->id.'&tanggal=2026-11-21')
        ->assertOk()
        ->assertJsonFragment(['siswa_id' => $siswa->id, 'nama' => 'Budi Santoso']);

    $this->postJson('/api/v1/guru/presensi', [
        'kelas_id' => $this->kelas->id,
        'tanggal' => '2026-11-21',
        'versi' => $sheet->json('versi'),
        'baris' => [
            [
                'siswa_id' => $siswa->id,
                'status' => 'hadir',
                'terlambat_menit' => 0,
                'catatan' => null,
            ],
        ],
    ])->assertOk();

    expect(PresensiSiswa::query()->where('siswa_id', $siswa->id)->where('tanggal', '2026-11-21')->value('status'))
        ->toBe('hadir');
});

it('lists teaching meetings, records attendance, and saves teaching journal', function (): void {
    Sanctum::actingAs($this->user);

    $list = $this->getJson('/api/v1/guru/jurnal?tanggal=2026-11-21')
        ->assertOk()
        ->assertJsonFragment(['nama_kelas' => 'VII', 'nama_mapel' => 'Matematika']);

    $sumber = $list->json('pertemuan.0.sumber_jadwal');
    expect($sumber)->toBeString();

    $this->postJson('/api/v1/guru/jurnal/hadir', [
        'tanggal' => '2026-11-21',
        'sumber_jadwal' => $sumber,
    ])->assertOk()->assertJsonPath('jurnal.status', 'hadir');

    $this->postJson('/api/v1/guru/jurnal', [
        'tanggal' => '2026-11-21',
        'sumber_jadwal' => $sumber,
        'status' => 'terlaksana',
        'materi' => 'Persamaan Linear',
        'kegiatan' => 'Diskusi dan latihan soal',
    ])->assertOk()->assertJsonPath('jurnal.status', 'terlaksana');
});

it('serves dashboard, gtk attendance, timetable preferences, grades, leger, and device summary', function (): void {
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Siti Aminah']);
    RiwayatKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'kelas_id' => $this->kelas->id,
        'tahun_ajaran_id' => $this->tahun->id,
    ]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/guru/beranda')
        ->assertOk()
        ->assertJsonPath('peran.is_wali_kelas', true)
        ->assertJsonFragment(['kelas' => 'VII', 'mapel' => 'Matematika']);

    Carbon::setTestNow(Carbon::create(2026, 11, 22, 6, 0, 0, 'Asia/Jakarta'));
    $this->getJson('/api/v1/guru/beranda')
        ->assertOk()
        ->assertJsonPath('hari_ini', 'Ahad')
        ->assertJsonPath('jadwal_hari_ini', []);
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));

    PengaturanPresensi::aktif()->update([
        'aktif' => false,
        'nama_lokasi' => 'Madrasah',
        'radius_meter' => 100,
        'jam_mulai' => '06:00',
        'jam_selesai' => '12:00',
        'wajib_foto' => false,
    ]);

    $this->postJson('/api/v1/guru/kehadiran-gtk/hadir')
        ->assertOk()
        ->assertJsonPath('status', 'hadir');

    $this->getJson('/api/v1/guru/kehadiran-gtk')
        ->assertOk()
        ->assertJsonFragment(['gtk_nama' => 'Ustadz Ahmad', 'status' => 'hadir']);

    $this->postJson('/api/v1/guru/permintaan-jadwal', [
        'jenis' => 'tidak_bisa',
        'sifat' => SifatPermintaanJadwal::cases()[0]->value,
        'hari' => [1],
        'jp_mulai' => 1,
        'jp_selesai' => 2,
    ])->assertOk();

    $this->getJson('/api/v1/guru/permintaan-jadwal')
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $semester = Semester::berdasarkanTanggal(now())->value;

    $this->postJson('/api/v1/guru/nilai', [
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => $semester,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'kkm' => 75,
        'baris' => [
            [
                'siswa_id' => $siswa->id,
                'nilai_pengetahuan' => 88,
                'nilai_keterampilan' => 90,
                'capaian_kompetensi' => 'Baik dalam memahami persamaan linear.',
            ],
        ],
    ])->assertOk();

    $this->getJson("/api/v1/guru/nilai?tahun_ajaran_id={$this->tahun->id}&semester={$semester}&kelas_id={$this->kelas->id}&mata_pelajaran_id={$this->mapel->id}")
        ->assertOk()
        ->assertJsonFragment(['nama' => 'Siti Aminah', 'nilai_pengetahuan' => 88]);

    $this->getJson("/api/v1/guru/leger?tahun_ajaran_id={$this->tahun->id}&semester={$semester}&kelas_id={$this->kelas->id}")
        ->assertOk()
        ->assertJsonFragment(['nama' => 'Siti Aminah', 'peringkat' => 1]);

    $this->getJson('/api/v1/guru/perangkat')
        ->assertOk()
        ->assertJsonPath('dokumen.atp', 0);
});

it('validates geolocation radius, time window, and private photo upload for gtk attendance', function (): void {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 7, 15, 0, 'Asia/Jakarta'));

    PengaturanPresensi::query()->create([
        'aktif' => true,
        'nama_lokasi' => 'MTs At-Taufiq',
        'latitude' => -6.9174640,
        'longitude' => 107.6191230,
        'radius_meter' => 100,
        'jam_mulai' => '06:00',
        'jam_selesai' => '08:30',
        'wajib_foto' => true,
    ]);

    Sanctum::actingAs($this->user);

    // Outside radius (~500m away)
    $this->post('/api/v1/guru/kehadiran-gtk/hadir', [
        'latitude' => -6.9220000,
        'longitude' => 107.6191230,
        'foto' => UploadedFile::fake()->image('selfie.jpg', 640, 480),
    ], ['Accept' => 'application/json'])->assertStatus(422);

    // Missing photo
    $this->post('/api/v1/guru/kehadiran-gtk/hadir', [
        'latitude' => -6.9174640,
        'longitude' => 107.6191230,
    ], ['Accept' => 'application/json'])->assertStatus(422);

    // Valid location, time, and photo
    $this->post('/api/v1/guru/kehadiran-gtk/hadir', [
        'latitude' => -6.9175000,
        'longitude' => 107.6191230,
        'akurasi_meter' => 12,
        'foto' => UploadedFile::fake()->image('selfie.jpg', 640, 480),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('status', 'hadir');

    $record = PresensiGtk::query()->where('gtk_id', $this->gtk->id)->firstOrFail();
    expect($record->foto_path)->not->toBeNull()
        ->and($record->jarak_meter)->toBeLessThanOrEqual(100);
    Storage::disk('local')->assertExists((string) $record->foto_path);

    $this->get("/api/v1/guru/kehadiran-gtk/{$record->id}/foto")
        ->assertOk();

    // Outside time window
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
    $this->post('/api/v1/guru/kehadiran-gtk/hadir', [
        'latitude' => -6.9174640,
        'longitude' => 107.6191230,
        'foto' => UploadedFile::fake()->image('selfie.jpg', 640, 480),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('registers push tokens, returns today teaching sessions, and dispatches morning notifications', function (): void {
    Sanctum::actingAs($this->user);

    $token = 'ExponentPushToken[test-device-token-123]';

    $this->postJson('/api/v1/guru/push-token', [
        'token' => $token,
        'platform' => 'android',
        'device_name' => 'Pixel 8',
    ])->assertOk();

    expect(PushToken::query()->where('user_id', $this->user->id)->where('token', $token)->exists())->toBeTrue();

    $this->getJson('/api/v1/guru/sesi-hari-ini')
        ->assertOk()
        ->assertJsonFragment(['kelas' => 'VII', 'mapel' => 'Matematika']);

    Http::fake([
        KlienExpoPush::ENDPOINT => Http::response([
            'data' => [['status' => 'ok', 'id' => 'receipt-1']],
        ]),
    ]);

    $this->artisan('aksara:notifikasi-pagi-guru')
        ->expectsOutputToContain('Notifikasi pagi dikirim ke 1 perangkat.')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === KlienExpoPush::ENDPOINT
        && $request->data()[0]['to'] === $token
        && str_contains((string) $request->data()[0]['body'], 'Ustadz Ahmad'));

    $this->deleteJson('/api/v1/guru/push-token', ['token' => $token])->assertOk();
    expect(PushToken::query()->where('token', $token)->exists())->toBeFalse();
});
