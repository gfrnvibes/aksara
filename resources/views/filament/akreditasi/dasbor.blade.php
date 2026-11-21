<x-filament-panels::page>
    @php
        $tahun = $this->tahunAktif();
        $kesiapan = $this->kesiapan();
        $dokMap = $this->dokumenMap();
        $buktiMap = $this->buktiCountMap();
        $progresAi = $this->statusProgresAi();
    @endphp

    <div
        wire:loading.flex
        wire:target="callMountedAction"
        class="flex-col gap-2 rounded-xl border border-primary-200 bg-primary-50/70 p-4 shadow-xs dark:border-primary-800/60 dark:bg-primary-950/30"
    >
        <div class="flex items-center gap-2">
            <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                Menyiapkan antrean generate draf AI...
            </span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
            <div class="h-full w-1/3 animate-pulse rounded-full bg-primary-600"></div>
        </div>
    </div>

    @if ($progresAi !== null)
        <div wire:poll.2s="cekProgresAi" class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        Menyusun Draf Dokumen Akreditasi AI
                    </span>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $progresAi['selesai'] }} / {{ $progresAi['total'] }} dokumen ({{ $progresAi['persen'] }}%)
                    </span>
                    @if ($progresAi['gagal'] > 0)
                        <span class="rounded-md bg-danger-50 px-2 py-0.5 font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                            {{ $progresAi['gagal'] }} gagal
                        </span>
                    @endif
                    <button
                        type="button"
                        wire:click="sembunyikanProgresAi"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        title="Sembunyikan indikator"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                <div
                    class="h-full rounded-full transition-all duration-500 {{ $progresAi['gagal'] > 0 ? 'bg-warning-500' : 'bg-primary-600' }}"
                    style="width: {{ max(4, $progresAi['persen']) }}%"
                ></div>
            </div>

            <p class="mt-2 text-xs {{ $progresAi['antrean_macet'] ? 'font-medium text-warning-600 dark:text-warning-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $progresAi['estimasi'] }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Kesiapan Keseluruhan</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $kesiapan['persen_keseluruhan'] }}%</div>
            <div class="text-xs text-gray-500 mt-1">
                TA {{ $tahun?->nama ?? '-' }} · Admin Guru {{ $kesiapan['persen_admin_guru'] }}%
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">6 Dokumen Wajib Sispena</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['dw_selesai'] }} / {{ $kesiapan['dw_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $kesiapan['dw_final'] }} berstatus final</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Narasi DKA 14 Butir</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['dka_selesai'] }} / {{ $kesiapan['dka_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $kesiapan['dka_final'] }} berstatus final</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Bukti Dukung Ter-tag</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['bukti_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $kesiapan['butir_terpenuhi'] }} / 14 butir terisi bukti</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Lapisan 1: 6 Dokumen Wajib (DW) Pra-Visitasi Sispena</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach (\App\Services\Akreditasi\PetaBanPdm::dokumenWajib() as $dw)
                @php
                    $dok = $dokMap->get($dw['kode']);
                    $st = $dok?->status ?? 'belum';
                @endphp
                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 flex flex-col justify-between gap-2">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-mono font-bold text-gray-500">{{ strtoupper($dw['kode']) }}</span>
                            <span @class([
                                'px-2 py-0.5 rounded text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700' => $st === 'final',
                                'bg-amber-100 text-amber-700' => $st === 'draf',
                                'bg-gray-100 text-gray-600' => $st === 'belum',
                            ])>{{ strtoupper($st) }}</span>
                        </div>
                        <div class="font-semibold text-sm mt-1">{{ $dw['judul'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $dw['deskripsi'] }}</div>
                    </div>
                    @if ($dok)
                        <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <a href="{{ \App\Filament\Akreditasi\Resources\AkreditasiDokumens\AkreditasiDokumenResource::getUrl('edit', ['record' => $dok]) }}" class="text-xs font-semibold text-emerald-600 hover:underline">Buka &amp; Edit</a>
                            <a href="{{ route('akreditasi.dokumen.cetak', $dok) }}" target="_blank" class="text-xs font-semibold text-gray-600 hover:underline ml-auto">Cetak PDF</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Lapisan 2: Pemetaan 14 Butir Instrumen BAN-PDM</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-xs uppercase text-gray-500">
                        <th class="py-2 pr-3">No</th>
                        <th class="py-2 pr-3">Indikator &amp; Bukti Kunci</th>
                        <th class="py-2 pr-3">Komponen</th>
                        <th class="py-2 pr-3">DKA</th>
                        <th class="py-2 pr-3">Bukti</th>
                        <th class="py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (\App\Services\Akreditasi\PetaBanPdm::butir14() as $b)
                        @php
                            $dok = $dokMap->get($b['kode']);
                            $st = $dok?->status ?? 'belum';
                            $jmlBukti = $buktiMap[$b['kode']] ?? 0;
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-3 font-bold">{{ $b['nomor'] }}</td>
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ $b['judul'] }}</div>
                                <div class="text-xs text-gray-500">{{ $b['bukti_kunci'] }}</div>
                            </td>
                            <td class="py-2 pr-3 text-xs text-gray-600">Komponen {{ $b['komponen'] }}</td>
                            <td class="py-2 pr-3">
                                <span @class([
                                    'px-2 py-0.5 rounded text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $st === 'final',
                                    'bg-amber-100 text-amber-700' => $st === 'draf',
                                    'bg-gray-100 text-gray-600' => $st === 'belum',
                                ])>{{ strtoupper($st) }}</span>
                            </td>
                            <td class="py-2 pr-3 text-xs font-semibold">{{ $jmlBukti }} item</td>
                            <td class="py-2 text-xs">
                                @if ($dok)
                                    <a href="{{ \App\Filament\Akreditasi\Resources\AkreditasiDokumens\AkreditasiDokumenResource::getUrl('edit', ['record' => $dok]) }}" class="font-semibold text-emerald-600 hover:underline">Susun DKA</a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
