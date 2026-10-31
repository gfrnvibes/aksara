<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusPpdb;
use App\Models\Ppdb;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->disk = 'arsip-uji-'.Str::lower(Str::random(8));
    config(['filesystems.arsip' => $this->disk]);
    Storage::fake($this->disk);

    travelTo('2027-03-10');
    $this->tahunAjaran = TahunAjaran::factory()->create(['nama' => '2027/2028', 'ppdb_dibuka' => '2027-03-01', 'ppdb_ditutup' => '2027-04-30']);
});

/**
 * A complete registration as the public form sends it.
 *
 * @return array<string, mixed>
 */
function isianPendaftaran(array $ubah = []): array
{
    return [
        'nama_lengkap' => 'Ahmad Fauzi',
        'nisn' => '0123456789',
        'nik' => '3205010101120001',
        'tempat_lahir' => 'Garut',
        'tanggal_lahir' => '2014-06-15',
        'jenis_kelamin' => 'L',
        'agama' => 'islam',
        'asal_sekolah' => 'SD Negeri 1 Cisurupan',
        'alamat' => 'Kp. Cisurupan RT 01 RW 02',
        'nomor_telepon' => '081234567890',
        'nama_ayah_kandung' => 'Budi Santoso',
        'nama_ibu_kandung' => 'Siti Aminah',
        'berkas' => [
            'kk' => UploadedFile::fake()->image('kk.jpg'),
            'akta' => UploadedFile::fake()->create('akta.pdf', 200, 'application/pdf'),
            'foto' => UploadedFile::fake()->image('foto.png', 600, 800),
        ],
        ...$ubah,
    ];
}

it('registers an applicant with their documents and gives them a number', function () {
    get(route('ppdb'))->assertOk()->assertSeeText('2027/2028');

    post(route('ppdb.store'), isianPendaftaran())
        ->assertRedirect(route('ppdb'))
        ->assertSessionHas('terdaftar.nomor', 'PPDB-2027-001');

    $ppdb = Ppdb::query()->sole();

    expect($ppdb)
        ->tahun_ajaran_id->toBe($this->tahunAjaran->id)
        ->status->toBe(StatusPpdb::Menunggu)
        ->jenis_kelamin->toBe(JenisKelamin::LakiLaki)
        ->and(array_keys($ppdb->berkas))->toBe(['kk', 'akta', 'foto']);

    Storage::disk($this->disk)->assertExists(array_values($ppdb->berkas));
});

it('refuses registrations outside the registration period', function () {
    travelTo('2027-05-01');

    get(route('ppdb'))->assertOk()->assertSeeText('Pendaftaran ditutup');

    from(route('ppdb'))
        ->post(route('ppdb.store'), isianPendaftaran())
        ->assertRedirect(route('ppdb'))
        ->assertSessionHasErrors('ppdb');

    expect(Ppdb::query()->count())->toBe(0);
});

it('accepts each NISN once per academic year', function () {
    Ppdb::factory()->for(TahunAjaran::factory()->create(['nama' => '2026/2027']))->create(['nisn' => '0123456789']);

    post(route('ppdb.store'), isianPendaftaran())->assertSessionHasNoErrors();
    post(route('ppdb.store'), isianPendaftaran(['nik' => '3205010101120002']))->assertSessionHasErrors('nisn');

    expect($this->tahunAjaran->ppdb()->count())->toBe(1);
});

it('requires the family card, birth certificate and photo', function () {
    post(route('ppdb.store'), isianPendaftaran(['berkas' => ['kk' => UploadedFile::fake()->create('kk.exe', 10)]]))
        ->assertSessionHasErrors(['berkas.kk', 'berkas.akta', 'berkas.foto']);

    expect(Ppdb::query()->count())->toBe(0);
});

it('shows an applicant their status by number and date of birth', function () {
    Ppdb::factory()->for($this->tahunAjaran)->status(StatusPpdb::Diterima)->create(['tanggal_lahir' => '2014-06-15']);

    post(route('ppdb.status'), ['nomor' => 'ppdb-2027-001', 'tanggal_lahir_cek' => '2014-06-15'])
        ->assertSessionHas('hasilCek.status', 'Diterima');

    post(route('ppdb.status'), ['nomor' => 'PPDB-2027-001', 'tanggal_lahir_cek' => '2014-06-16'])
        ->assertSessionHasErrorsIn('cekStatus', 'nomor')
        ->assertSessionMissing('hasilCek');
});
