<?php

namespace App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages;

use App\Filament\Guru\Resources\AlurTujuanPembelajarans\AlurTujuanPembelajaranResource;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Services\Kurikulum\PenyusunAtpKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListAlurTujuanPembelajarans extends ListRecords
{
    protected static string $resource = AlurTujuanPembelajaranResource::class;

    protected function getHeaderActions(): array
    {
        $gtk = AlurTujuanPembelajaranResource::gtk();

        return [
            Action::make('generateDenganAi')
                ->label('Generate AI')
                ->icon(Heroicon::OutlinedSparkles)
                ->extraAttributes(['class' => 'btn-ai-generate'])
                ->color('success')
                ->modalHeading('Generate ATP Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan menyusun Alur Tujuan Pembelajaran (ATP) lengkap dari kompetensi sederhana ke kompleks.')
                ->modalSubmitActionLabel('Mulai Generate')
                ->modalWidth('2xl')
                ->schema(fn (): array => static::skemaModalGenerateAi($gtk))
                ->action(function (array $data) use ($gtk): void {
                    abort_unless($gtk instanceof Gtk, 403);

                    $penyusun = app(PenyusunAtpKurikulumMerdeka::class);

                    $atp = $penyusun->buatAtpLengkap(
                        (int) (auth()->id() ?? 0),
                        $gtk,
                        (int) $data['tahun_ajaran_id'],
                        (int) $data['kelas_id'],
                        (int) $data['mata_pelajaran_id'],
                        (string) ($data['jenjang'] ?? 'SMP'),
                        (string) ($data['fase'] ?? 'D'),
                        ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null
                    );

                    Notification::make()
                        ->success()
                        ->title('ATP Berhasil Disusun oleh AI Gemini')
                        ->body("Alur Tujuan Pembelajaran {$atp->mataPelajaran->nama} Kelas {$atp->kelas->nama} telah disusun lengkap.")
                        ->send();

                    $this->redirect(AlurTujuanPembelajaranResource::getUrl('edit', ['record' => $atp]));
                }),

            CreateAction::make()
                ->label('Buat ATP'),
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
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('mata_pelajaran_id', null);
                    if ($state) {
                        $tingkat = (int) Kelas::query()->whereKey((int) $state)->value('tingkat');
                        $set('jenjang', PenyusunAtpKurikulumMerdeka::tebakJenjang($tingkat));
                        $set('fase', PenyusunAtpKurikulumMerdeka::tebakFase($tingkat));
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
                ->searchable(),

            Select::make('jenjang')
                ->label('Jenjang')
                ->options([
                    'SD' => 'SD / MI',
                    'SMP' => 'SMP / MTs',
                    'SMA' => 'SMA / MA',
                    'SMK' => 'SMK / MAK',
                ])
                ->default('SMP')
                ->required(),

            Select::make('fase')
                ->label('Fase')
                ->options([
                    'A' => 'Fase A',
                    'B' => 'Fase B',
                    'C' => 'Fase C',
                    'D' => 'Fase D',
                    'E' => 'Fase E',
                    'F' => 'Fase F',
                ])
                ->default('D')
                ->required(),

            Textarea::make('instruksi_khusus')
                ->label('Catatan Tambahan untuk AI (Opsional)')
                ->rows(3)
                ->maxLength(1000),
        ];
    }
}
