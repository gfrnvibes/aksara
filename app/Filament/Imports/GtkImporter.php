<?php

namespace App\Filament\Imports;

use App\Enums\JenisGtk;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Models\Gtk;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Imports teachers and staff, one row per GTK, from our template or a list downloaded from EMIS GTK. A row
 * updates the GTK with the same NIP, NUPTK, PegID or NIK, in that order, or else the one with the same name.
 *
 * @property Gtk $record
 */
class GtkImporter extends BukuIndukImporter
{
    protected static ?string $model = Gtk::class;

    /**
     * @var list<string>
     */
    protected const array KOLOM_PENCOCOK = ['nip', 'nuptk', 'peg_id', 'nik'];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nama')
                ->label('Nama Lengkap')
                ->guess(['nama', 'nama gtk', 'nama lengkap dengan gelar', 'nama pegawai'])
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Siti Aminah, S.Pd.'),
            static::kolomNomor('nip', 'NIP', 18)->example('198501012010012001'),
            static::kolomNomor('nuptk', 'NUPTK', 16)->example('1234567890123456'),
            static::kolomNomor('peg_id', 'PegID', 30)->guess(['peg id', 'pegid', 'id pegawai']),
            static::kolomNomor('nik', 'NIK', 16)->rules(['nullable', 'digits:16']),
            ImportColumn::make('jenis_kelamin')
                ->label('Jenis Kelamin (L/P)')
                ->guess(['jenis kelamin', 'jk', 'l/p'])
                ->requiredMappingForNewRecordsOnly()
                ->castStateUsing(fn (mixed $state): ?string => static::jenisKelamin($state))
                ->rules(['required', Rule::enum(JenisKelamin::class)])
                ->example('P'),
            ImportColumn::make('tempat_lahir')
                ->label('Tempat Lahir')
                ->rules(['nullable', 'max:255'])
                ->example('Garut'),
            ImportColumn::make('tanggal_lahir')
                ->label('Tanggal Lahir')
                ->guess(['tgl lahir', 'tanggal lahir'])
                ->castStateUsing(fn (mixed $state): ?string => static::tanggal($state))
                ->rules(['nullable', 'date'])
                ->example('1985-01-01'),
            ImportColumn::make('jenis_gtk')
                ->label('Jenis GTK (Guru/Tendik)')
                ->guess(['jenis gtk', 'jenis ptk', 'jenis'])
                ->castStateUsing(fn (mixed $state): ?string => static::jenisGtk($state))
                ->rules(['nullable', Rule::enum(JenisGtk::class)])
                ->example('Guru'),
            ImportColumn::make('status_kepegawaian')
                ->label('Status Kepegawaian')
                ->guess(['status kepegawaian', 'status pegawai', 'status'])
                ->castStateUsing(fn (mixed $state): ?string => static::statusKepegawaian($state))
                ->rules(['nullable', Rule::enum(StatusKepegawaian::class)])
                ->example('GTY'),
            ImportColumn::make('jabatan')
                ->label('Jabatan / Tugas Tambahan')
                ->guess(['jabatan', 'tugas tambahan'])
                ->rules(['nullable', 'max:255'])
                ->example('Guru Mata Pelajaran'),
            ImportColumn::make('pendidikan_terakhir')
                ->label('Pendidikan Terakhir')
                ->guess(['pendidikan', 'jenjang pendidikan', 'ijazah terakhir'])
                ->rules(['nullable', 'max:255'])
                ->example('S1'),
            ImportColumn::make('program_studi')
                ->label('Program Studi')
                ->guess(['prodi', 'jurusan'])
                ->rules(['nullable', 'max:255'])
                ->example('Pendidikan Matematika'),
            ImportColumn::make('is_sertifikasi')
                ->label('Sertifikasi (Ya/Tidak)')
                ->guess(['sertifikasi', 'sudah sertifikasi'])
                ->boolean(),
            ImportColumn::make('tmt_tugas')
                ->label('TMT Tugas')
                ->guess(['tmt', 'tmt tugas', 'tmt mengajar'])
                ->castStateUsing(fn (mixed $state): ?string => static::tanggal($state))
                ->rules(['nullable', 'date'])
                ->example('2010-07-01'),
            ImportColumn::make('nomor_hp')
                ->label('No. HP')
                ->guess(['hp', 'no hp', 'nomor hp', 'telepon', 'no telepon'])
                ->castStateUsing(fn (mixed $state): ?string => Gtk::normalkanNomorHp(static::teks($state)))
                ->rules(['nullable', 'max:20'])
                ->example('081234567890'),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('alamat')
                ->rules(['nullable']),
        ];
    }

    public static function getNamaTemplate(): string
    {
        return 'template-data-gtk';
    }

    public function resolveRecord(): Gtk
    {
        foreach (static::KOLOM_PENCOCOK as $kolom) {
            if (filled($this->data[$kolom] ?? null) && ($gtk = Gtk::query()->where($kolom, $this->data[$kolom])->first())) {
                return $gtk;
            }
        }

        return Gtk::query()->firstOrNew(['nama' => $this->data['nama']], [
            'jenis_gtk' => JenisGtk::Guru,
            'status_kepegawaian' => StatusKepegawaian::TidakTetap,
        ]);
    }

    /**
     * Empty cells leave the stored value alone, so a list with only some columns filled does not erase the rest.
     */
    public function fillRecord(): void
    {
        foreach ($this->data as $atribut => $nilai) {
            if ($this->kolomTerpetakan($atribut) && $nilai !== null) {
                $this->record->{$atribut} = $nilai;
            }
        }
    }

    protected static function kolomNomor(string $nama, string $label, int $panjang): ImportColumn
    {
        return ImportColumn::make($nama)
            ->label($label)
            ->castStateUsing(fn (mixed $state): ?string => static::teks(preg_replace('/\s+/', '', (string) $state)))
            ->rules(['nullable', "max:{$panjang}"]);
    }

    protected static function jenisGtk(mixed $state): ?string
    {
        $teks = Str::lower((string) static::teks($state));

        return match (true) {
            $teks === '' => null,
            str_contains($teks, 'tendik'), str_contains($teks, 'tenaga'), str_contains($teks, 'staf') => JenisGtk::TenagaKependidikan->value,
            str_contains($teks, 'guru') => JenisGtk::Guru->value,
            default => $teks,
        };
    }

    protected static function statusKepegawaian(mixed $state): ?string
    {
        $teks = Str::lower((string) static::teks($state));

        return match (true) {
            $teks === '' => null,
            str_contains($teks, 'pppk'), str_contains($teks, 'p3k') => StatusKepegawaian::Pppk->value,
            str_contains($teks, 'pns') => StatusKepegawaian::Pns->value,
            str_contains($teks, 'gty'), str_contains($teks, 'pty'), str_contains($teks, 'tetap yayasan') => StatusKepegawaian::TetapYayasan->value,
            str_contains($teks, 'gtt'), str_contains($teks, 'ptt'), str_contains($teks, 'honor'), str_contains($teks, 'tidak tetap') => StatusKepegawaian::TidakTetap->value,
            default => $teks,
        };
    }
}
