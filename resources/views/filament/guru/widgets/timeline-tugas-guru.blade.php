@php
    $data = $this->ringkasan();
@endphp

<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <div class="space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                            Ringkasan Tugas Administrasi
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $data['selesai'] }}/{{ $data['total'] }} selesai ({{ $data['persen'] }}%)
                            @if ($data['belum'] > 0)
                                · <span class="font-medium text-danger-600 dark:text-danger-400">{{ $data['belum'] }} perlu diselesaikan</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden h-1.5 w-28 overflow-hidden bg-gray-100 sm:block dark:bg-gray-800">
                        <div class="h-full bg-primary-600 transition-all" style="width: {{ $data['persen'] }}%"></div>
                    </div>

                    <a
                        href="{{ \App\Filament\Guru\Pages\TugasSaya::getUrl() }}"
                        class="inline-flex items-center gap-1 border border-primary-600 bg-primary-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-primary-500"
                    >
                        Buka Timeline Tugas →
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($data['items'] as $item)
                    @php
                        $dot = match ($item['status']) {
                            'selesai' => 'bg-success-500',
                            'sebagian' => 'bg-warning-500',
                            default => 'bg-danger-500',
                        };
                        $badge = match ($item['status']) {
                            'selesai' => 'text-success-700 bg-success-50 dark:text-success-400 dark:bg-success-950/50',
                            'sebagian' => 'text-warning-700 bg-warning-50 dark:text-warning-400 dark:bg-warning-950/50',
                            default => 'text-danger-700 bg-danger-50 dark:text-danger-400 dark:bg-danger-950/50',
                        };
                    @endphp

                    <a
                        href="{{ $item['url'] }}"
                        class="flex items-center justify-between gap-2 border border-gray-950/10 bg-white px-3 py-2 text-xs transition hover:border-primary-500/50 dark:border-white/10 dark:bg-gray-900"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                            <span class="truncate font-medium text-gray-900 dark:text-white">{{ $item['judul'] }}</span>
                        </div>
                        <span class="shrink-0 px-1.5 py-0.5 text-[11px] font-medium {{ $badge }}">
                            {{ $item['labelStatus'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
