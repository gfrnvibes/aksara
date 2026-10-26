<?php

namespace App\Filament\Imports\Concerns;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\OrangTua;
use App\Models\Siswa;
use Carbon\CarbonImmutable;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Spreadsheet columns for the student biodata and parents, shared by the student and combined importers.
 *
 * The column labels double as spreadsheet headers, so the Excel export reuses them to stay re-importable.
 */
trait ImportsDataSiswa
{
    /**
     * @var array<string, string>
     */
    public const array KOLOM_ORANG_TUA = [
        'nama' => 'Nama',
        'pekerjaan' => 'Pekerjaan',
        'pendidikan' => 'Pendidikan',
        'penghasilan' => 'Penghasilan',
        'nomor_telepon' => 'Telepon',
    ];

    /**
     * @return array<int, ImportColumn>
     */
    public static function kolomSiswa(): array
    {
        $kolomOrangTua = [];

        foreach (JenisOrangTua::cases() as $jenis) {
            foreach (static::KOLOM_ORANG_TUA as $atribut => $label) {
                $kolomOrangTua[] = ImportColumn::make("{$jenis->value}_{$atribut}")
                    ->label("{$label} {$jenis->getLabel()}")
                    ->rules(['nullable', 'max:255']);
            }
        }

        return [
            static::kolomNis(),
            ImportColumn::make('nisn')
                ->label('NISN')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state))
                ->rules(['nullable', 'digits:10'])
                ->example('0123456789'),
            ImportColumn::make('nama_lengkap')
                ->label('Nama Lengkap')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'max:255'])
                ->example('Ahmad Fauzi'),
            ImportColumn::make('nama_panggilan')
                ->label('Nama Panggilan')
                ->rules(['nullable', 'max:255'])
                ->example('Fauzi'),
            ImportColumn::make('jenis_kelamin')
                ->label('Jenis Kelamin (L/P)')
                ->requiredMappingForNewRecordsOnly()
                ->castStateUsing(fn (mixed $state): ?string => static::jenisKelamin($state))
                ->rules(['required', Rule::enum(JenisKelamin::class)])
                ->example('L'),
            ImportColumn::make('kelas')
                ->label('Kelas')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state))
                ->rules(['nullable', Rule::exists(Kelas::class, 'nama')])
                ->example('VII-A'),
            ImportColumn::make('status')
                ->label('Status')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state) === null ? null : Str::lower(static::teks($state)))
                ->rules(['nullable', Rule::enum(StatusSiswa::class)])
                ->example('aktif'),
            ImportColumn::make('tempat_lahir')
                ->label('Tempat Lahir')
                ->rules(['nullable', 'max:255'])
                ->example('Bandung'),
            ImportColumn::make('tanggal_lahir')
                ->label('Tanggal Lahir')
                ->castStateUsing(fn (mixed $state): ?string => static::tanggal($state))
                ->rules(['nullable', 'date'])
                ->example('2012-05-17'),
            ImportColumn::make('agama')
                ->label('Agama')
                ->castStateUsing(fn (mixed $state): ?string => static::agama($state))
                ->rules(['nullable', Rule::enum(Agama::class)])
                ->example('Islam'),
            ImportColumn::make('kewarganegaraan')
                ->label('Kewarganegaraan')
                ->rules(['nullable', 'max:255'])
                ->example('WNI'),
            ImportColumn::make('anak_ke')
                ->label('Anak Ke')
                ->integer()
                ->rules(['nullable', 'integer', 'min:1', 'max:255'])
                ->example('1'),
            ImportColumn::make('alamat')
                ->label('Alamat')
                ->example('Jl. Merdeka No. 1'),
            ImportColumn::make('nomor_telepon')
                ->label('Telepon Siswa')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state))
                ->rules(['nullable', 'max:30']),
            ImportColumn::make('tinggal_dengan')
                ->label('Tinggal Dengan')
                ->rules(['nullable', 'max:255'])
                ->example('Orang Tua'),
            ImportColumn::make('golongan_darah')
                ->label('Golongan Darah')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state) === null ? null : Str::upper(static::teks($state)))
                ->rules(['nullable', Rule::in(['A', 'B', 'AB', 'O'])]),
            ImportColumn::make('tinggi_badan')
                ->label('Tinggi Badan (cm)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0', 'max:300']),
            ImportColumn::make('berat_badan')
                ->label('Berat Badan (kg)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0', 'max:500']),
            ImportColumn::make('lulusan_dari')
                ->label('Lulusan Dari')
                ->rules(['nullable', 'max:255'])
                ->example('MI Al-Hikmah'),
            ImportColumn::make('nomor_sttb')
                ->label('Nomor STTB / Ijazah')
                ->castStateUsing(fn (mixed $state): ?string => static::teks($state))
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('diterima_di_tingkat')
                ->label('Diterima di Tingkat')
                ->integer()
                ->rules(['nullable', 'integer', Rule::in([7, 8, 9])])
                ->example('7'),
            ImportColumn::make('tanggal_diterima')
                ->label('Tanggal Diterima')
                ->castStateUsing(fn (mixed $state): ?string => static::tanggal($state))
                ->rules(['nullable', 'date'])
                ->example('2025-07-14'),
            ...$kolomOrangTua,
        ];
    }

    /**
     * Find the student by NIS, or start a new one.
     */
    protected function resolveSiswa(): Siswa
    {
        $siswa = Siswa::withTrashed()->firstOrNew(['nis' => $this->data['nis']]);

        if ($siswa->trashed()) {
            throw new RowImportFailedException("Siswa dengan NIS {$this->data['nis']} ada di data terhapus. Pulihkan siswa tersebut terlebih dahulu.");
        }

        return $siswa;
    }

    /**
     * Copy the mapped biodata columns onto the student. Unmapped columns leave existing values untouched.
     */
    protected function isiSiswa(Siswa $siswa): void
    {
        $atributLangsung = [
            'nisn', 'nama_lengkap', 'nama_panggilan', 'jenis_kelamin', 'status', 'tempat_lahir', 'tanggal_lahir',
            'agama', 'kewarganegaraan', 'anak_ke', 'alamat', 'nomor_telepon', 'tinggal_dengan', 'golongan_darah',
            'tinggi_badan', 'berat_badan', 'lulusan_dari', 'nomor_sttb', 'diterima_di_tingkat', 'tanggal_diterima',
        ];

        foreach ($atributLangsung as $atribut) {
            if ($this->kolomTerpetakan($atribut)) {
                $siswa->setAttribute($atribut, $this->data[$atribut]);
            }
        }

        if (blank($siswa->status)) {
            $siswa->status = StatusSiswa::Aktif;
        }

        if (blank($siswa->kewarganegaraan)) {
            $siswa->kewarganegaraan = 'WNI';
        }

        if ($this->kolomTerpetakan('nisn') && filled($siswa->nisn) && Siswa::query()
            ->where('nisn', $siswa->nisn)
            ->where('nis', '!=', $siswa->nis)
            ->exists()) {
            throw new RowImportFailedException("NISN {$siswa->nisn} sudah dipakai siswa lain.");
        }

        if ($this->kolomTerpetakan('kelas')) {
            $siswa->kelas_id = filled($this->data['kelas'])
                ? Kelas::query()->where('nama', $this->data['kelas'])->value('id')
                : null;
        }
    }

    /**
     * Create or update the father, mother and guardian rows that have at least one filled column.
     */
    protected function simpanOrangTua(Siswa $siswa): void
    {
        foreach (JenisOrangTua::cases() as $jenis) {
            $atribut = [];

            foreach (array_keys(static::KOLOM_ORANG_TUA) as $kolom) {
                if ($this->kolomTerpetakan("{$jenis->value}_{$kolom}")) {
                    $atribut[$kolom] = static::teks($this->data["{$jenis->value}_{$kolom}"]);
                }
            }

            if (array_filter($atribut) === []) {
                continue;
            }

            $orangTua = OrangTua::query()->firstOrNew(['siswa_id' => $siswa->id, 'jenis' => $jenis]);

            if (blank($atribut['nama'] ?? $orangTua->nama)) {
                throw new RowImportFailedException("Nama {$jenis->getLabel()} wajib diisi jika data {$jenis->getLabel()} diisi.");
            }

            $orangTua->fill($atribut)->save();
        }
    }

    protected static function tanggal(mixed $state): ?string
    {
        $teks = static::teks($state);

        if ($teks === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $tanggal = CarbonImmutable::createFromFormat($format, $teks);

                if ($tanggal !== null && $tanggal->format($format) === $teks) {
                    return $tanggal->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $teks;
    }

    protected static function jenisKelamin(mixed $state): ?string
    {
        $teks = Str::lower((string) static::teks($state));

        return match (true) {
            $teks === '' => null,
            in_array($teks, ['l', 'lk', 'laki-laki', 'laki laki', 'laki'], true) => JenisKelamin::LakiLaki->value,
            in_array($teks, ['p', 'pr', 'perempuan', 'wanita'], true) => JenisKelamin::Perempuan->value,
            default => $teks,
        };
    }

    protected static function agama(mixed $state): ?string
    {
        $teks = Str::lower((string) static::teks($state));

        return match ($teks) {
            '' => null,
            'budha', 'budhha' => Agama::Buddha->value,
            'protestan', 'kristen protestan' => Agama::Kristen->value,
            default => $teks,
        };
    }
}
