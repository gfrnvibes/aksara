<?php

namespace App\Filament\Guru\Resources\ProgramTahunans\Pages;

use App\Filament\Guru\Resources\ProgramTahunans\ProgramTahunanResource;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
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

class ListProgramTahunans extends ListRecords
{
    protected static string $resource = ProgramTahunanResource::class;

    protected function getHeaderActions(): array
    {
        $gtk = ProgramTahunanResource::gtk();

        return [
            Action::make('generateDenganAi')
                ->label('Generate PROTA dengan AI (Gemini)')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate PROTA Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan bertindak sebagai ahli kurikulum untuk menyusun Program Tahunan lengkap dari awal sampai akhir (identitas, Capaian Pembelajaran, materi pokok, Tujuan Pembelajaran, alokasi waktu JP, dan minggu efektif).')
                ->modalSubmitActionLabel('Mulai Generate PROTA')
                ->modalWidth('2xl')
                ->schema(fn (): array => static::skemaModalGenerateAi($gtk))
                ->action(function (array $data) use ($gtk): void {
                    abort_unless($gtk instanceof Gtk, 403);

                    $penyusun = app(PenyusunProtaKurikulumMerdeka::class);

                    $prota = $penyusun->buatProtaLengkap(
                        (int) (auth()->id() ?? 0),
                        $gtk,
                        (int) $data['tahun_ajaran_id'],
                        (int) $data['kelas_id'],
                        (int) $data['mata_pelajaran_id'],
                        (string) ($data['fase'] ?? 'D'),
                        (int) ($data['jp_per_minggu'] ?? 4),
                        ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null
                    );

                    Notification::make()
                        ->success()
                        ->title('PROTA Berhasil Disusun oleh AI Gemini')
                        ->body("Program Tahunan {$prota->mataPelajaran->nama} Kelas {$prota->kelas->nama} telah dibuat lengkap dengan {$prota->items()->count()} materi pembelajaran.")
                        ->send();

                    $this->redirect(ProgramTahunanResource::getUrl('edit', ['record' => $prota]));
                }),

            CreateAction::make()
                ->label('Buat PROTA Manual'),
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
                ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
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
                ->helperText('Digunakan untuk menghitung total jam pelajaran realistis selama satu tahun.')
                ->required(),

            Textarea::make('instruksi_khusus')
                ->label('Instruksi / Catatan Khusus untuk AI (Opsional)')
                ->rows(3)
                ->maxLength(1000)
                ->placeholder('Contoh: Fokus pada penguatan literasi dan numerasi, sertakan tema P5 Kearifan Lokal dan Kewirausahaan, atau sesuaikan dengan konteks madrasah...')
                ->columnSpanFull(),
        ];
    }
}
