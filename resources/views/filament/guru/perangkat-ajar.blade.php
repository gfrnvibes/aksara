<x-filament-panels::page>
    <div class="space-y-6">

        {{-- BARIS 1: TOOLBAR PILIHAN TAHUN AJARAN, KELAS, MAPEL & TOMBOL GENERATE --}}
        <x-filament::section compact>
            <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                <div>
                    <label class="fi-fo-field-wrp-label mb-1.5 block text-sm font-medium text-gray-950 dark:text-white">
                        Tahun Ajaran
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="tahun_ajaran_id">
                            @foreach ($this->opsiTahunAjaran as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label mb-1.5 block text-sm font-medium text-gray-950 dark:text-white">
                        Kelas Binaan / Ajar
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="kelas_id">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($this->opsiKelas as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label mb-1.5 block text-sm font-medium text-gray-950 dark:text-white">
                        Mata Pelajaran
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="mata_pelajaran_id">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach ($this->opsiMataPelajaran as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <x-filament::button
                        wire:click="generateDanSinkronkan"
                        wire:target="generateDanSinkronkan"
                        icon="heroicon-m-sparkles"
                        class="btn-ai-generate w-full"
                    >
                        <span wire:loading.remove wire:target="generateDanSinkronkan">Generate & Sinkronkan</span>
                        <span wire:loading wire:target="generateDanSinkronkan">Menyusun AI...</span>
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- BARIS 2: KARTU IDENTITAS & PARAMETER --}}
        <x-filament::section
            collapsible
            collapsed
            compact
            icon="heroicon-o-identification"
            heading="Identitas & Parameter Kurikulum"
        >
            <x-slot name="afterHeader">
                <div class="flex items-center gap-2" @click.stop>
                    <x-filament::icon-button
                        icon="heroicon-o-information-circle"
                        color="gray"
                        size="sm"
                        label="Ringkasan parameter"
                        x-tooltip.raw="{{ $this->satuan_pendidikan . ' · Kelas ' . $this->kelas_teks . ' · Fase ' . $this->fase . ' · ' . $this->jp_per_minggu . ' JP/Minggu' }}"
                    />
                    <x-filament::button
                        wire:click="simpanIdentitas"
                        wire:target="simpanIdentitas"
                        icon="heroicon-o-bookmark"
                        color="gray"
                        size="sm"
                    >
                        Simpan Identitas
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Satuan Pendidikan</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="satuan_pendidikan" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kepala Madrasah</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="kepala_madrasah" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">NIP Kepala Madrasah</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="nip_kepala" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kota Titimangsa</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="kota_titimangsa" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Guru Pengampu</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="guru_pengampu" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">NIP Guru</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="nip_guru" type="text" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Tanggal Titimangsa</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="tanggal_titimangsa" type="date" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Fase Kurikulum</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.defer="fase">
                                <option value="A">Fase A (Kelas 1-2)</option>
                                <option value="B">Fase B (Kelas 3-4)</option>
                                <option value="C">Fase C (Kelas 5-6)</option>
                                <option value="D">Fase D (Kelas 7-9 MTs/SMP)</option>
                                <option value="E">Fase E (Kelas 10 MA/SMA)</option>
                                <option value="F">Fase F (Kelas 11-12 MA/SMA)</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Alokasi JP per Minggu</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model.defer="jp_per_minggu" type="number" min="1" max="10" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- BARIS 3: TAB INTERAKTIF FILAMENT 5 --}}
        <div class="flex items-center gap-1.5">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Dokumen Perangkat Pembelajaran
            </h2>
            <x-filament::icon-button
                icon="heroicon-o-information-circle"
                color="gray"
                size="sm"
                label="Informasi Perangkat Pembelajaran"
                x-tooltip.raw="Kelola AME, ATP, PROTA, PROSEM, dan Modul Ajar Kurikulum Merdeka dalam satu alur kerja."
            />
        </div>

        <x-filament::tabs>
            <x-filament::tabs.item
                :active="$activeTab === 'ame'"
                wire:click="setTab('ame')"
                icon="heroicon-o-calendar-days"
            >
                1. Analisis Minggu Efektif (AME)
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'atp'"
                wire:click="setTab('atp')"
                icon="heroicon-o-map"
            >
                2. Alur Tujuan Pembelajaran (ATP)
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'prota'"
                wire:click="setTab('prota')"
                icon="heroicon-o-table-cells"
            >
                3. Program Tahunan (PROTA)
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'prosem'"
                wire:click="setTab('prosem')"
                icon="heroicon-o-clipboard-document-list"
            >
                4. Program Semester (PROSEM)
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'modul'"
                wire:click="setTab('modul')"
                icon="heroicon-o-book-open"
                :badge="count($this->modul_ajars) > 0 ? count($this->modul_ajars) : null"
                badge-color="primary"
            >
                5. Modul Ajar
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- ISI TAB 1: ANALISIS MINGGU EFEKTIF (AME) --}}
        @if ($activeTab === 'ame')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ([
                    ['key' => 'semester_1', 'judul' => 'Semester 1 (Juli – Desember)', 'labelTotal' => 'Jumlah Semester 1'],
                    ['key' => 'semester_2', 'judul' => 'Semester 2 (Januari – Juni)', 'labelTotal' => 'Jumlah Semester 2'],
                ] as $semConfig)
                    @php
                        $rows = $data_ame[$semConfig['key']] ?? [];
                        $totJml = 0; $totTdk = 0; $totEfk = 0;
                    @endphp
                    <x-filament::section compact :heading="$semConfig['judul']">
                        <div class="overflow-x-auto -mx-4 -mb-4">
                            <table class="fi-ta-table">
                                <thead>
                                    <tr>
                                        <th class="fi-ta-header-cell w-10">No</th>
                                        <th class="fi-ta-header-cell">Bulan</th>
                                        <th class="fi-ta-header-cell text-center">Jml Mgg</th>
                                        <th class="fi-ta-header-cell text-center">Tdk Efektif</th>
                                        <th class="fi-ta-header-cell text-center">Efektif</th>
                                        <th class="fi-ta-header-cell">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rows as $i => $row)
                                        @php
                                            $totJml += (int)($row['jml_minggu'] ?? 0);
                                            $totTdk += (int)($row['mgg_tidak_efektif'] ?? 0);
                                            $totEfk += (int)($row['mgg_efektif'] ?? 0);
                                        @endphp
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs font-mono text-gray-500">{{ $i + 1 }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-sm font-medium text-gray-950 dark:text-white">{{ $row['bulan'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-sm font-mono">{{ $row['jml_minggu'] ?? 0 }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-sm font-mono text-danger-600 dark:text-danger-400">{{ $row['mgg_tidak_efektif'] ?? 0 }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-sm font-mono font-semibold text-primary-600 dark:text-primary-400">{{ $row['mgg_efektif'] ?? 0 }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs text-gray-600 dark:text-gray-400">{{ $row['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Belum ada data AME. Klik tombol <strong>Generate & Sinkronkan</strong> di atas.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (!empty($rows))
                                    <tfoot>
                                        <tr class="fi-ta-summary-row bg-gray-50 dark:bg-white/5 font-semibold">
                                            <td colspan="2" class="fi-ta-cell px-3 py-2.5 text-xs">{{ $semConfig['labelTotal'] }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-xs font-mono">{{ $totJml }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-xs font-mono text-danger-600 dark:text-danger-400">{{ $totTdk }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-xs font-mono text-primary-600 dark:text-primary-400">{{ $totEfk }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs font-mono text-primary-600 dark:text-primary-400">
                                                {{ $totEfk * $this->jp_per_minggu }} JP ({{ $totEfk }} mgg × {{ $this->jp_per_minggu }} JP)
                                            </td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        {{-- ISI TAB 2: ALUR TUJUAN PEMBELAJARAN (ATP) --}}
        @if ($activeTab === 'atp')
            <div class="fi-ta-ctn">
                <div class="fi-ta-content">
                    <table class="fi-ta-table">
                        <thead>
                            <tr>
                                <th class="fi-ta-header-cell w-12 text-center">No</th>
                                <th class="fi-ta-header-cell w-24">Sem / TP</th>
                                <th class="fi-ta-header-cell w-40">Elemen CP</th>
                                <th class="fi-ta-header-cell">Capaian Pembelajaran (CP)</th>
                                <th class="fi-ta-header-cell">Materi Pokok</th>
                                <th class="fi-ta-header-cell">Tujuan Pembelajaran (TP)</th>
                                <th class="fi-ta-header-cell w-24 text-center">Alokasi JP</th>
                                <th class="fi-ta-header-cell w-32">Asesmen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($master_tp as $index => $tp)
                                <tr class="fi-ta-row">
                                    <td class="fi-ta-cell px-3 py-3 text-center text-xs font-mono text-gray-500">{{ $index + 1 }}</td>
                                    <td class="fi-ta-cell px-3 py-3 align-top">
                                        <x-filament::badge color="info" size="sm">
                                            Sem {{ $tp['semester'] ?? 1 }}
                                        </x-filament::badge>
                                        <div class="mt-1 font-mono text-xs font-semibold text-gray-950 dark:text-white">{{ $tp['kode_tp'] ?? '-' }}</div>
                                    </td>
                                    <td class="fi-ta-cell px-3 py-3 align-top text-xs font-semibold text-gray-950 dark:text-white">{{ $tp['elemen_cp'] ?? '-' }}</td>
                                    <td class="fi-ta-cell px-3 py-3 align-top text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $tp['capaian_pembelajaran'] ?? '-' }}</td>
                                    <td class="fi-ta-cell px-3 py-3 align-top text-xs font-medium text-primary-700 dark:text-primary-400">{{ $tp['materi_pokok'] ?? '-' }}</td>
                                    <td class="fi-ta-cell px-3 py-3 align-top text-xs leading-relaxed text-gray-800 dark:text-gray-200">{{ $tp['tujuan_pembelajaran'] ?? '-' }}</td>
                                    <td class="fi-ta-cell px-3 py-3 text-center font-mono text-xs font-semibold text-gray-950 dark:text-white whitespace-nowrap">{{ $tp['alokasi_jp'] ?? 0 }} JP</td>
                                    <td class="fi-ta-cell px-3 py-3 align-top text-xs text-gray-500 dark:text-gray-400">{{ $tp['jenis_asesmen'] ?? 'Formatif & Sumatif' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada Tujuan Pembelajaran yang disusun. Klik tombol <strong>Generate & Sinkronkan</strong> di atas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (!empty($master_tp))
                            <tfoot>
                                <tr class="fi-ta-summary-row bg-gray-50 dark:bg-white/5 font-semibold">
                                    <td colspan="6" class="fi-ta-cell px-3 py-3 text-right text-xs uppercase tracking-wider">Total Alokasi JP Setahun:</td>
                                    <td class="fi-ta-cell px-3 py-3 text-center font-mono text-sm text-primary-600 dark:text-primary-400">{{ $this->totalJpTp }} JP</td>
                                    <td class="fi-ta-cell"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif

        {{-- ISI TAB 3: PROGRAM TAHUNAN (PROTA) --}}
        @if ($activeTab === 'prota')
            <div class="space-y-6">
                @foreach ([1 => 'Semester 1 (Ganjil)', 2 => 'Semester 2 (Genap)'] as $semNum => $semJudul)
                    @php
                        $tpSem = collect($master_tp)->filter(fn($t) => (int)($t['semester'] ?? 1) === $semNum);
                        $totJpSem = $tpSem->sum('alokasi_jp');
                    @endphp
                    <x-filament::section compact :heading="$semJudul">
                        <x-slot name="afterHeader">
                            <x-filament::badge color="success">
                                Total: {{ $totJpSem }} JP
                            </x-filament::badge>
                        </x-slot>
                        <div class="overflow-x-auto -mx-4 -mb-4">
                            <table class="fi-ta-table">
                                <thead>
                                    <tr>
                                        <th class="fi-ta-header-cell w-12 text-center">No</th>
                                        <th class="fi-ta-header-cell w-24">Kode TP</th>
                                        <th class="fi-ta-header-cell w-44">Elemen CP</th>
                                        <th class="fi-ta-header-cell">Materi Pokok / Lingkup Materi</th>
                                        <th class="fi-ta-header-cell">Tujuan Pembelajaran</th>
                                        <th class="fi-ta-header-cell w-28 text-center">Alokasi Waktu</th>
                                        <th class="fi-ta-header-cell w-32">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tpSem as $tp)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell px-3 py-2.5 text-center text-xs font-mono text-gray-500">{{ $loop->iteration }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 font-mono text-xs font-semibold text-primary-700 dark:text-primary-400">{{ $tp['kode_tp'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs font-medium text-gray-900 dark:text-white">{{ $tp['elemen_cp'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs font-semibold text-gray-950 dark:text-white">{{ $tp['materi_pokok'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ $tp['tujuan_pembelajaran'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center font-mono text-xs font-semibold text-gray-950 dark:text-white">{{ $tp['alokasi_jp'] ?? 0 }} JP</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400">{{ $tp['jenis_asesmen'] ?? 'LM' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada TP pada semester ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($tpSem->isNotEmpty())
                                    <tfoot>
                                        <tr class="fi-ta-summary-row bg-gray-50 dark:bg-white/5 font-semibold">
                                            <td colspan="5" class="fi-ta-cell px-3 py-2.5 text-right text-xs">Jumlah Jam Pelajaran {{ $semJudul }}:</td>
                                            <td class="fi-ta-cell px-3 py-2.5 text-center font-mono text-xs text-primary-600 dark:text-primary-400">{{ $totJpSem }} JP</td>
                                            <td class="fi-ta-cell"></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        {{-- ISI TAB 4: PROGRAM SEMESTER (PROSEM) --}}
        @if ($activeTab === 'prosem')
            <div class="space-y-6">
                @php
                    $bulanSem1List = ['juli' => 'Juli', 'agustus' => 'Agustus', 'september' => 'September', 'oktober' => 'Oktober', 'november' => 'November', 'desember' => 'Desember'];
                    $bulanSem2List = ['januari' => 'Januari', 'februari' => 'Februari', 'maret' => 'Maret', 'april' => 'April', 'mei' => 'Mei', 'juni' => 'Juni'];
                @endphp

                @foreach ([1 => ['judul' => 'Semester 1 (Ganjil)', 'bulanMap' => $bulanSem1List], 2 => ['judul' => 'Semester 2 (Genap)', 'bulanMap' => $bulanSem2List]] as $semNum => $config)
                    @php
                        $tpSem = collect($master_tp)->filter(fn($t) => (int)($t['semester'] ?? 1) === $semNum);
                        $totJpSem = $tpSem->sum('alokasi_jp');
                    @endphp
                    <x-filament::section compact :heading="'Matriks Distribusi Mingguan ' . $config['judul']">
                        <x-slot name="afterHeader">
                            <x-filament::badge color="success">
                                Total: {{ $totJpSem }} JP
                            </x-filament::badge>
                        </x-slot>
                        <div class="overflow-x-auto -mx-4 -mb-4">
                            <table class="fi-ta-table text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="fi-ta-header-cell w-10 text-center">No</th>
                                        <th rowspan="2" class="fi-ta-header-cell w-16 text-center">Kode TP</th>
                                        <th rowspan="2" class="fi-ta-header-cell min-w-[200px]">Materi Pokok / TP</th>
                                        <th rowspan="2" class="fi-ta-header-cell w-16 text-center">JP</th>
                                        @foreach ($config['bulanMap'] as $key => $namaBulan)
                                            @php
                                                $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                                            @endphp
                                            <th colspan="{{ $weekCount }}" class="fi-ta-header-cell text-center">{{ $namaBulan }}</th>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        @foreach ($config['bulanMap'] as $key => $namaBulan)
                                            @php
                                                $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                                            @endphp
                                            @for ($w = 1; $w <= $weekCount; $w++)
                                                <th class="fi-ta-header-cell w-6 px-1 py-1.5 text-center">{{ $w }}</th>
                                            @endfor
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tpSem as $tp)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell px-2 py-2 text-center font-mono text-gray-500">{{ $loop->iteration }}</td>
                                            <td class="fi-ta-cell px-2 py-2 text-center font-mono font-semibold text-primary-700 dark:text-primary-400">{{ $tp['kode_tp'] ?? '-' }}</td>
                                            <td class="fi-ta-cell px-3 py-2">
                                                <div class="font-semibold text-gray-950 dark:text-white">{{ $tp['materi_pokok'] ?? '-' }}</div>
                                                <div class="line-clamp-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $tp['tujuan_pembelajaran'] ?? '' }}</div>
                                            </td>
                                            <td class="fi-ta-cell px-2 py-2 text-center font-mono font-semibold text-gray-950 dark:text-white">{{ $tp['alokasi_jp'] ?? 0 }}</td>
                                            @foreach ($config['bulanMap'] as $key => $namaBulan)
                                                @php
                                                    $weeks = $tp['jadwal_prosem'][$key] ?? [];
                                                    $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                    $weekCount = count($sampleWeeks) ?: count($weeks) ?: 4;
                                                @endphp
                                                @for ($w = 0; $w < $weekCount; $w++)
                                                    @php $jam = (int)($weeks[$w] ?? 0); @endphp
                                                    <td class="fi-ta-cell px-1 py-1.5 text-center font-mono {{ $jam > 0 ? 'bg-primary-50 font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' : 'text-gray-300 dark:text-gray-600' }}">
                                                        {{ $jam > 0 ? $jam : '-' }}
                                                    </td>
                                                @endfor
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="35" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Belum ada rincian PROSEM. Klik tombol <strong>Generate & Sinkronkan</strong> di atas.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        {{-- ISI TAB 5: MODUL AJAR --}}
        @if ($activeTab === 'modul')
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Daftar Modul Ajar Berdasarkan Master Tujuan Pembelajaran</h3>
                        <x-filament::icon-button
                            icon="heroicon-o-information-circle"
                            color="gray"
                            size="sm"
                            label="Informasi Modul Ajar"
                            x-tooltip.raw="Generate modul ajar lengkap 18 komponen, LKPD, dan rubrik penilaian per Tujuan Pembelajaran."
                        />
                    </div>
                    <x-filament::badge color="gray">
                        {{ count($this->modul_ajars) }} dari {{ count($this->master_tp) }} Modul Dibuat
                    </x-filament::badge>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($master_tp as $tp)
                        @php
                            $kode = $tp['kode_tp'] ?? '0';
                            $sudahAda = isset($modul_ajars[$kode]);
                            $sedangProses = $generatingTpCode === $kode;
                        @endphp
                        <x-filament::section compact class="flex h-full flex-col justify-between">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <x-filament::badge color="info">TP {{ $kode }}</x-filament::badge>
                                    @if ($sudahAda)
                                        <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                            Modul Siap
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">
                                            Belum Di-generate
                                        </x-filament::badge>
                                    @endif
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold leading-snug text-gray-950 dark:text-white">{{ $tp['materi_pokok'] ?? '-' }}</h4>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Elemen: {{ $tp['elemen_cp'] ?? '-' }}</p>
                                </div>

                                <p class="line-clamp-2 text-xs leading-relaxed text-gray-700 dark:text-gray-300">
                                    {{ $tp['tujuan_pembelajaran'] ?? '-' }}
                                </p>

                                <div class="flex items-center gap-2 border-t border-gray-100 pt-2 font-mono text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                                    <span>Alokasi: <strong class="text-gray-900 dark:text-white">{{ $tp['alokasi_jp'] ?? 0 }} JP</strong></span>
                                    <span>·</span>
                                    <span>Semester {{ $tp['semester'] ?? 1 }}</span>
                                </div>
                            </div>

                            <x-slot name="footer">
                                <div class="flex items-center gap-2">
                                    @if ($sudahAda)
                                        <x-filament::button
                                            wire:click="bukaModulAjar('{{ $kode }}')"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-eye"
                                            class="flex-1"
                                        >
                                            Lihat Modul
                                        </x-filament::button>

                                        <x-filament::button
                                            tag="a"
                                            :href="route('perangkat-ajar.cetak-satuan', ['perangkatAjar' => $this->perangkatAjarId ?: 0, 'tab' => 'modul', 'kodeTp' => $kode])"
                                            target="_blank"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-printer"
                                        >
                                            Cetak
                                        </x-filament::button>

                                        <x-filament::icon-button
                                            wire:click="generateModulAjarItem('{{ $kode }}')"
                                            wire:target="generateModulAjarItem('{{ $kode }}')"
                                            icon="heroicon-o-arrow-path"
                                            color="gray"
                                            size="sm"
                                            label="Re-generate Modul"
                                        />
                                    @else
                                        <x-filament::button
                                            wire:click="generateModulAjarItem('{{ $kode }}')"
                                            wire:target="generateModulAjarItem('{{ $kode }}')"
                                            icon="heroicon-m-sparkles"
                                            size="sm"
                                            class="btn-ai-generate w-full"
                                        >
                                            @if ($sedangProses)
                                                Menyusun Modul...
                                            @else
                                                Generate Modul Ajar
                                            @endif
                                        </x-filament::button>
                                    @endif
                                </div>
                            </x-slot>
                        </x-filament::section>
                    @empty
                        <div class="col-span-full py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            Belum ada Tujuan Pembelajaran yang dimuat. Klik tombol <strong>Generate & Sinkronkan</strong> di atas untuk menyusun ATP & TP.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- BARIS PALING BAWAH: RINGKASAN & SINKRONISASI JP REAL-TIME --}}
        <x-filament::section
            compact
            icon="heroicon-o-calculator"
            heading="Ringkasan & Sinkronisasi Alokasi JP"
        >
            <x-slot name="afterHeader">
                <div class="flex items-center gap-2">
                    <x-filament::icon-button
                        icon="heroicon-o-information-circle"
                        color="gray"
                        size="sm"
                        label="Informasi ringkasan JP"
                        x-tooltip.raw="Perbandingan total jam pelajaran antara Analisis Minggu Efektif (AME), ATP/PROTA, dan Matriks PROSEM."
                    />
                    @if ($this->isSinkron)
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">
                            Sinkron Mutlak ({{ $this->totalJpAme }} JP)
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
                            Belum Sinkron / Belum Di-generate
                        </x-filament::badge>
                    @endif
                </div>
            </x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="border border-gray-200 bg-gray-50/50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Analisis Minggu Efektif (AME)
                    </div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $this->totalJpAme }} JP
                    </div>
                    <div class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                        Sem 1: <strong>{{ $this->totalJpAmeSem1 }} JP</strong> · Sem 2: <strong>{{ $this->totalJpAmeSem2 }} JP</strong>
                    </div>
                </div>

                <div class="border border-gray-200 bg-gray-50/50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        ATP &amp; PROTA
                    </div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $this->totalJpTp }} JP
                    </div>
                    <div class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                        Sem 1: <strong>{{ $this->totalJpTpSem1 }} JP</strong> · Sem 2: <strong>{{ $this->totalJpTpSem2 }} JP</strong>
                    </div>
                </div>

                <div class="border border-gray-200 bg-gray-50/50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Matriks PROSEM
                    </div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $this->totalJpProsem }} JP
                    </div>
                    <div class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                        Sem 1: <strong>{{ $this->totalJpProsemSem1 }} JP</strong> · Sem 2: <strong>{{ $this->totalJpProsemSem2 }} JP</strong>
                    </div>
                </div>

                <div class="border border-gray-200 bg-gray-50/50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Kelengkapan Modul
                    </div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-950 dark:text-white">
                        {{ count($this->modul_ajars) }} / {{ count($this->master_tp) }} Modul
                    </div>
                    <div class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                        Total <strong>{{ count($this->master_tp) }}</strong> Tujuan Pembelajaran
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- MODAL PREVIEW MODUL AJAR (18 KOMPONEN LENGKAP) --}}
        @if ($modalModulOpen && $activeModulData)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4 backdrop-blur-xs">
                <div class="fi-modal-window flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                    {{-- Header Modal --}}
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/70 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                        <div>
                            <x-filament::badge color="primary">
                                Modul Ajar TP {{ $activeModulData['identitas']['kode_tp'] ?? $selectedModulTp }}
                            </x-filament::badge>
                            <h2 class="mt-1.5 text-base font-bold text-gray-950 dark:text-white">{{ $activeModulData['identitas']['materi_pokok'] ?? '-' }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::button
                                tag="a"
                                :href="route('perangkat-ajar.cetak-satuan', ['perangkatAjar' => $this->perangkatAjarId ?: 0, 'tab' => 'modul', 'kodeTp' => $selectedModulTp])"
                                target="_blank"
                                color="primary"
                                size="sm"
                                icon="heroicon-o-printer"
                            >
                                Cetak Modul Ajar
                            </x-filament::button>

                            <x-filament::icon-button
                                wire:click="tutupModulAjar"
                                icon="heroicon-o-x-mark"
                                color="gray"
                                size="sm"
                                label="Tutup"
                            />
                        </div>
                    </div>

                    {{-- Isi Modal (18 Komponen) --}}
                    <div class="flex-1 space-y-5 overflow-y-auto p-6 text-xs leading-relaxed text-gray-800 dark:text-gray-200">
                        {{-- 1. IDENTITAS --}}
                        <x-filament::section compact heading="I. Informasi Umum & Identitas">
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div><span class="text-gray-500 dark:text-gray-400">Satuan Pendidikan:</span><div class="font-semibold text-gray-950 dark:text-white">{{ $activeModulData['identitas']['satuan_pendidikan'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Mata Pelajaran:</span><div class="font-semibold text-gray-950 dark:text-white">{{ $activeModulData['identitas']['mata_pelajaran'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Kelas / Fase:</span><div class="font-semibold text-gray-950 dark:text-white">{{ $activeModulData['identitas']['kelas_fase'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Alokasi Waktu:</span><div class="font-semibold text-gray-950 dark:text-white">{{ $activeModulData['identitas']['alokasi_waktu_teks'] ?? '-' }}</div></div>
                            </div>
                        </x-filament::section>

                        {{-- 2. KOMPETENSI AWAL & P5RA --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-filament::section compact heading="Kompetensi Awal">
                                <ul class="list-disc space-y-1 pl-4 text-gray-600 dark:text-gray-300">
                                    @foreach ((array)($activeModulData['kompetensi_awal'] ?? []) as $ka)
                                        <li>{{ $ka }}</li>
                                    @endforeach
                                </ul>
                            </x-filament::section>
                            <x-filament::section compact heading="Profil Pelajar Pancasila & Rahmatan Lil Alamin (P5RA)">
                                <ul class="list-disc space-y-1 pl-4 text-gray-600 dark:text-gray-300">
                                    @foreach ((array)($activeModulData['profil_p5ra'] ?? []) as $p5)
                                        <li><strong>{{ $p5['dimensi'] ?? '' }}:</strong> {{ $p5['deskripsi'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            </x-filament::section>
                        </div>

                        {{-- 3. SARPRAS & MODEL PEMBELAJARAN --}}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-filament::section compact heading="Sarana dan Prasarana">
                                <p><strong>Media:</strong> {{ $activeModulData['sarana_prasarana']['media'] ?? '-' }}</p>
                                <p><strong>Alat:</strong> {{ $activeModulData['sarana_prasarana']['alat'] ?? '-' }}</p>
                                <p><strong>Sumber Belajar:</strong> {{ $activeModulData['sarana_prasarana']['sumber_belajar'] ?? '-' }}</p>
                            </x-filament::section>
                            <x-filament::section compact heading="Model & Metode Pembelajaran">
                                <p><strong>Model:</strong> {{ $activeModulData['model_metode']['model'] ?? '-' }}</p>
                                <p><strong>Pendekatan:</strong> {{ $activeModulData['model_metode']['pendekatan'] ?? '-' }}</p>
                                <p><strong>Metode:</strong> {{ $activeModulData['model_metode']['metode'] ?? '-' }}</p>
                            </x-filament::section>
                        </div>

                        {{-- 4. KOMPONEN INTI --}}
                        <x-filament::section compact heading="II. Komponen Inti">
                            <div class="space-y-3">
                                <div>
                                    <span class="font-semibold text-gray-950 dark:text-white">Tujuan Pembelajaran:</span>
                                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                        @foreach ((array)($activeModulData['tujuan_pembelajaran'] ?? []) as $tpText)
                                            <li>{{ $tpText }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-950 dark:text-white">Pemahaman Bermakna:</span>
                                    <p class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $activeModulData['pemahaman_bermakna'] ?? '-' }}</p>
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-950 dark:text-white">Pertanyaan Pemantik:</span>
                                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                        @foreach ((array)($activeModulData['pertanyaan_pemantik'] ?? []) as $tanya)
                                            <li>{{ $tanya }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </x-filament::section>

                        {{-- 5. KEGIATAN PEMBELAJARAN PER PERTEMUAN --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">III. Kegiatan Pembelajaran</h4>
                            @foreach ((array)($activeModulData['kegiatan_pembelajaran'] ?? []) as $keg)
                                <x-filament::section compact :heading="'Pertemuan ke-' . ($keg['pertemuan_ke'] ?? 1) . ' (' . ($keg['jp_pertemuan'] ?? 2) . ' JP)'">
                                    <div class="space-y-2">
                                        <div>
                                            <span class="font-semibold text-primary-700 dark:text-primary-400">A. Pendahuluan:</span>
                                            <ul class="mt-0.5 list-disc space-y-0.5 pl-4 text-gray-600 dark:text-gray-300">
                                                @foreach ((array)($keg['pendahuluan'] ?? []) as $pnd)
                                                    <li>{{ $pnd }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-info-700 dark:text-info-400">B. Kegiatan Inti:</span>
                                            <ul class="mt-0.5 list-disc space-y-0.5 pl-4 text-gray-600 dark:text-gray-300">
                                                @foreach ((array)($keg['inti'] ?? []) as $int)
                                                    <li>{{ $int }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-warning-700 dark:text-warning-400">C. Penutup:</span>
                                            <ul class="mt-0.5 list-disc space-y-0.5 pl-4 text-gray-600 dark:text-gray-300">
                                                @foreach ((array)($keg['penutup'] ?? []) as $pnt)
                                                    <li>{{ $pnt }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </x-filament::section>
                            @endforeach
                        </div>

                        {{-- 6. ASESMEN & RUBRIK --}}
                        <x-filament::section compact heading="IV. Asesmen & Rubrik Penilaian">
                            <div class="overflow-x-auto -mx-4 -mb-4">
                                <table class="fi-ta-table text-[11px]">
                                    <thead>
                                        <tr>
                                            <th class="fi-ta-header-cell">Aspek Penilaian</th>
                                            <th class="fi-ta-header-cell">Sangat Baik (4)</th>
                                            <th class="fi-ta-header-cell">Baik (3)</th>
                                            <th class="fi-ta-header-cell">Cukup (2)</th>
                                            <th class="fi-ta-header-cell">Perlu Bimbingan (1)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ((array)($activeModulData['asesmen']['formatif_rubrik'] ?? []) as $rubrik)
                                            <tr class="fi-ta-row">
                                                <td class="fi-ta-cell px-2.5 py-2 font-semibold">{{ $rubrik['aspek'] ?? '-' }}</td>
                                                <td class="fi-ta-cell px-2.5 py-2">{{ $rubrik['sangat_baik'] ?? '-' }}</td>
                                                <td class="fi-ta-cell px-2.5 py-2">{{ $rubrik['baik'] ?? '-' }}</td>
                                                <td class="fi-ta-cell px-2.5 py-2">{{ $rubrik['cukup'] ?? '-' }}</td>
                                                <td class="fi-ta-cell px-2.5 py-2">{{ $rubrik['perlu_bimbingan'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>

                        {{-- 7. LAMPIRAN LKPD --}}
                        <x-filament::section compact heading="V. Lampiran: Lembar Kerja Peserta Didik (LKPD)">
                            <div class="space-y-2">
                                <div class="text-sm font-bold text-primary-700 dark:text-primary-400">{{ $activeModulData['lampiran']['lkpd']['judul'] ?? 'LKPD' }}</div>
                                <p class="text-gray-600 dark:text-gray-300"><strong>Petunjuk:</strong> {{ $activeModulData['lampiran']['lkpd']['petunjuk'] ?? '-' }}</p>
                                @if (!empty($activeModulData['lampiran']['lkpd']['teks_stimulus']))
                                    <div class="border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                                        <strong>Stimulus:</strong> {{ $activeModulData['lampiran']['lkpd']['teks_stimulus'] }}
                                    </div>
                                @endif
                                <div>
                                    <strong>Daftar Tugas / Pertanyaan:</strong>
                                    <ol class="mt-1 list-decimal space-y-1 pl-4">
                                        @foreach ((array)($activeModulData['lampiran']['lkpd']['daftar_tugas'] ?? []) as $tgs)
                                            <li>{{ $tgs }}</li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>
                        </x-filament::section>
                    </div>

                    {{-- Footer Modal --}}
                    <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-white/10 dark:bg-white/5">
                        <x-filament::button wire:click="tutupModulAjar" color="gray" size="sm">
                            Tutup
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
