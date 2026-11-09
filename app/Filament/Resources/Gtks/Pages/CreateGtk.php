<?php

namespace App\Filament\Resources\Gtks\Pages;

use App\Filament\Resources\Gtks\GtkResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

class CreateGtk extends CreateRecord
{
    protected static string $resource = GtkResource::class;

    #[Locked]
    public bool $keAkun = false;

    public function simpanDanAturAkun(): void
    {
        $this->keAkun = true;
        $this->create();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->keAkun && GtkResource::canEdit($this->getRecord())) {
            return GtkResource::getUrl('edit', ['record' => $this->getRecord(), 'tab' => 'akun']);
        }

        return parent::getRedirectUrl();
    }
}
