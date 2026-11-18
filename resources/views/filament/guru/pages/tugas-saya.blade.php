@php
    $data = $this->ringkasan();
@endphp

<x-filament-panels::page>
    <div
        x-data="{
            filter: 'semua',
            cari: '',
            terbuka: null,
            tampil(status, judul, kategori) {
                const cocokStatus =
                    this.filter === 'semua' ||
                    (this.filter === 'perlu' && status !== 'selesai') ||
                    (this.filter === 'selesai' && status === 'selesai') ||
                    (this.filter === 'harian' && kategori === 'Administrasi Harian') ||
                    (this.filter === 'perangkat' && kategori !== 'Administrasi Harian');
                const q = this.cari.trim().toLowerCase();
                const cocokCari = q === '' || judul.toLowerCase().includes(q) || kategori.toLowerCase().includes(q);
                return cocokStatus && cocokCari;
            },
            toggle(id) {
                this.terbuka = this.terbuka === id ? null : id;
            }
        }"
        class="space-y-4"
    >
        {{-- Top compact filter & progress bar --}}
        <div class="border border-gray-950/10 bg-white p-3.5 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach ([
                        ['key' => 'semua', 'label' => 'Semua', 'count' => $data['total']],
                        ['key' => 'perlu', 'label' => 'Perlu Diselesaikan', 'count' => $data['belum']],
                        ['key' => 'harian', 'label' => 'Harian', 'count' => null],
                        ['key' => 'perangkat', 'label' => 'Perangkat & Nilai', 'count' => null],
                        ['key' => 'selesai', 'label' => 'Selesai', 'count' => $data['selesai']],
                    ] as $tab)
                        <button
                            type="button"
                            @click="filter = '{{ $tab['key'] }}'"
                            :class="filter === '{{ $tab['key'] }}'
                                ? 'border-primary-600 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-950/50 dark:text-primary-300'
                                : 'border-gray-950/10 bg-white text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'"
                            class="inline-flex items-center gap-1.5 border px-2.5 py-1 text-xs font-medium transition"
                        >
                            <span>{{ $tab['label'] }}</span>
                            @if ($tab['count'] !== null)
                                <span class="bg-gray-950/5 px-1.5 py-0.2 text-[11px] font-semibold dark:bg-white/10">
                                    {{ $tab['count'] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $data['persen'] }}%</span>
                        <div class="h-1.5 w-24 overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <div class="h-full bg-primary-600 transition-all" style="width: {{ $data['persen'] }}%"></div>
                        </div>
                    </div>

                    <input
                        type="search"
                        x-model="cari"
                        placeholder="Cari tugas..."
                        class="w-44 border border-gray-950/15 bg-white px-2.5 py-1 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none dark:border-white/15 dark:bg-gray-950 dark:text-white"
                    />
                </div>
            </div>
        </div>

        {{-- Interactive timeline list --}}
        <div class="relative ml-3.5 border-l border-gray-200 pl-6 dark:border-gray-800">
            @foreach ($data['items'] as $item)
                @php
                    $cardBg = match ($item['status']) {
                        'selesai' => 'border-success-200/80 bg-success-50/30 dark:border-success-900/40 dark:bg-success-950/10',
                        'sebagian' => 'border-warning-200/90 bg-warning-50/35 dark:border-warning-900/40 dark:bg-warning-950/10',
                        default => 'border-danger-200/80 bg-danger-50/30 dark:border-danger-900/40 dark:bg-danger-950/10',
                    };
                    $nodeStyle = match ($item['status']) {
                        'selesai' => 'border-success-500 bg-success-50 text-success-600 dark:bg-success-950 dark:text-success-400',
                        'sebagian' => 'border-warning-500 bg-warning-50 text-warning-600 dark:bg-warning-950 dark:text-warning-400',
                        default => 'border-danger-400 bg-danger-50 text-danger-600 dark:bg-danger-950 dark:text-danger-400',
                    };
                    $nodeIcon = match ($item['status']) {
                        'selesai' => 'heroicon-m-check',
                        'sebagian' => 'heroicon-m-clock',
                        default => 'heroicon-m-exclamation-triangle',
                    };
                    $badgeStyle = match ($item['status']) {
                        'selesai' => 'border-success-300 bg-success-50 text-success-700 dark:border-success-800 dark:bg-success-950/60 dark:text-success-400',
                        'sebagian' => 'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-800 dark:bg-warning-950/60 dark:text-warning-400',
                        default => 'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-800 dark:bg-danger-950/60 dark:text-danger-400',
                    };
                @endphp

                <div
                    x-show="tampil('{{ $item['status'] }}', @js($item['judul']), @js($item['kategori']))"
                    x-transition
                    class="relative pb-3 last:pb-0"
                >
                    <span class="absolute -left-[35px] top-3 flex h-5 w-5 items-center justify-center rounded-full border {{ $nodeStyle }}">
                        <x-filament::icon :icon="$nodeIcon" class="h-3 w-3" />
                    </span>

                    <div class="border px-3.5 py-2.5 transition hover:border-primary-500/50 {{ $cardBg }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $item['judul'] }}
                                    </span>
                                    <span class="border px-1.5 py-0.5 text-[11px] font-medium {{ $badgeStyle }}">
                                        {{ $item['labelStatus'] }}
                                    </span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        · {{ $item['kategori'] }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                                    {{ $item['ringkasan'] }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                @if ($item['detail'] !== [])
                                    <button
                                        type="button"
                                        @click="toggle('{{ $item['id'] }}')"
                                        class="border border-gray-950/15 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <span x-text="terbuka === '{{ $item['id'] }}' ? 'Tutup' : 'Rincian ({{ count($item['detail']) }})'"></span>
                                    </button>
                                @endif

                                <a
                                    href="{{ $item['url'] }}"
                                    class="bg-primary-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-primary-500"
                                >
                                    {{ $item['labelAksi'] }} →
                                </a>
                            </div>
                        </div>

                        @if ($item['detail'] !== [])
                            <div
                                x-show="terbuka === '{{ $item['id'] }}'"
                                x-collapse
                                x-cloak
                                class="mt-2 grid grid-cols-1 gap-1 border-t border-gray-950/10 pt-2 sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10"
                            >
                                @foreach ($item['detail'] as $baris)
                                    <div class="flex items-center gap-1.5 bg-white/80 px-2 py-1 text-xs dark:bg-gray-900/70">
                                        @if ($baris['selesai'])
                                            <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5 shrink-0 text-success-600 dark:text-success-400" />
                                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $baris['label'] }}</span>
                                        @else
                                            <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5 shrink-0 text-danger-500" />
                                            <span class="truncate font-medium text-gray-900 dark:text-white">{{ $baris['label'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
