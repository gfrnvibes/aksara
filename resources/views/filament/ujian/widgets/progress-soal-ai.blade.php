<x-filament-widgets::widget>
    @php
        $status = $this->getStatus();
    @endphp

    @if ($status !== null)
        <div wire:poll.2s="cekProgres" class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        Membuat soal AI · {{ $status['label'] }}
                    </span>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $status['selesai'] }} / {{ $status['total'] }} baris ({{ $status['persen'] }}%)
                    </span>
                    @if ($status['gagal'] > 0)
                        <span class="rounded-md bg-danger-50 px-2 py-0.5 font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                            {{ $status['gagal'] }} gagal
                        </span>
                    @endif
                    <button
                        type="button"
                        wire:click="sembunyikan"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        title="Sembunyikan indikator"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                <div
                    class="h-full rounded-full transition-all duration-500 {{ $status['gagal'] > 0 ? 'bg-warning-500' : 'bg-primary-600' }}"
                    style="width: {{ max(4, $status['persen']) }}%"
                ></div>
            </div>

            <p class="mt-2 text-xs {{ $status['antrean_macet'] ? 'font-medium text-warning-600 dark:text-warning-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $status['estimasi'] }}
            </p>
        </div>
    @endif
</x-filament-widgets::widget>
