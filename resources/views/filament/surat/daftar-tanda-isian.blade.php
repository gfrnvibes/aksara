@php
    /** @var array<string, array<string, string>> $tanda */
@endphp

{{-- Every placeholder a letter may hold. Clicking one copies it, ready to paste into the letter. --}}
<div class="grid gap-4 sm:grid-cols-3">
    @foreach ($tanda as $grup => $daftar)
        <div>
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $grup }}</p>

            <ul class="space-y-1.5">
                @foreach ($daftar as $kode => $keterangan)
                    <li>
                        <button
                            type="button"
                            x-on:click="window.navigator.clipboard.writeText(@js($kode)); $tooltip('Disalin', { theme: $store.theme, timeout: 1500 })"
                            title="Klik untuk menyalin"
                            class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-primary-700 hover:bg-primary-50 dark:bg-white/5 dark:text-primary-400 dark:hover:bg-white/10"
                        >{{ $kode }}</button>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $keterangan }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
