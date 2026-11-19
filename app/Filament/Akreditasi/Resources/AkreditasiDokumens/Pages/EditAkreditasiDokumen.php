<?php

namespace App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages;

use App\Filament\Akreditasi\Resources\AkreditasiDokumens\AkreditasiDokumenResource;
use App\Models\AkreditasiDokumen;
use App\Services\Akreditasi\PenyusunDokumenAkreditasiAi;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAkreditasiDokumen extends EditRecord
{
    protected static string $resource = AkreditasiDokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAi')
                ->label('Generate Ulang dengan AI')
                ->icon(Heroicon::Sparkles)
                ->extraAttributes(['class' => 'btn-ai-generate'])
                ->action(function (): void {
                    /** @var AkreditasiDokumen $record */
                    $record = $this->getRecord();
                    $html = app(PenyusunDokumenAkreditasiAi::class)->susun($record);
                    $this->form->fill([...$this->form->getState(), 'isi_html' => $html, 'status' => $record->status]);

                    Notification::make()->success()->title('Draf dokumen diperbarui dengan data nyata Aksara')->send();
                }),
            Action::make('cetakPdf')
                ->label('Cetak PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('akreditasi.dokumen.cetak', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
