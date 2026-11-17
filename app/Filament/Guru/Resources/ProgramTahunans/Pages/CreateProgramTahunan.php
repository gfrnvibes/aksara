<?php

namespace App\Filament\Guru\Resources\ProgramTahunans\Pages;

use App\Filament\Guru\Resources\ProgramTahunans\ProgramTahunanResource;
use App\Models\Gtk;
use App\Services\Kurikulum\PenyusunProtaKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateProgramTahunan extends CreateRecord
{
    protected static string $resource = ProgramTahunanResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $gtk = ProgramTahunanResource::gtk();

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
                ->modalHeading('Generate PROTA Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan bertindak sebagai ahli kurikulum untuk menyusun Program Tahunan lengkap dari awal sampai akhir.')
                ->modalSubmitActionLabel('Mulai Generate')
                ->modalWidth('2xl')
                ->schema(fn (): array => ListProgramTahunans::skemaModalGenerateAi($gtk))
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
                        ->body("Program Tahunan {$prota->mataPelajaran->nama} Kelas {$prota->kelas->nama} telah dibuat.")
                        ->send();

                    $this->redirect(ProgramTahunanResource::getUrl('edit', ['record' => $prota]));
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gtk_id'] = ProgramTahunanResource::gtk()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ProgramTahunanResource::getUrl('edit', ['record' => $this->record]);
    }
}
