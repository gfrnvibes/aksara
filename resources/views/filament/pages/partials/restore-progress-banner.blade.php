@php
    $restore = $this->getStatusRestoreTerbaru();
@endphp

@if ($restore !== null)
    <div
        @if ($restore['aktif']) wire:poll.2s @endif
        class="rounded-xl border p-4 shadow-xs {{ match ($restore['status']) {
            'berhasil' => 'border-success-200 bg-success-50/70 dark:border-success-800/60 dark:bg-success-950/30',
            'gagal' => 'border-danger-200 bg-danger-50/70 dark:border-danger-800/60 dark:bg-danger-950/30',
            default => 'border-warning-200 bg-white dark:border-warning-800/60 dark:bg-gray-900',
        } }}"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                @if ($restore['aktif'])
                    <x-filament::loading-indicator class="h-5 w-5 text-warning-600" />
                @elseif ($restore['status'] === 'berhasil')
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-600" />
                @else
                    <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 text-danger-600" />
                @endif

                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $restore['label'] }}
                </span>

                @if ($restore['arsip'] !== '')
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ({{ $restore['arsip'] }})
                    </span>
                @endif
            </div>

            <span class="text-xs font-semibold {{ match ($restore['status']) {
                'berhasil' => 'text-success-700 dark:text-success-400',
                'gagal' => 'text-danger-700 dark:text-danger-400',
                default => 'text-warning-700 dark:text-warning-400',
            } }}">
                {{ $restore['persen'] }}%
            </span>
        </div>

        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
            <div
                class="h-full rounded-full transition-all duration-300 {{ match ($restore['status']) {
                    'berhasil' => 'bg-success-600',
                    'gagal' => 'bg-danger-600',
                    default => 'bg-warning-500',
                } }}"
                style="width: {{ $restore['persen'] }}%"
            ></div>
        </div>

        @if ($restore['status'] === 'gagal' && filled($restore['error']))
            <p class="mt-2 text-xs text-danger-700 dark:text-danger-400">
                {{ $restore['error'] }}
            </p>
        @elseif ($restore['aktif'])
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Aplikasi masuk mode maintenance sementara selama pemulihan database berlangsung. Halaman akan memperbarui status secara otomatis.
            </p>
        @endif
    </div>
@endif
