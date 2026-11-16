<?php

namespace App\Filament\Guru\Resources\ProgramSemesters\Pages;

use App\Enums\Semester;
use App\Filament\Guru\Resources\ProgramSemesters\ProgramSemesterResource;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\ProgramSemester;
use App\Models\ProgramTahunan;
use App\Models\TahunAjaran;
use App\Services\Kurikulum\PenyusunProsemKurikulumMerdeka;
use App\Services\Kurikulum\PenyusunProtaKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListProgramSemesters extends ListRecords
{
    protected static string $resource = ProgramSemesterResource::class;

    protected function getHeaderActions(): array
    {
        $gtk = ProgramSemesterResource::gtk();

        return [
            Action::make('generateDenganAi')
                ->label('Generate PROSEM dengan AI (Gemini)')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate PROSEM Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan bertindak sebagai konsultan perencanaan kurikulum untuk menyusun Program Semester secara menyeluruh (identitas, Tujuan Pembelajaran, materi pokok, alokasi JP, pemetaan bulan dan minggu efektif).')
                ->modalSubmitActionLabel('Mulai Generate PROSEM')
                ->modalWidth('2xl')
                ->schema(fn (): array => static::skemaModalGenerateAi($gtk))
                ->action(function (array $data) use ($gtk): void {
                    abort_unless($gtk instanceof Gtk, 403);

                    $penyusun = app(PenyusunProsemKurikulumMerdeka::class);

                    $prosem = $penyusun->buatProsemLengkap(
                        (int) (auth()->id() ?? 0),
                        $gtk,
                        (int) $data['tahun_ajaran_id'],
                        (int) $data['kelas_id'],
                        (int) $data['mata_pelajaran_id'],
                        (int) $data['semester'],
                        (string) ($data['fase'] ?? 'D'),
                        (int) ($data['jp_per_minggu'] ?? 4),
                        ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null,
                        ! empty($data['program_tahunan_id']) ? (int) $data['program_tahunan_id'] : null
                    );

                    Notification::make()
                        ->success()
                        ->title('PROSEM Berhasil Disusun oleh AI Gemini')
                        ->body("Program Semester {$prosem->mataPelajaran->nama} {$prosem->kelas->nama} telah dibuat lengkap dengan {$prosem->items()->count()} materi pembelajaran.")
                        ->send();

                    $this->redirect(ProgramSemesterResource::getUrl('edit', ['record' => $prosem]));
                }),

            Action::make('tarikDariProta')
                ->label('Tarik dari PROTA')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('info')
                ->modalHeading('Tarik Data PROSEM dari Program Tahunan (PROTA)')
                ->modalDescription('Pilih PROTA yang sudah Anda buat sebelumnya. Sistem akan otomatis memetakan seluruh Tujuan Pembelajaran dan Materi Pokok ke jadwal bulan dan minggu semester ini.')
                ->modalSubmitActionLabel('Terapkan dari PROTA')
                ->schema(function () use ($gtk): array {
                    return [
                        Select::make('program_tahunan_id')
                            ->label('Pilih PROTA')
                            ->options(function () use ($gtk): array {
                                return ProgramTahunan::query()
                                    ->where('gtk_id', $gtk->id ?? 0)
                                    ->with(['mataPelajaran', 'kelas', 'tahunAjaran'])
                                    ->get()
                                    ->mapWithKeys(fn (ProgramTahunan $p) => [$p->id => "PROTA {$p->mataPelajaran->nama} {$p->kelas->nama} ({$p->tahunAjaran->nama})"])
                                    ->all();
                            })
                            ->required()
                            ->searchable(),

                        Select::make('semester')
                            ->label('Semester yang Dijabarkan')
                            ->options([
                                Semester::Ganjil->value => 'Semester 1 (Ganjil - Juli s.d. Desember)',
                                Semester::Genap->value => 'Semester 2 (Genap - Januari s.d. Juni)',
                            ])
                            ->default(Semester::Ganjil->value)
                            ->required(),

                        TextInput::make('jp_per_minggu')
                            ->label('Alokasi JP per Minggu')
                            ->numeric()
                            ->default(4)
                            ->suffix('JP / minggu')
                            ->required(),
                    ];
                })
                ->action(function (array $data) use ($gtk): void {
                    abort_unless($gtk instanceof Gtk, 403);

                    /** @var ProgramTahunan $prota */
                    $prota = ProgramTahunan::query()->findOrFail((int) $data['program_tahunan_id']);
                    $semester = (int) $data['semester'];
                    $jp = (int) ($data['jp_per_minggu'] ?? 4);

                    $penyusun = app(PenyusunProsemKurikulumMerdeka::class);
                    $hasil = $penyusun->salinDariProta($prota, $semester, $jp);

                    $prosem = ProgramSemester::query()->updateOrCreate(
                        [
                            'tahun_ajaran_id' => $prota->tahun_ajaran_id,
                            'semester' => $semester,
                            'kelas_id' => $prota->kelas_id,
                            'mata_pelajaran_id' => $prota->mata_pelajaran_id,
                        ],
                        [
                            'gtk_id' => $gtk->id,
                            'program_tahunan_id' => $prota->id,
                            'fase' => $prota->fase ?? 'D',
                            'jp_per_minggu' => $jp,
                            'total_minggu_efektif' => $semester === Semester::Genap->value ? 16 : 18,
                            'catatan' => $hasil['catatan'],
                        ]
                    );

                    $penyusun->terapkanKeProsem($prosem, $hasil['items']);

                    Notification::make()
                        ->success()
                        ->title('PROSEM Berhasil Dijabarkan dari PROTA')
                        ->body(count($hasil['items']).' rincian materi telah dipetakan ke bulan dan minggu semester.')
                        ->send();

                    $this->redirect(ProgramSemesterResource::getUrl('edit', ['record' => $prosem]));
                }),

            CreateAction::make()
                ->label('Buat PROSEM Manual'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function skemaModalGenerateAi(?Gtk $gtk): array
    {
        return [
            Select::make('tahun_ajaran_id')
                ->label('Tahun Pelajaran')
                ->options(function () use ($gtk): array {
                    $assigned = PembagianMengajar::query()->milik($gtk)->select('tahun_ajaran_id');

                    return TahunAjaran::query()
                        ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                        ->orderByDesc('is_aktif')
                        ->orderByDesc('nama')
                        ->pluck('nama', 'id')
                        ->all();
                })
                ->default(fn () => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required()
                ->live(),

            Select::make('semester')
                ->label('Semester')
                ->options([
                    Semester::Ganjil->value => 'Semester 1 (Ganjil - Juli s.d. Desember)',
                    Semester::Genap->value => 'Semester 2 (Genap - Januari s.d. Juni)',
                ])
                ->default(Semester::Ganjil->value)
                ->required(),

            Select::make('kelas_id')
                ->label('Kelas')
                ->options(function (Get $get) use ($gtk): array {
                    $tahunAjaranId = $get('tahun_ajaran_id');
                    if (! $tahunAjaranId) {
                        return [];
                    }

                    $assigned = PembagianMengajar::query()->milik($gtk, (int) $tahunAjaranId)->select('kelas_id');

                    return Kelas::query()
                        ->aktif()
                        ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                        ->orderBy('tingkat')
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->all();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                    $set('mata_pelajaran_id', null);
                    if ($state) {
                        $tingkat = (int) Kelas::query()->whereKey((int) $state)->value('tingkat');
                        $set('fase', PenyusunProtaKurikulumMerdeka::tebakFase($tingkat));

                        $tahunId = $get('tahun_ajaran_id');
                        $mapelId = $get('mata_pelajaran_id');
                        if ($tahunId && $mapelId) {
                            $jp = PembagianMengajar::query()
                                ->where('tahun_ajaran_id', (int) $tahunId)
                                ->where('kelas_id', (int) $state)
                                ->where('mata_pelajaran_id', (int) $mapelId)
                                ->value('jp_per_minggu');
                            if ($jp) {
                                $set('jp_per_minggu', $jp);
                            }
                        }
                    }
                }),

            Select::make('mata_pelajaran_id')
                ->label('Mata Pelajaran')
                ->options(function (Get $get) use ($gtk): array {
                    $tahunAjaranId = $get('tahun_ajaran_id');
                    $kelasId = $get('kelas_id');
                    if (! $tahunAjaranId || ! $kelasId) {
                        return [];
                    }

                    $assigned = PembagianMengajar::query()->milik($gtk, (int) $tahunAjaranId)->where('kelas_id', (int) $kelasId)->select('mata_pelajaran_id');

                    return MataPelajaran::query()
                        ->where('is_aktif', true)
                        ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                        ->orderBy('kelompok')
                        ->orderBy('urutan')
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->all();
                })
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state, Get $get) use ($gtk): void {
                    $tahunId = $get('tahun_ajaran_id');
                    $kelasId = $get('kelas_id');
                    if ($tahunId && $kelasId && $state) {
                        $jp = PembagianMengajar::query()
                            ->where('tahun_ajaran_id', (int) $tahunId)
                            ->where('kelas_id', (int) $kelasId)
                            ->where('mata_pelajaran_id', (int) $state)
                            ->value('jp_per_minggu');
                        if ($jp) {
                            $set('jp_per_minggu', $jp);
                        }

                        $protaId = ProgramTahunan::query()
                            ->where('tahun_ajaran_id', (int) $tahunId)
                            ->where('kelas_id', (int) $kelasId)
                            ->where('mata_pelajaran_id', (int) $state)
                            ->where('gtk_id', $gtk?->id)
                            ->value('id');
                        if ($protaId) {
                            $set('program_tahunan_id', $protaId);
                        }
                    }
                }),

            Select::make('fase')
                ->label('Fase Capaian Pembelajaran')
                ->options([
                    'A' => 'Fase A (Kelas 1–2 SD/MI)',
                    'B' => 'Fase B (Kelas 3–4 SD/MI)',
                    'C' => 'Fase C (Kelas 5–6 SD/MI)',
                    'D' => 'Fase D (Kelas 7–9 SMP/MTs)',
                    'E' => 'Fase E (Kelas 10 SMA/MA/SMK)',
                    'F' => 'Fase F (Kelas 11–12 SMA/MA/SMK)',
                ])
                ->default('D')
                ->required(),

            TextInput::make('jp_per_minggu')
                ->label('Alokasi Jam per Minggu (JP)')
                ->numeric()
                ->default(4)
                ->minValue(1)
                ->maxValue(10)
                ->suffix('JP / minggu')
                ->required(),

            Select::make('program_tahunan_id')
                ->label('Tautkan dengan PROTA Terkait (Opsional)')
                ->options(function () use ($gtk): array {
                    return ProgramTahunan::query()
                        ->where('gtk_id', $gtk->id ?? 0)
                        ->with(['mataPelajaran', 'kelas', 'tahunAjaran'])
                        ->get()
                        ->mapWithKeys(fn (ProgramTahunan $p) => [$p->id => "PROTA {$p->mataPelajaran->nama} {$p->kelas->nama} ({$p->tahunAjaran->nama})"])
                        ->all();
                })
                ->searchable()
                ->nullable(),

            Textarea::make('instruksi_khusus')
                ->label('Instruksi / Catatan Khusus untuk AI (Opsional)')
                ->rows(3)
                ->maxLength(1000)
                ->placeholder('Contoh: Sinkronkan dengan jadwal asesmen sumatif tengah semester, tekankan tema P5, sertakan waktu cadangan dan pengayaan...')
                ->columnSpanFull(),
        ];
    }
}
