<?php

namespace App\Filament\Resources\KopSurats\Pages;

use App\Filament\Resources\KopSurats\KopSuratResource;
use App\Models\KopSurat;
use App\Services\Surat\PengaturanKop;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKopSurat extends EditRecord
{
    use MempratinjauKop;

    protected static string $resource = KopSuratResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Draf');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiPratinjauPdf(),
            Action::make('jadikanDefault')->label('Jadikan Default')->icon('heroicon-o-check-circle')->requiresConfirmation()
                ->modalDescription('Menerapkan draf yang sudah tersimpan hanya pada jenis dokumen yang dipilih. Surat keluar yang sudah tersimpan tetap memakai versi kop arsipnya.')
                ->schema([Select::make('jenis')->label('Gunakan untuk')->multiple()->options(PengaturanKop::JENIS)->required()])
                ->action(function (array $data): void {
                    $this->authorizeAccess();
                    $kop = $this->getRecord();
                    abort_unless($kop instanceof KopSurat, 404);
                    PengaturanKop::terapkan($kop, $data['jenis']);
                    Notification::make()->success()->title('Kop default diperbarui')->send();
                }),
        ];
    }
}
