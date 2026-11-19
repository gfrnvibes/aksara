<x-filament-panels::page>
    @php
        $tahun = $this->tahunAktif();
        $kesiapan = $this->kesiapan();
        $dokMap = $this->dokumenMap();
        $buktiMap = $this->buktiCountMap();
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Kesiapan Keseluruhan</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $kesiapan['persen_keseluruhan'] }}%</div>
            <div class="text-xs text-gray-500 mt-1">Tahun Ajaran {{ $tahun?->nama ?? '-' }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">6 Dokumen Wajib Sispena</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['dw_selesai'] }} / {{ $kesiapan['dw_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">KSP, RKT, RKAS, Kalender, Modul, Foto</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Narasi DKA 14 Butir</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['dka_selesai'] }} / {{ $kesiapan['dka_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Komponen 1, 2, dan 3 IASP</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500 uppercase font-semibold">Bukti Dukung Ter-tag</div>
            <div class="text-2xl font-bold mt-1">{{ $kesiapan['bukti_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Otomatis &amp; unggahan manual</div>
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
