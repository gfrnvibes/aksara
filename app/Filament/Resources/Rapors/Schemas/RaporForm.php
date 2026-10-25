<?php

namespace App\Filament\Resources\Rapors\Schemas;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Models\Ekstrakurikuler;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class RaporForm
{
    /**
     * @var array<string, string>
     */
    public const array PREDIKAT_SIKAP = [
        'SB' => 'SB - Sangat Baik',
        'B' => 'B - Baik',
        'C' => 'C - Cukup',
        'K' => 'K - Kurang',
    ];

    /**
     * @var array<string, string>
     */
    public const array PREDIKAT_HURUF = [
        'A' => 'A - Sangat Baik',
        'B' => 'B - Baik',
        'C' => 'C - Cukup',
        'D' => 'D - Kurang',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Rapor')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Select::make('siswa_id')
                            ->label('Siswa')
                            ->relationship('siswa', 'nama_lengkap')
                            ->getOptionLabelFromRecordUsing(fn (Siswa $record): string => "{$record->nis} - {$record->nama_lengkap}")
                            ->searchable(['nama_lengkap', 'nis', 'nisn'])
                            ->default(fn (): ?int => request()->integer('siswa') ?: null)
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                $set('kelas_id', $state ? Siswa::query()->find($state)?->kelas_id : null);
                            })
                            ->required(),
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->relationship('tahunAjaran', 'nama', fn ($query) => $query->orderByDesc('nama'))
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->preload()
                            ->required(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->live()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('siswa_id', $get('siswa_id'))
                                    ->where('tahun_ajaran_id', $get('tahun_ajaran_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Rapor siswa ini untuk tahun ajaran dan semester tersebut sudah ada.',
                            ]),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->relationship('kelas', 'nama')
                            ->default(fn (): ?int => Siswa::query()->find(request()->integer('siswa'))?->kelas_id)
                            ->preload()
                            ->required(),
                    ]),
                Section::make('Nilai Mata Pelajaran')
                    ->description('Predikat (A/B/C/D) dihitung otomatis dari nilai dan KKM saat disimpan.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('nilaiRapor')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Mata Pelajaran')->width('40%'),
                                TableColumn::make('KKM'),
                                TableColumn::make('Pengetahuan (KI-3)'),
                                TableColumn::make('Keterampilan (KI-4)'),
                                TableColumn::make('Sikap'),
                            ])
                            ->schema([
                                Select::make('mata_pelajaran_id')
                                    ->relationship('mataPelajaran', 'nama')
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        $set('kkm', $state ? MataPelajaran::query()->find($state)?->kkm : null);
                                    })
                                    ->required(),
                                TextInput::make('kkm')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->required(),
                                TextInput::make('nilai_pengetahuan')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                                TextInput::make('nilai_keterampilan')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                                Select::make('sikap')
                                    ->options(array_combine(array_keys(static::PREDIKAT_SIKAP), array_keys(static::PREDIKAT_SIKAP))),
                            ])
                            ->default(fn (): array => MataPelajaran::query()
                                ->aktifBerurutan()
                                ->get()
                                ->map(fn (MataPelajaran $mataPelajaran): array => [
                                    'mata_pelajaran_id' => $mataPelajaran->id,
                                    'kkm' => $mataPelajaran->kkm,
                                ])
                                ->all())
                            ->addActionLabel('Tambah mata pelajaran'),
                    ]),
                Section::make('Ekstrakurikuler')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('nilaiEkstrakurikuler')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Ekstrakurikuler'),
                                TableColumn::make('Nilai'),
                                TableColumn::make('Keterangan'),
                            ])
                            ->schema([
                                Select::make('ekstrakurikuler_id')
                                    ->relationship('ekstrakurikuler', 'nama')
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required(),
                                Select::make('nilai')
                                    ->options(array_combine(array_keys(static::PREDIKAT_HURUF), array_keys(static::PREDIKAT_HURUF))),
                                TextInput::make('keterangan'),
                            ])
                            ->default(fn (): array => Ekstrakurikuler::query()
                                ->where('is_wajib', true)
                                ->pluck('id')
                                ->map(fn (int $id): array => ['ekstrakurikuler_id' => $id])
                                ->all())
                            ->addActionLabel('Tambah ekstrakurikuler'),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Akhlak & Kepribadian')
                            ->columns(2)
                            ->schema([
                                Select::make('nilai_akhlak')
                                    ->label('Akhlak')
                                    ->options(static::PREDIKAT_HURUF),
                                Select::make('nilai_kepribadian')
                                    ->label('Kepribadian')
                                    ->options(static::PREDIKAT_HURUF),
                            ]),
                        Section::make('Ketidakhadiran')
                            ->columns(3)
                            ->schema([
                                TextInput::make('sakit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                                TextInput::make('izin')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                                TextInput::make('tanpa_keterangan')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Prestasi yang Pernah Diraih')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Repeater::make('prestasi')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Prestasi'),
                                TableColumn::make('Nilai / Peringkat'),
                                TableColumn::make('Keterangan'),
                            ])
                            ->schema([
                                TextInput::make('prestasi')
                                    ->required(),
                                TextInput::make('nilai'),
                                TextInput::make('keterangan'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Tambah prestasi'),
                    ]),
                Section::make('Keputusan Hasil Semester I dan II')
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (Get $get): bool => static::isSemesterGenap($get('semester')))
                    ->schema([
                        Select::make('keputusan')
                            ->options(KeputusanKenaikan::class),
                        DatePicker::make('tanggal_keputusan')
                            ->label('Tanggal'),
                    ]),
                Textarea::make('catatan')
                    ->label('Catatan Wali Kelas')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    protected static function isSemesterGenap(mixed $semester): bool
    {
        if ($semester instanceof Semester) {
            return $semester === Semester::Genap;
        }

        return (int) $semester === Semester::Genap->value;
    }
}
