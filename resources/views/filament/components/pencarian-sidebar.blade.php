{{-- Filament hides global search when a panel contains only pages. Keep its native field available for menu search. --}}
@if (! filament()->isGlobalSearchEnabled())
    <div x-show="$store.sidebar.isOpen" x-cloak class="-mt-3">
        @livewire(\Filament\Livewire\GlobalSearch::class)
    </div>
@endif
