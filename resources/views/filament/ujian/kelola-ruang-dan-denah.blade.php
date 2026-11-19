<x-filament-panels::page>
    <style>
        @media (min-width: 1024px) {
            .fi-header {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 1rem;
            }
            .fi-header-actions-ctn {
                margin-left: auto;
                flex-shrink: 0;
            }
            .fi-header-actions-ctn .fi-ac {
                justify-content: flex-end;
                flex-wrap: nowrap;
            }
        }
    </style>
    @php
        $daftarRuang = $this->daftarRuang();
        $ruangAktif = $this->ruangAktif();
    @endphp

    @if ($daftarRuang->isEmpty())
        <x-filament::section>
            <div class="text-center py-8 text-sm text-gray-500">
                Belum ada ruang ujian. Klik tombol <strong>Tambah Ruang</strong> di atas untuk membuat ruang ujian pertama.
            </div>
        </x-filament::section>
    @else
        {{-- Pemilih Ruang --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($daftarRuang as $ruang)
                @php
                    $aktif = $ruangAktif?->id === $ruang->id;
                @endphp
                <button
                    type="button"
                    wire:click="pilihRuang({{ $ruang->id }})"
                    @class([
                        'px-3 py-1.5 rounded-lg text-sm font-medium border transition',
                        'bg-emerald-600 text-white border-emerald-600' => $aktif,
                        'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-50' => ! $aktif,
                    ])
                >
                    {{ $ruang->kode }} · {{ $ruang->nama }}
                    <span class="ml-1 text-xs opacity-80">({{ $ruang->penempatan->count() }}/{{ $ruang->kapasitas }})</span>
                </button>
            @endforeach
        </div>

        @if ($ruangAktif)
            @php
                $kursiPerMeja = max(1, $ruangAktif->kursi_per_meja);
                $jumlahMeja = (int) ceil($ruangAktif->kapasitas / $kursiPerMeja);
                $slotMap = $ruangAktif->penempatan->keyBy('nomor_kursi');
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    {{ $ruangAktif->nama }} ({{ $ruangAktif->kode }})
                </x-slot>
                <x-slot name="description">
                    {{ $ruangAktif->jumlah_kolom }} kolom meja · {{ $kursiPerMeja }} kursi per meja · {{ $ruangAktif->penempatan->count() }} dari {{ $ruangAktif->kapasitas }} kursi terisi
                </x-slot>
                <x-slot name="afterHeader">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    icon="heroicon-o-printer"
                                    icon-position="before"
                                >
                                    Cetak Ruang Ini
                                </x-filament::button>
                            </x-slot>

                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    tag="a"
                                    target="_blank"
                                    icon="heroicon-o-identification"
                                    :href="route('ujian.kartu', ['kegiatan' => $this->record, 'ruang_id' => $ruangAktif->id])"
                                >
                                    Kartu Peserta
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item
                                    tag="a"
                                    target="_blank"
                                    icon="heroicon-o-table-cells"
                                    :href="route('ujian.denah', ['kegiatan' => $this->record, 'ruang_id' => $ruangAktif->id])"
                                >
                                    Denah Ruang
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item
                                    tag="a"
                                    target="_blank"
                                    icon="heroicon-o-clipboard-document-check"
                                    :href="route('ujian.daftar-hadir', ['kegiatan' => $this->record, 'ruang_id' => $ruangAktif->id])"
                                >
                                    Daftar Hadir Peserta
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item
                                    tag="a"
                                    target="_blank"
                                    icon="heroicon-o-document-duplicate"
                                    :href="route('ujian.semuanya', ['kegiatan' => $this->record, 'ruang_id' => $ruangAktif->id])"
                                >
                                    Semuanya
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                        @if ($this->bolehKelola())
                            {{ ($this->editRuangAction)(['ruang' => $ruangAktif->id]) }}
                            {{ ($this->hapusRuangAction)(['ruang' => $ruangAktif->id]) }}
                        @endif
                    </div>
                </x-slot>

                <div class="mb-4 flex justify-center">
                    <div class="px-6 py-2 border-2 border-gray-400 dark:border-gray-600 rounded-md text-xs font-semibold tracking-wider uppercase bg-gray-50 dark:bg-gray-800">
                        Meja Pengawas / Papan Tulis
                    </div>
                </div>

                <div
                    class="grid gap-3"
                    style="grid-template-columns: repeat({{ max(1, $ruangAktif->jumlah_kolom) }}, minmax(0, 1fr));"
                >
                    @for ($meja = 0; $meja < $jumlahMeja; $meja++)
                        <div
                            class="border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 grid"
                            style="grid-template-columns: repeat({{ $kursiPerMeja }}, minmax(0, 1fr));"
                        >
                            @for ($k = 1; $k <= $kursiPerMeja; $k++)
                                @php
                                    $nomorKursi = ($meja * $kursiPerMeja) + $k;
                                    $peserta = $slotMap->get($nomorKursi);
                                @endphp
                                @if ($nomorKursi <= $ruangAktif->kapasitas)
                                    <div @class([
                                        'p-2 text-xs flex flex-col justify-between min-h-20',
                                        'border-l border-dashed border-gray-300 dark:border-gray-700' => $k > 1,
                                        'bg-emerald-50/40 dark:bg-emerald-950/20' => $peserta !== null,
                                        'bg-gray-50/60 dark:bg-gray-800/40 text-gray-400' => $peserta === null,
                                    ])>
                                        <div class="flex items-center justify-between gap-1 border-b border-gray-200 dark:border-gray-700 pb-1 mb-1">
                                            <span class="px-1.5 py-0.5 rounded bg-gray-800 text-white text-[10px] font-bold">
                                                {{ str_pad((string) $nomorKursi, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                            <span class="font-mono text-[10px] font-semibold truncate">
                                                {{ $peserta?->nomor_peserta ?? '-' }}
                                            </span>
                                        </div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100 leading-tight line-clamp-2">
                                            {{ $peserta?->siswa?->nama_lengkap ?? 'Kosong' }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $peserta?->kelas?->nama ?? '' }}
                                        </div>
                                    </div>
                                @endif
                            @endfor
                        </div>
                    @endfor
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
