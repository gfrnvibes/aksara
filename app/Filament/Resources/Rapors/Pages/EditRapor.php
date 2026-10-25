<?php

namespace App\Filament\Resources\Rapors\Pages;

use App\Filament\Resources\Rapors\RaporResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRapor extends EditRecord
{
    protected static string $resource = RaporResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityTimelineAction::make()
                ->label('Riwayat Perubahan')
                ->withRelations(['nilaiRapor', 'nilaiEkstrakurikuler']),
            DeleteAction::make(),
        ];
    }
}
