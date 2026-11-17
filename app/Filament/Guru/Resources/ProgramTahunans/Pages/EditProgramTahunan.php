<?php

namespace App\Filament\Guru\Resources\ProgramTahunans\Pages;

use App\Filament\Guru\Resources\ProgramTahunans\ProgramTahunanResource;
use App\Models\PembagianMengajar;
use App\Models\ProgramTahunan;
use App\Services\Gemini\KlienGemini;
use App\Services\Kurikulum\PenyusunProtaKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditProgramTahunan extends EditRecord
{
    protected static string $resource = ProgramTahunanResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveHeader')
                ->label('Simpan')
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('cetak')
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('prota.cetak', $this->record))
                ->openUrlInNewTab(),

            Action::make('muatDrafStandar')
                ->label('Draf Standar')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Muat Draf Standar Kurikulum Merdeka')
                ->modalDescription('Seluruh baris materi saat ini akan diganti dengan susunan Capaian Pembelajaran (CP) dan Tujuan Pembelajaran (TP) standar Kurikulum Merdeka yang realistis. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Terapkan Draf')
                ->action(function (): void {
                    /** @var ProgramTahunan $prota */
                    $prota = $this->record;

                    $jpPerMinggu = (int) PembagianMengajar::query()
                        ->where('tahun_ajaran_id', $prota->tahun_ajaran_id)
                        ->where('kelas_id', $prota->kelas_id)
                        ->where('mata_pelajaran_id', $prota->mata_pelajaran_id)
                        ->value('jp_per_minggu') ?: 3;

                    $penyusun = app(PenyusunProtaKurikulumMerdeka::class);
                    $items = $penyusun->dapatkanDrafStandar($prota->mataPelajaran->nama, $prota->fase ?? 'D', $jpPerMinggu);

                    $penyusun->terapkanKeProta($prota, $items);
                    $prota->refresh();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Draf Kurikulum Merdeka berhasil diterapkan')
                        ->body(count($items).' materi pembelajaran telah disusun untuk Semester 1 dan 2.')
                        ->send();
                }),

            Action::make('susunDenganAi')
                ->label('AI Gemini')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate Ulang PROTA dengan AI Gemini')
                ->modalDescription('AI Gemini akan menyusun ulang rincian Capaian Pembelajaran, materi pokok, Tujuan Pembelajaran, dan alokasi waktu tahunan sesuai permintaan Anda.')
                ->modalSubmitActionLabel('Generate dengan AI')
                ->modalWidth('lg')
                ->schema(function (): array {
                    /** @var ProgramTahunan $prota */
                    $prota = $this->record;

                    $jpPerMinggu = (int) PembagianMengajar::query()
                        ->where('tahun_ajaran_id', $prota->tahun_ajaran_id)
                        ->where('kelas_id', $prota->kelas_id)
                        ->where('mata_pelajaran_id', $prota->mata_pelajaran_id)
                        ->value('jp_per_minggu') ?: 4;

                    return [
                        TextInput::make('jp_per_minggu')
                            ->label('Alokasi Jam per Minggu')
                            ->numeric()
                            ->default($jpPerMinggu)
                            ->minValue(1)
                            ->maxValue(10)
                            ->suffix('JP / minggu')
                            ->required(),

                        Textarea::make('instruksi_khusus')
                            ->label('Instruksi / Catatan Khusus untuk AI (Opsional)')
                            ->rows(3)
                            ->maxLength(1000),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var ProgramTahunan $prota */
                    $prota = $this->record;

                    $jpPerMinggu = (int) ($data['jp_per_minggu'] ?? 4);
                    $instruksiKhusus = ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null;

                    try {
                        $penyusun = app(PenyusunProtaKurikulumMerdeka::class);
                        $hasil = $penyusun->susunProtaLengkap(
                            (int) (auth()->id() ?? 0),
                            $prota->mataPelajaran->nama,
                            $prota->kelas->nama,
                            $prota->fase ?? 'D',
                            $jpPerMinggu,
                            $instruksiKhusus
                        );

                        $prota->update(['catatan' => $hasil['catatan']]);
                        $penyusun->terapkanKeProta($prota, $hasil['items']);
                        $prota->refresh();

                        $this->fillForm();

                        Notification::make()
                            ->success()
                            ->title('PROTA Berhasil Disusun oleh AI Gemini')
                            ->body(count($hasil['items']).' butir materi dan alokasi waktu tahunan telah diperbarui.')
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal menyusun dengan AI')
                            ->body(KlienGemini::alasanGagal($e))
                            ->send();
                    }
                }),

            DeleteAction::make(),
        ];
    }
}
