<?php

namespace App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages;

use App\Filament\Guru\Resources\AlurTujuanPembelajarans\AlurTujuanPembelajaranResource;
use App\Models\AlurTujuanPembelajaran;
use App\Services\Gemini\KlienGemini;
use App\Services\Kurikulum\PenyusunAtpKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditAlurTujuanPembelajaran extends EditRecord
{
    protected static string $resource = AlurTujuanPembelajaranResource::class;

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
                ->url(fn (): string => route('atp.cetak', $this->record))
                ->openUrlInNewTab(),

            Action::make('muatDrafStandar')
                ->label('Draf Standar')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Muat Draf Standar Kurikulum Merdeka')
                ->modalDescription('Seluruh baris ATP saat ini akan diganti dengan susunan Capaian Pembelajaran (CP) dan Alur Tujuan Pembelajaran (ATP) standar yang realistis dan terstruktur secara hierarkis. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Terapkan Draf')
                ->action(function (): void {
                    /** @var AlurTujuanPembelajaran $atp */
                    $atp = $this->record;

                    $penyusun = app(PenyusunAtpKurikulumMerdeka::class);
                    $items = $penyusun->dapatkanDrafStandar($atp->mataPelajaran->nama, $atp->fase ?? 'D', $atp->jenjang ?? 'SMP');

                    $penyusun->terapkanKeAtp($atp, $items);
                    $atp->refresh();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Draf Standar ATP berhasil diterapkan')
                        ->body(count($items).' tujuan pembelajaran telah disusun secara berurutan.')
                        ->send();
                }),

            Action::make('susunDenganAi')
                ->label('AI Gemini')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate Ulang ATP dengan AI Gemini')
                ->modalDescription('AI Gemini akan menyusun ulang Alur Tujuan Pembelajaran (ATP) lengkap dari kompetensi sederhana ke kompleks.')
                ->modalSubmitActionLabel('Generate dengan AI')
                ->modalWidth('lg')
                ->schema(function (): array {
                    return [
                        Textarea::make('instruksi_khusus')
                            ->label('Instruksi / Catatan Khusus untuk AI (Opsional)')
                            ->rows(3)
                            ->maxLength(1000),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var AlurTujuanPembelajaran $atp */
                    $atp = $this->record;
                    $instruksiKhusus = ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null;

                    try {
                        $penyusun = app(PenyusunAtpKurikulumMerdeka::class);
                        $hasil = $penyusun->susunAtpLengkap(
                            (int) (auth()->id() ?? 0),
                            $atp->mataPelajaran->nama,
                            $atp->kelas->nama,
                            $atp->fase ?? 'D',
                            $atp->jenjang ?? 'SMP',
                            $instruksiKhusus
                        );

                        $atp->update([
                            'capaian_pembelajaran_fase' => $hasil['cp_fase'],
                            'catatan' => $hasil['catatan'],
                        ]);
                        $penyusun->terapkanKeAtp($atp, $hasil['items']);
                        $atp->refresh();

                        $this->fillForm();

                        Notification::make()
                            ->success()
                            ->title('ATP Berhasil Disusun oleh AI Gemini')
                            ->body(count($hasil['items']).' butir Alur Tujuan Pembelajaran telah diperbarui.')
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
