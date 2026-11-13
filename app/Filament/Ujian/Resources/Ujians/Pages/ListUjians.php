<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Filament\Ujian\Resources\Ujians\UjianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUjians extends ListRecords
{
    protected static string $resource = UjianResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
