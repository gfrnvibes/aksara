<?php

namespace App\Filament\Resources\KopSurats\Pages;

use App\Services\Surat\PengaturanKop;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;

trait MempratinjauKop
{
    protected function aksiPratinjauPdf(): Action
    {
        return Action::make('pdf')->label('Pratinjau PDF')->icon('heroicon-o-document-arrow-down')
            ->action(function () {
                $this->authorizeAccess();
                $state = $this->form->getState();
                $kop = PengaturanKop::validasi($state['konfigurasi']);
                $bytes = Pdf::loadView('cetak.pratinjau-kop', ['kop' => $kop])->setPaper('a4')->output();

                return response()->streamDownload(fn () => print ($bytes), 'pratinjau-kop.pdf', ['Content-Type' => 'application/pdf']);
            });
    }
}
