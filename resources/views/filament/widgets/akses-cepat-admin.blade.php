@php
    $panels = $this->daftarPanel();
    $kartu = $this->kartuAksesCepat();
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3">
        {{-- Compact Panel Switcher --}}
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 shadow-2xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Pindah Panel
                </span>
                @foreach ($panels as $panel)
                    <a
                        href="{{ $panel['url'] }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:border-amber-500/60 hover:bg-amber-50 hover:text-amber-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:border-amber-400/50 dark:hover:bg-amber-500/10 dark:hover:text-amber-300"
                    >
                        <x-filament::icon :icon="$panel['ikon']" class="size-3.5" />
                        <span>{{ $panel['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5">
                <a
                    href="{{ route('home') }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
                >
                    <x-filament::icon icon="heroicon-o-globe-alt" class="size-3.5" />
                    <span>Website</span>
                </a>
                <a
                    href="{{ route('perpustakaan.index') }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
                >
                    <x-filament::icon icon="heroicon-o-book-open" class="size-3.5" />
                    <span>Perpustakaan</span>
                </a>
            </div>
        </div>

        {{-- Compact Quick Access Grid --}}
        @if ($kartu !== [])
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($kartu as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="group flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-3 shadow-2xs transition hover:-translate-y-0.5 hover:border-amber-500/60 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-amber-400/50"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs font-medium text-gray-500 group-hover:text-amber-700 dark:text-gray-400 dark:group-hover:text-amber-300">
                                {{ $item['judul'] }}
                            </span>
                            <x-filament::icon :icon="$item['ikon']" class="size-4 shrink-0 text-gray-400 transition group-hover:text-amber-600 dark:text-gray-500 dark:group-hover:text-amber-400" />
                        </div>
                        <div class="mt-1.5 truncate text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                            {{ $item['nilai'] }}
                        </div>
                        <div class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400">
                            {{ $item['keterangan'] }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
