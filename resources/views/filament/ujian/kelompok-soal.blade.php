<x-filament-panels::page>
    @php($kartu = $this->getKartu())

    @if ($kartu === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada mata pelajaran untuk Anda. Bank soal muncul untuk mapel yang Anda ajar di tahun ajaran aktif.</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($kartu as $item)
                <a href="{{ $item['url'] }}" wire:navigate
                   class="{{ $item['warna'] }} group flex gap-3 border border-t-4 border-gray-200 border-t-(--ikon) bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-white/10 dark:border-t-(--ikon) dark:bg-gray-900">
                    <span class="flex size-11 shrink-0 items-center justify-center bg-(--latar) text-(--ikon) dark:bg-white/5" aria-hidden="true">
                        <x-filament::icon :icon="$item['ikon']" class="size-6" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="truncate font-semibold text-gray-950 group-hover:text-(--ikon) dark:text-white" title="{{ $item['nama'] }}">{{ $item['nama'] }}</span>
                            <span class="shrink-0 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $item['total'] }}</span>
                        </span>

                        @if ($item['kelas'] !== [])
                            <span class="mt-1 flex flex-wrap gap-x-2 text-xs text-gray-700 dark:text-gray-300">
                                @foreach ($item['kelas'] as $kelas => $jumlah)
                                    <span>{{ $kelas }} <span class="font-semibold tabular-nums">{{ $jumlah }}</span></span>
                                    @unless ($loop->last)<span class="text-gray-400" aria-hidden="true">·</span>@endunless
                                @endforeach
                            </span>
                        @endif

                        <span class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-600 dark:text-gray-400">
                            <span><span class="font-medium text-success-600 dark:text-success-400">{{ $item['siap'] }}</span> siap</span>
                            <span><span @class(['font-medium', 'text-warning-600 dark:text-warning-400' => $item['draf'] > 0])>{{ $item['draf'] }}</span> draf</span>
                            <span><span class="font-medium text-info-600 dark:text-info-400">{{ $item['dipakai'] }}</span> di ujian</span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
