<x-filament-panels::page>
    <div class="space-y-6">

        {{-- BARIS 1: TOOLBAR PILIHAN KELAS, MAPEL, TAHUN AJARAN & TOMBOL GENERATE --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tahun Ajaran</label>
                    <select wire:model.live="tahun_ajaran_id" class="w-full rounded-lg border-gray-300 text-sm shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-gray-800 dark:text-white">
                        @foreach ($this->opsiTahunAjaran as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kelas Binaan / Ajar</label>
                    <select wire:model.live="kelas_id" class="w-full rounded-lg border-gray-300 text-sm shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-gray-800 dark:text-white">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($this->opsiKelas as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Mata Pelajaran</label>
                    <select wire:model.live="mata_pelajaran_id" class="w-full rounded-lg border-gray-300 text-sm shadow-xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-gray-800 dark:text-white">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        @foreach ($this->opsiMataPelajaran as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        wire:click="generateDanSinkronkan"
                        wire:loading.attr="disabled"
                        type="button"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-50 transition"
                    >
                        <span wire:loading.remove wire:target="generateDanSinkronkan">⚡ Generate & Sinkronkan</span>
                        <span wire:loading wire:target="generateDanSinkronkan" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyusun AI...
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- BARIS 2: KARTU IDENTITAS & PARAMETER --}}
        <div x-data="{ buka: false }" class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm text-gray-800 dark:text-gray-200">Identitas & Parameter Kurikulum</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ $this->satuan_pendidikan }} • Kelas {{ $this->kelas_teks }} • Fase {{ $this->fase }} • {{ $this->jp_per_minggu }} JP/Minggu)</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        wire:click="simpanIdentitas"
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-xs ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700"
                    >
                        💾 Simpan Identitas
                    </button>
                    <button
                        @click="buka = !buka"
                        type="button"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <span x-text="buka ? '▲ Sembunyikan' : '▼ Ubah Parameter'"></span>
                    </button>
                </div>
            </div>

            <div x-show="buka" x-transition class="p-4 border-t border-gray-100 dark:border-white/5 space-y-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Satuan Pendidikan</label>
                        <input wire:model.defer="satuan_pendidikan" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Kepala Madrasah</label>
                        <input wire:model.defer="kepala_madrasah" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">NIP Kepala Madrasah</label>
                        <input wire:model.defer="nip_kepala" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Kota Titimangsa</label>
                        <input wire:model.defer="kota_titimangsa" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-5">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Guru Pengampu</label>
                        <input wire:model.defer="guru_pengampu" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">NIP Guru</label>
                        <input wire:model.defer="nip_guru" type="text" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Tanggal Titimangsa</label>
                        <input wire:model.defer="tanggal_titimangsa" type="date" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Fase Kurikulum</label>
                        <select wire:model.defer="fase" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                            <option value="A">Fase A (Kelas 1-2)</option>
                            <option value="B">Fase B (Kelas 3-4)</option>
                            <option value="C">Fase C (Kelas 5-6)</option>
                            <option value="D">Fase D (Kelas 7-9 MTs/SMP)</option>
                            <option value="E">Fase E (Kelas 10 MA/SMA)</option>
                            <option value="F">Fase F (Kelas 11-12 MA/SMA)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400">Alokasi JP per Minggu</label>
                        <input wire:model.defer="jp_per_minggu" type="number" min="1" max="10" class="mt-1 w-full rounded-md border-gray-300 text-xs shadow-xs dark:border-white/10 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>
            </div>
        </div>

        {{-- BARIS 3: INDIKATOR SINKRONISASI JP REAL-TIME --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-2.5 text-xs font-medium {{ $this->isSinkron ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300' }}">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 font-bold">
                    @if ($this->isSinkron)
                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                        STATUS JP: SINKRON MUTLAK ({{ $this->totalJpAme }} JP)
                    @else
                        <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                        STATUS JP: BELUM SINKRON / BELUM DI-GENERATE
                    @endif
                </span>
                <span class="text-gray-400 dark:text-gray-600">|</span>
                <span><strong>AME:</strong> Sem 1 ({{ $this->totalJpAmeSem1 }} JP) + Sem 2 ({{ $this->totalJpAmeSem2 }} JP) = <strong>{{ $this->totalJpAme }} JP</strong></span>
                <span class="text-gray-400 dark:text-gray-600">|</span>
                <span><strong>ATP/PROTA:</strong> Sem 1 ({{ $this->totalJpTpSem1 }} JP) + Sem 2 ({{ $this->totalJpTpSem2 }} JP) = <strong>{{ $this->totalJpTp }} JP</strong></span>
                <span class="text-gray-400 dark:text-gray-600">|</span>
                <span><strong>PROSEM Matriks:</strong> Sem 1 ({{ $this->totalJpProsemSem1 }} JP) + Sem 2 ({{ $this->totalJpProsemSem2 }} JP) = <strong>{{ $this->totalJpProsem }} JP</strong></span>
            </div>

            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                Total TP: <strong>{{ count($this->master_tp) }}</strong> • Modul Siap: <strong>{{ count($this->modul_ajars) }}</strong>
            </div>
        </div>

        {{-- BARIS 4: TAB INTERAKTIF --}}
        <div class="border-b border-gray-200 dark:border-white/10">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button
                    wire:click="setTab('ame')"
                    type="button"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold transition {{ $activeTab === 'ame' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}"
                >
                    Tab 1: Analisis Minggu Efektif (AME)
                </button>

                <button
                    wire:click="setTab('atp')"
                    type="button"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold transition {{ $activeTab === 'atp' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}"
                >
                    Tab 2: Alur Tujuan Pembelajaran (ATP)
                </button>

                <button
                    wire:click="setTab('prota')"
                    type="button"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold transition {{ $activeTab === 'prota' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}"
                >
                    Tab 3: Program Tahunan (PROTA)
                </button>

                <button
                    wire:click="setTab('prosem')"
                    type="button"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold transition {{ $activeTab === 'prosem' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}"
                >
                    Tab 4: Program Semester (PROSEM)
                </button>

                <button
                    wire:click="setTab('modul')"
                    type="button"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold transition {{ $activeTab === 'modul' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}"
                >
                    Tab 5: Modul Ajar
                    @if (count($this->modul_ajars) > 0)
                        <span class="ml-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">{{ count($this->modul_ajars) }}</span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- ISI TAB 1: ANALISIS MINGGU EFEKTIF (AME) --}}
        @if ($activeTab === 'ame')
            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Semester 1 --}}
                    <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-white/10 dark:bg-gray-800/50">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Semester 1 (Juli – Desember)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-gray-200 bg-gray-50/20 text-gray-600 dark:border-white/10 dark:text-gray-400">
                                    <tr>
                                        <th class="py-2.5 px-3 font-semibold">No</th>
                                        <th class="py-2.5 px-3 font-semibold">Bulan</th>
                                        <th class="py-2.5 px-3 text-center font-semibold">Jml Mgg</th>
                                        <th class="py-2.5 px-3 text-center font-semibold">Tdk Efektif</th>
                                        <th class="py-2.5 px-3 text-center font-semibold text-emerald-600 dark:text-emerald-400">Efektif</th>
                                        <th class="py-2.5 px-3 font-semibold">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @php
                                        $sem1 = $data_ame['semester_1'] ?? [];
                                        $totJml1 = 0; $totTdk1 = 0; $totEfk1 = 0;
                                    @endphp
                                    @forelse ($sem1 as $i => $row)
                                        @php
                                            $totJml1 += (int)($row['jml_minggu'] ?? 0);
                                            $totTdk1 += (int)($row['mgg_tidak_efektif'] ?? 0);
                                            $totEfk1 += (int)($row['mgg_efektif'] ?? 0);
                                        @endphp
                                        <tr>
                                            <td class="py-2 px-3">{{ $i + 1 }}</td>
                                            <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">{{ $row['bulan'] ?? '-' }}</td>
                                            <td class="py-2 px-3 text-center">{{ $row['jml_minggu'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-center text-red-600 dark:text-red-400">{{ $row['mgg_tidak_efektif'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ $row['mgg_efektif'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $row['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-gray-400">Belum ada data AME Semester 1. Klik tombol Generate di atas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (!empty($sem1))
                                    <tfoot class="border-t border-gray-200 bg-gray-50 font-bold dark:border-white/10 dark:bg-gray-800">
                                        <tr>
                                            <td colspan="2" class="py-2 px-3">Jumlah Semester 1</td>
                                            <td class="py-2 px-3 text-center">{{ $totJml1 }}</td>
                                            <td class="py-2 px-3 text-center text-red-600">{{ $totTdk1 }}</td>
                                            <td class="py-2 px-3 text-center text-emerald-600">{{ $totEfk1 }}</td>
                                            <td class="py-2 px-3 text-emerald-600">{{ $totEfk1 * $this->jp_per_minggu }} JP ({{ $totEfk1 }} mgg × {{ $this->jp_per_minggu }} JP)</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    {{-- Semester 2 --}}
                    <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-white/10 dark:bg-gray-800/50">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Semester 2 (Januari – Juni)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-gray-200 bg-gray-50/20 text-gray-600 dark:border-white/10 dark:text-gray-400">
                                    <tr>
                                        <th class="py-2.5 px-3 font-semibold">No</th>
                                        <th class="py-2.5 px-3 font-semibold">Bulan</th>
                                        <th class="py-2.5 px-3 text-center font-semibold">Jml Mgg</th>
                                        <th class="py-2.5 px-3 text-center font-semibold">Tdk Efektif</th>
                                        <th class="py-2.5 px-3 text-center font-semibold text-emerald-600 dark:text-emerald-400">Efektif</th>
                                        <th class="py-2.5 px-3 font-semibold">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @php
                                        $sem2 = $data_ame['semester_2'] ?? [];
                                        $totJml2 = 0; $totTdk2 = 0; $totEfk2 = 0;
                                    @endphp
                                    @forelse ($sem2 as $i => $row)
                                        @php
                                            $totJml2 += (int)($row['jml_minggu'] ?? 0);
                                            $totTdk2 += (int)($row['mgg_tidak_efektif'] ?? 0);
                                            $totEfk2 += (int)($row['mgg_efektif'] ?? 0);
                                        @endphp
                                        <tr>
                                            <td class="py-2 px-3">{{ $i + 1 }}</td>
                                            <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">{{ $row['bulan'] ?? '-' }}</td>
                                            <td class="py-2 px-3 text-center">{{ $row['jml_minggu'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-center text-red-600 dark:text-red-400">{{ $row['mgg_tidak_efektif'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ $row['mgg_efektif'] ?? 0 }}</td>
                                            <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $row['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-gray-400">Belum ada data AME Semester 2. Klik tombol Generate di atas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if (!empty($sem2))
                                    <tfoot class="border-t border-gray-200 bg-gray-50 font-bold dark:border-white/10 dark:bg-gray-800">
                                        <tr>
                                            <td colspan="2" class="py-2 px-3">Jumlah Semester 2</td>
                                            <td class="py-2 px-3 text-center">{{ $totJml2 }}</td>
                                            <td class="py-2 px-3 text-center text-red-600">{{ $totTdk2 }}</td>
                                            <td class="py-2 px-3 text-center text-emerald-600">{{ $totEfk2 }}</td>
                                            <td class="py-2 px-3 text-emerald-600">{{ $totEfk2 * $this->jp_per_minggu }} JP ({{ $totEfk2 }} mgg × {{ $this->jp_per_minggu }} JP)</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ISI TAB 2: ALUR TUJUAN PEMBELAJARAN (ATP) --}}
        @if ($activeTab === 'atp')
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200 bg-gray-50/50 text-gray-600 dark:border-white/10 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="py-3 px-3 font-semibold text-center w-12">No</th>
                                <th class="py-3 px-3 font-semibold w-20">Sem / TP</th>
                                <th class="py-3 px-3 font-semibold w-36">Elemen CP</th>
                                <th class="py-3 px-3 font-semibold">Capaian Pembelajaran (CP)</th>
                                <th class="py-3 px-3 font-semibold">Materi Pokok</th>
                                <th class="py-3 px-3 font-semibold">Tujuan Pembelajaran (TP)</th>
                                <th class="py-3 px-3 font-semibold text-center w-20">Alokasi JP</th>
                                <th class="py-3 px-3 font-semibold w-28">Asesmen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($master_tp as $index => $tp)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                    <td class="py-2.5 px-3 text-center font-medium">{{ $index + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Sem {{ $tp['semester'] ?? 1 }}</span>
                                        <div class="font-bold text-gray-900 dark:text-white mt-0.5">{{ $tp['kode_tp'] ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white">{{ $tp['elemen_cp'] ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300 leading-relaxed">{{ $tp['capaian_pembelajaran'] ?? '-' }}</td>
                                    <td class="py-2.5 px-3 font-medium text-emerald-700 dark:text-emerald-400">{{ $tp['materi_pokok'] ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-gray-800 dark:text-gray-200 leading-relaxed">{{ $tp['tujuan_pembelajaran'] ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-center font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $tp['alokasi_jp'] ?? 0 }} JP</td>
                                    <td class="py-2.5 px-3 text-gray-500 dark:text-gray-400 text-[11px]">{{ $tp['jenis_asesmen'] ?? 'Formatif & Sumatif' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-gray-400">Belum ada Tujuan Pembelajaran yang disusun. Klik tombol Generate di atas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (!empty($master_tp))
                            <tfoot class="border-t border-gray-200 bg-gray-50 font-bold dark:border-white/10 dark:bg-gray-800">
                                <tr>
                                    <td colspan="6" class="py-3 px-3 text-right">TOTAL ALOKASI JP SETAHUN:</td>
                                    <td class="py-3 px-3 text-center text-emerald-600 dark:text-emerald-400 text-sm">{{ $this->totalJpTp }} JP</td>
                                    <td></td>
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
                    <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-white/10 dark:bg-gray-800/50 flex justify-between items-center">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ $semJudul }}</h3>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Total: {{ $totJpSem }} JP</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-gray-200 bg-gray-50/20 text-gray-600 dark:border-white/10 dark:text-gray-400">
                                    <tr>
                                        <th class="py-2.5 px-3 font-semibold text-center w-12">No</th>
                                        <th class="py-2.5 px-3 font-semibold w-20">Kode TP</th>
                                        <th class="py-2.5 px-3 font-semibold w-40">Elemen CP</th>
                                        <th class="py-2.5 px-3 font-semibold">Materi Pokok / Lingkup Materi</th>
                                        <th class="py-2.5 px-3 font-semibold">Tujuan Pembelajaran</th>
                                        <th class="py-2.5 px-3 font-semibold text-center w-24">Alokasi Waktu</th>
                                        <th class="py-2.5 px-3 font-semibold w-28">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @forelse ($tpSem as $idx => $tp)
                                        <tr>
                                            <td class="py-2.5 px-3 text-center font-medium">{{ $loop->iteration }}</td>
                                            <td class="py-2.5 px-3 font-bold text-emerald-700 dark:text-emerald-400">{{ $tp['kode_tp'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3 font-medium">{{ $tp['elemen_cp'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white">{{ $tp['materi_pokok'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 leading-relaxed">{{ $tp['tujuan_pembelajaran'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3 text-center font-bold text-gray-900 dark:text-white">{{ $tp['alokasi_jp'] ?? 0 }} JP</td>
                                            <td class="py-2.5 px-3 text-gray-500 text-[11px]">{{ $tp['jenis_asesmen'] ?? 'LM' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-6 text-center text-gray-400">Tidak ada TP pada semester ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($tpSem->isNotEmpty())
                                    <tfoot class="border-t border-gray-200 bg-gray-50 font-bold dark:border-white/10 dark:bg-gray-800">
                                        <tr>
                                            <td colspan="5" class="py-2.5 px-3 text-right">Jumlah Jam Pelajaran {{ $semJudul }}:</td>
                                            <td class="py-2.5 px-3 text-center text-emerald-600 dark:text-emerald-400">{{ $totJpSem }} JP</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
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
                    <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900 overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-white/10 dark:bg-gray-800/50 flex justify-between items-center">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Matriks Distribusi Mingguan {{ $config['judul'] }}</h3>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Total: {{ $totJpSem }} JP</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-[11px] border-collapse">
                                <thead class="border-b border-gray-200 bg-gray-50 text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th rowspan="2" class="py-2 px-2 text-center border-r border-gray-200 dark:border-white/10 w-8">No</th>
                                        <th rowspan="2" class="py-2 px-2 text-center border-r border-gray-200 dark:border-white/10 w-16">Kode TP</th>
                                        <th rowspan="2" class="py-2 px-3 border-r border-gray-200 dark:border-white/10 min-w-[200px]">Materi Pokok / TP</th>
                                        <th rowspan="2" class="py-2 px-2 text-center border-r border-gray-200 dark:border-white/10 w-14 font-bold">Alokasi JP</th>
                                        @foreach ($config['bulanMap'] as $key => $namaBulan)
                                            @php
                                                // Cek jumlah minggu dari baris pertama atau default 4/5
                                                $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                                            @endphp
                                            <th colspan="{{ $weekCount }}" class="py-1 px-1 text-center border-r border-gray-200 dark:border-white/10 font-bold bg-gray-100/60 dark:bg-gray-700/60">{{ $namaBulan }}</th>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        @foreach ($config['bulanMap'] as $key => $namaBulan)
                                            @php
                                                $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                                            @endphp
                                            @for ($w = 1; $w <= $weekCount; $w++)
                                                <th class="py-1 px-1 text-center border-r border-gray-200 dark:border-white/10 text-[10px] w-6 text-gray-500 dark:text-gray-400">{{ $w }}</th>
                                            @endfor
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @forelse ($tpSem as $idx => $tp)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                            <td class="py-2 px-2 text-center font-medium border-r border-gray-100 dark:border-white/5">{{ $loop->iteration }}</td>
                                            <td class="py-2 px-2 text-center font-bold text-emerald-700 dark:text-emerald-400 border-r border-gray-100 dark:border-white/5">{{ $tp['kode_tp'] ?? '-' }}</td>
                                            <td class="py-2 px-3 border-r border-gray-100 dark:border-white/5">
                                                <div class="font-semibold text-gray-900 dark:text-white">{{ $tp['materi_pokok'] ?? '-' }}</div>
                                                <div class="text-[10px] text-gray-500 line-clamp-1">{{ $tp['tujuan_pembelajaran'] ?? '' }}</div>
                                            </td>
                                            <td class="py-2 px-2 text-center font-bold text-gray-900 dark:text-white border-r border-gray-100 dark:border-white/5">{{ $tp['alokasi_jp'] ?? 0 }}</td>
                                            @foreach ($config['bulanMap'] as $key => $namaBulan)
                                                @php
                                                    $weeks = $tp['jadwal_prosem'][$key] ?? [];
                                                    $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                                    $weekCount = count($sampleWeeks) ?: count($weeks) ?: 4;
                                                @endphp
                                                @for ($w = 0; $w < $weekCount; $w++)
                                                    @php $jam = (int)($weeks[$w] ?? 0); @endphp
                                                    <td class="py-1 px-1 text-center border-r border-gray-100 dark:border-white/5 {{ $jam > 0 ? 'bg-emerald-50 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'text-gray-300 dark:text-gray-600' }}">
                                                        {{ $jam > 0 ? $jam : '-' }}
                                                    </td>
                                                @endfor
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="35" class="py-6 text-center text-gray-400">Belum ada rincian PROSEM. Klik tombol Generate di atas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ISI TAB 5: MODUL AJAR --}}
        @if ($activeTab === 'modul')
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Daftar Modul Ajar Berdasarkan Master Tujuan Pembelajaran</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Generate modul ajar lengkap 18 komponen + LKPD + Rubrik secara on-demand per Tujuan Pembelajaran.</p>
                    </div>
                    <span class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ count($this->modul_ajars) }} dari {{ count($this->master_tp) }} Modul Dibuat
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($master_tp as $tp)
                        @php
                            $kode = $tp['kode_tp'] ?? '0';
                            $sudahAda = isset($modul_ajars[$kode]);
                            $sedangProses = $generatingTpCode === $kode;
                        @endphp
                        <div class="rounded-xl border {{ $sudahAda ? 'border-emerald-200 dark:border-emerald-900/50 bg-white dark:bg-gray-900' : 'border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900' }} p-4 shadow-xs flex flex-col justify-between transition">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">TP {{ $kode }}</span>
                                    @if ($sudahAda)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            ✓ Modul Siap
                                        </span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            Belum Di-generate
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    <h4 class="font-bold text-sm text-gray-900 dark:text-white leading-snug">{{ $tp['materi_pokok'] ?? '-' }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Elemen: {{ $tp['elemen_cp'] ?? '-' }}</p>
                                </div>

                                <div class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2 leading-relaxed">
                                    {{ $tp['tujuan_pembelajaran'] ?? '-' }}
                                </div>

                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-100 dark:border-white/5">
                                    <span>Alokasi: <strong>{{ $tp['alokasi_jp'] ?? 0 }} JP</strong></span>
                                    <span>•</span>
                                    <span>Semester {{ $tp['semester'] ?? 1 }}</span>
                                </div>
                            </div>

                            <div class="pt-4 mt-3 border-t border-gray-100 dark:border-white/5 flex items-center gap-2">
                                @if ($sudahAda)
                                    <button
                                        wire:click="bukaModulAjar('{{ $kode }}')"
                                        type="button"
                                        class="flex-1 inline-flex items-center justify-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition"
                                    >
                                        👁️ Lihat Modul
                                    </button>
                                    <a
                                        href="{{ route('perangkat-ajar.cetak-satuan', ['perangkatAjar' => $this->perangkatAjarId ?: 0, 'tab' => 'modul', 'kodeTp' => $kode]) }}"
                                        target="_blank"
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        🖨️ Cetak
                                    </a>
                                    <button
                                        wire:click="generateModulAjarItem('{{ $kode }}')"
                                        wire:loading.attr="disabled"
                                        type="button"
                                        title="Re-generate Modul"
                                        class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300"
                                    >
                                        🔄
                                    </button>
                                @else
                                    <button
                                        wire:click="generateModulAjarItem('{{ $kode }}')"
                                        wire:loading.attr="disabled"
                                        type="button"
                                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500 disabled:opacity-50 transition"
                                    >
                                        @if ($sedangProses)
                                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Menyusun Modul...
                                        @else
                                            ✨ Generate Modul Ajar
                                        @endif
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-400">
                            Belum ada Tujuan Pembelajaran yang dimuat. Klik tombol Generate di atas untuk menyusun ATP & TP.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- MODAL PREVIEW MODUL AJAR (18 KOMPONEN LENGKAP) --}}
        @if ($modalModulOpen && $activeModulData)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
                <div class="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-white/10 overflow-hidden">
                    {{-- Header Modal --}}
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/50">
                        <div>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                Modul Ajar TP {{ $activeModulData['identitas']['kode_tp'] ?? $selectedModulTp }}
                            </span>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white mt-1">{{ $activeModulData['identitas']['materi_pokok'] ?? '-' }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('perangkat-ajar.cetak-satuan', ['perangkatAjar' => $this->perangkatAjarId ?: 0, 'tab' => 'modul', 'kodeTp' => $selectedModulTp]) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500"
                            >
                                🖨️ Cetak Modul Ajar
                            </a>
                            <button
                                wire:click="tutupModulAjar"
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-800"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    {{-- Isi Modal (18 Komponen) --}}
                    <div class="flex-1 overflow-y-auto p-6 space-y-6 text-xs text-gray-800 dark:text-gray-200 leading-relaxed">
                        {{-- 1. IDENTITAS --}}
                        <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-800/40">
                            <h4 class="font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-2">I. Informasi Umum & Identitas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <div><span class="text-gray-500">Satuan Pendidikan:</span> <div class="font-semibold">{{ $activeModulData['identitas']['satuan_pendidikan'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500">Mata Pelajaran:</span> <div class="font-semibold">{{ $activeModulData['identitas']['mata_pelajaran'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500">Kelas / Fase:</span> <div class="font-semibold">{{ $activeModulData['identitas']['kelas_fase'] ?? '-' }}</div></div>
                                <div><span class="text-gray-500">Alokasi Waktu:</span> <div class="font-semibold">{{ $activeModulData['identitas']['alokasi_waktu_teks'] ?? '-' }}</div></div>
                            </div>
                        </div>

                        {{-- 2. KOMPETENSI AWAL & P5RA --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-1.5">Kompetensi Awal</h4>
                                <ul class="list-disc pl-4 space-y-1 text-gray-600 dark:text-gray-300">
                                    @foreach ((array)($activeModulData['kompetensi_awal'] ?? []) as $ka)
                                        <li>{{ $ka }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-1.5">Profil Pelajar Pancasila & Rahmatan Lil Alamin (P5RA)</h4>
                                <ul class="list-disc pl-4 space-y-1 text-gray-600 dark:text-gray-300">
                                    @foreach ((array)($activeModulData['profil_p5ra'] ?? []) as $p5)
                                        <li><strong>{{ $p5['dimensi'] ?? '' }}:</strong> {{ $p5['deskripsi'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        {{-- 3. SARPRAS & MODEL PEMBELAJARAN --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-1.5">Sarana dan Prasarana</h4>
                                <p><strong>Media:</strong> {{ $activeModulData['sarana_prasarana']['media'] ?? '-' }}</p>
                                <p><strong>Alat:</strong> {{ $activeModulData['sarana_prasarana']['alat'] ?? '-' }}</p>
                                <p><strong>Sumber Belajar:</strong> {{ $activeModulData['sarana_prasarana']['sumber_belajar'] ?? '-' }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-1.5">Model & Metode Pembelajaran</h4>
                                <p><strong>Model:</strong> {{ $activeModulData['model_metode']['model'] ?? '-' }}</p>
                                <p><strong>Pendekatan:</strong> {{ $activeModulData['model_metode']['pendekatan'] ?? '-' }}</p>
                                <p><strong>Metode:</strong> {{ $activeModulData['model_metode']['metode'] ?? '-' }}</p>
                            </div>
                        </div>

                        {{-- 4. KOMPONEN INTI (TUJUAN, PEMAHAMAN BERMAKNA, PEMANTIK) --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10 space-y-3">
                            <h4 class="font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">II. Komponen Inti</h4>
                            <div>
                                <span class="font-bold">Tujuan Pembelajaran:</span>
                                <ul class="list-disc pl-4 space-y-0.5 mt-1">
                                    @foreach ((array)($activeModulData['tujuan_pembelajaran'] ?? []) as $tpText)
                                        <li>{{ $tpText }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <span class="font-bold">Pemahaman Bermakna:</span>
                                <p class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $activeModulData['pemahaman_bermakna'] ?? '-' }}</p>
                            </div>
                            <div>
                                <span class="font-bold">Pertanyaan Pemantik:</span>
                                <ul class="list-disc pl-4 space-y-0.5 mt-1">
                                    @foreach ((array)($activeModulData['pertanyaan_pemantik'] ?? []) as $tanya)
                                        <li>{{ $tanya }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        {{-- 5. KEGIATAN PEMBELAJARAN PER PERTEMUAN --}}
                        <div class="space-y-3">
                            <h4 class="font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">III. Kegiatan Pembelajaran</h4>
                            @foreach ((array)($activeModulData['kegiatan_pembelajaran'] ?? []) as $keg)
                                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-800 space-y-2">
                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-white/5 pb-1">
                                        Pertemuan ke-{{ $keg['pertemuan_ke'] ?? 1 }} ({{ $keg['jp_pertemuan'] ?? 2 }} JP)
                                    </div>
                                    <div>
                                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">A. Pendahuluan:</span>
                                        <ul class="list-disc pl-4 space-y-0.5 mt-0.5 text-gray-600 dark:text-gray-300">
                                            @foreach ((array)($keg['pendahuluan'] ?? []) as $pnd)
                                                <li>{{ $pnd }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-blue-700 dark:text-blue-400">B. Kegiatan Inti:</span>
                                        <ul class="list-disc pl-4 space-y-0.5 mt-0.5 text-gray-600 dark:text-gray-300">
                                            @foreach ((array)($keg['inti'] ?? []) as $int)
                                                <li>{{ $int }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-amber-700 dark:text-amber-400">C. Penutup:</span>
                                        <ul class="list-disc pl-4 space-y-0.5 mt-0.5 text-gray-600 dark:text-gray-300">
                                            @foreach ((array)($keg['penutup'] ?? []) as $pnt)
                                                <li>{{ $pnt }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- 6. ASESMEN & RUBRIK --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10 space-y-3">
                            <h4 class="font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">IV. Asesmen & Rubrik Penilaian</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-[11px]">
                                    <thead class="bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-b border-gray-200 dark:border-white/10">
                                        <tr>
                                            <th class="py-2 px-2 border-r border-gray-200 dark:border-white/10 font-bold">Aspek Penilaian</th>
                                            <th class="py-2 px-2 border-r border-gray-200 dark:border-white/10 text-emerald-700">Sangat Baik (4)</th>
                                            <th class="py-2 px-2 border-r border-gray-200 dark:border-white/10 text-blue-700">Baik (3)</th>
                                            <th class="py-2 px-2 border-r border-gray-200 dark:border-white/10 text-amber-700">Cukup (2)</th>
                                            <th class="py-2 px-2 text-red-700">Perlu Bimbingan (1)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                        @foreach ((array)($activeModulData['asesmen']['formatif_rubrik'] ?? []) as $rubrik)
                                            <tr>
                                                <td class="py-2 px-2 font-semibold border-r border-gray-100 dark:border-white/5">{{ $rubrik['aspek'] ?? '-' }}</td>
                                                <td class="py-2 px-2 border-r border-gray-100 dark:border-white/5">{{ $rubrik['sangat_baik'] ?? '-' }}</td>
                                                <td class="py-2 px-2 border-r border-gray-100 dark:border-white/5">{{ $rubrik['baik'] ?? '-' }}</td>
                                                <td class="py-2 px-2 border-r border-gray-100 dark:border-white/5">{{ $rubrik['cukup'] ?? '-' }}</td>
                                                <td class="py-2 px-2">{{ $rubrik['perlu_bimbingan'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- 7. LAMPIRAN LKPD --}}
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/50 p-4 dark:border-white/20 dark:bg-gray-800/50 space-y-2">
                            <h4 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider">V. Lampiran: Lembar Kerja Peserta Didik (LKPD)</h4>
                            <div class="font-bold text-sm text-emerald-700 dark:text-emerald-400">{{ $activeModulData['lampiran']['lkpd']['judul'] ?? 'LKPD' }}</div>
                            <p class="text-gray-600 dark:text-gray-300"><strong>Petunjuk:</strong> {{ $activeModulData['lampiran']['lkpd']['petunjuk'] ?? '-' }}</p>
                            @if (!empty($activeModulData['lampiran']['lkpd']['teks_stimulus']))
                                <div class="rounded bg-white p-2 border border-gray-200 dark:bg-gray-800 dark:border-white/10">
                                    <strong>Stimulus:</strong> {{ $activeModulData['lampiran']['lkpd']['teks_stimulus'] }}
                                </div>
                            @endif
                            <div>
                                <strong>Daftar Tugas / Pertanyaan:</strong>
                                <ol class="list-decimal pl-4 space-y-1 mt-1">
                                    @foreach ((array)($activeModulData['lampiran']['lkpd']['daftar_tugas'] ?? []) as $tgs)
                                        <li>{{ $tgs }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Modal --}}
                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-white/10 dark:bg-gray-800 flex justify-end">
                        <button
                            wire:click="tutupModulAjar"
                            type="button"
                            class="rounded-lg bg-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
