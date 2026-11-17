<?php

namespace App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages;

use App\Filament\Guru\Resources\AlurTujuanPembelajarans\AlurTujuanPembelajaranResource;
use App\Models\Gtk;
use App\Services\Kurikulum\PenyusunAtpKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateAlurTujuanPembelajaran extends CreateRecord
{
    protected static string $resource = AlurTujuanPembelajaranResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $gtk = AlurTujuanPembelajaranResource::gtk();

        return [
            Action::make('createHeader')
                ->label('Buat')
                ->color('primary')
                ->action('create')
                ->keyBindings(['mod+s']),

            Action::make('generateDenganAi')
                ->label('Generate AI')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate ATP Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan menyusun Alur Tujuan Pembelajaran (ATP) lengkap dari kompetensi sederhana ke kompleks.')
                ->modalSubmitActionLabel('Mulai Generate')
                ->modalWidth('2xl')
                ->schema(fn (): array => ListAlurTujuanPembelajarans::skemaModalGenerateAi($gtk))
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
                        ->body("Alur Tujuan Pembelajaran {$atp->mataPelajaran->nama} Kelas {$atp->kelas->nama} telah dibuat.")
                        ->send();

                    $this->redirect(AlurTujuanPembelajaranResource::getUrl('edit', ['record' => $atp]));
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gtk_id'] = AlurTujuanPembelajaranResource::gtk()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AlurTujuanPembelajaranResource::getUrl('edit', ['record' => $this->record]);
    }
}
