<x-filament-widgets::widget>
    <x-filament::section heading="Jabatan & Akses Cepat" :compact="true">
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->items() as $item)
                <a
                    href="{{ $item['url'] }}"
                    class="group flex items-center gap-3 border border-gray-950/10 bg-white px-3.5 py-2.5 transition hover:border-primary-500/50 hover:bg-primary-50/40 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400/40 dark:hover:bg-primary-950/20"
                >
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400">
                        <x-filament::icon :icon="$item['icon']" class="h-4 w-4" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                {{ $item['jabatan'] }}
                            </span>
                        </div>
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $item['judul'] }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $item['deskripsi'] }}
                        </p>
                    </div>

                    <x-filament::icon
                        icon="heroicon-m-chevron-right"
                        class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-600 dark:group-hover:text-primary-400"
                    />
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
