@php
    /** @var \App\Models\PresensiGtk $record */
@endphp

<div class="space-y-3">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
        <img
            src="{{ route('presensi-gtk.foto', $record) }}"
            alt="Foto presensi {{ $record->gtk->nama }}"
            class="max-h-96 w-full object-contain"
        />
    </div>

    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300">
        <div>
            <span class="font-semibold">Jarak:</span>
            {{ $record->jarak_meter !== null ? $record->jarak_meter . ' m' : '—' }}
        </div>
        <div>
            <span class="font-semibold">Akurasi GPS:</span>
            {{ $record->akurasi_meter !== null ? '±' . $record->akurasi_meter . ' m' : '—' }}
        </div>
        @if ($record->latitude !== null && $record->longitude !== null)
            <div class="col-span-2">
                <span class="font-semibold">Koordinat:</span>
                {{ number_format($record->latitude, 6) }}, {{ number_format($record->longitude, 6) }}
            </div>
        @endif
    </div>
</div>
