<?php

namespace App\Filament\Resources\Gtks\Pages;

use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\Gtks\GtkResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Resources\Pages\EditRecord;

class EditGtk extends EditRecord
{
    protected static string $resource = GtkResource::class;

    protected function getHeaderActions(): array
    {
        return [ActivityTimelineAction::make()->label('Riwayat Perubahan'), HapusMasterDataActions::hapus()];
    }
}
