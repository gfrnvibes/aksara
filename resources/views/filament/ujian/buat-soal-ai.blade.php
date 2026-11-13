<x-filament-panels::page>
    <form wire:submit="kirim" class="space-y-4">
        {{ $this->form }}

        <div class="flex gap-2">
            <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="kirim">Buat Draf</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Ujian\Resources\Soals\SoalResource::urlKelompok($this->mapel)" wire:navigate>Batal</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
