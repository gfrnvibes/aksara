<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Models\Soal;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSoal extends CreateRecord
{
    protected static string $resource = SoalResource::class;

    /** The subject and grade of the list the teacher came from, used as form defaults. */
    public ?int $mapelAwal = null;

    public ?int $tingkatAwal = null;

    public function mount(): void
    {
        $this->mapelAwal = request()->integer('mapel') ?: null;
        $this->tingkatAwal = request()->integer('tingkat') ?: null;

        parent::mount();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SoalResource::getUrl() => 'Bank Soal',
            ...($this->mapelAwal ? [SoalResource::urlKelompok($this->mapelAwal, $this->tingkatAwal) => SoalResource::labelKelompok($this->mapelAwal)] : []),
            'Tambah Soal',
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => Filament::auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        /** @var Soal $soal */
        $soal = $this->getRecord();

        return SoalResource::urlKelompok($soal->mata_pelajaran_id, $soal->tingkat);
    }
}
