@php
    $evaluasi = $this->evaluasi();
    $daftarAspek = $this->daftarAspek();
    $indikators = $evaluasi?->indikators->sortBy('kode') ?? collect();
    $terisi = $evaluasi?->jumlahTerisi() ?? 0;
    $total = $indikators->count();
    $persenProgres = $total > 0 ? round(($terisi / $total) * 100) : 0;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Header Ringkas: Pilih Tahun, Progres & Segarkan Data --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label for="tahun-edm" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Tahun Ajaran</label>
                    <select
                        id="tahun-edm"
                        wire:model.live="tahunAjaranId"
                        class="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-900 dark:border-white/15 dark:bg-gray-800 dark:text-white"
                    >
                        @foreach ($this->opsiTahunAjaran() as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-48">
                    <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
                        <span>Progres Pengisian</span>
                        <span class="font-semibold">{{ $terisi }} / {{ $total }} ({{ $persenProgres }}%)</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-emerald-600 transition-all" style="width: {{ $persenProgres }}%;"></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($evaluasi && $evaluasi->rataRataSkor() !== null)
                    <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                        Rata-rata Skor: {{ $evaluasi->rataRataSkor() }} / 4.00
                    </span>
                @endif
                <button
                    type="button"
                    wire:click="segarkanData"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                >
                    <x-filament::icon icon="heroicon-o-arrow-path" class="size-4" />
                    <span>Segarkan Data Sistem</span>
                </button>
            </div>
        </div>

        {{-- Tab 5 Aspek Budaya Mutu --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($daftarAspek as $kodeAspek => $labelAspek)
                @php
                    $itemsAspek = $indikators->where('aspek', $kodeAspek);
                    $terisiAspek = $itemsAspek->filter(fn ($i) => $i->skor_final !== null)->count();
                    $aktif = $aspekAktif === $kodeAspek;
                @endphp
                <button
                    type="button"
                    wire:click="$set('aspekAktif', '{{ $kodeAspek }}')"
                    class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition {{ $aktif ? 'border-emerald-600 bg-emerald-600 text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200' }}"
                >
                    <span>{{ $labelAspek }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] {{ $aktif ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' }}">
                        {{ $terisiAspek }}/{{ $itemsAspek->count() }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Daftar Kartu Indikator Per Aspek --}}
        <div class="space-y-4">
            @foreach ($indikators->where('aspek', $aspekAktif) as $indikator)
                @php
                    $definisi = \App\Services\Akreditasi\KatalogEdm2026::cari($indikator->kode);
                    $buktiList = $indikator->buktiGabungan();
                    $skorAktif = $indikator->skor_final;
                    $skorSaran = $indikator->skor_saran;
                @endphp

                <div wire:key="edm-{{ $indikator->id }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-2xs sm:p-5 dark:border-white/10 dark:bg-gray-900">
                    {{-- Header Butir --}}
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-3 dark:border-white/10">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-emerald-700 px-2.5 py-0.5 text-xs font-bold text-white">{{ $indikator->kode }}</span>
                                @if ($skorAktif !== null)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                                        Terpilih: Tingkat {{ $skorAktif }}
                                    </span>
                                @elseif ($skorSaran !== null)
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                        Saran Sistem: Tingkat {{ $skorSaran }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        Belum dinilai
                                    </span>
                                @endif
                            </div>
                            <h3 class="text-sm font-semibold text-gray-900 sm:text-base dark:text-white">
                                {{ $indikator->pertanyaan }}
                            </h3>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('akreditasi.edm.cetak', $indikator) }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/15 dark:bg-gray-800 dark:text-gray-200"
                            >
                                <x-filament::icon icon="heroicon-o-printer" class="size-3.5" />
                                <span>Cetak Bukti</span>
                            </a>
                            <a
                                href="{{ route('akreditasi.edm.unduh-zip', $indikator) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-3.5" />
                                <span>Unduh Bukti (.ZIP)</span>
                            </a>
                        </div>
                    </div>

                    {{-- Ringkasan Data Otomatis dari Aksara --}}
                    <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50/70 p-3 text-xs text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
                        <div class="font-semibold">Data Otomatis dari Aksara ({{ $indikator->metrik_sistem['sumber'] ?? 'Sistem' }}):</div>
                        <p class="mt-0.5">{{ $indikator->metrik_sistem['ringkasan'] ?? 'Belum ada ringkasan otomatis.' }}</p>
                    </div>

                    {{-- Pilihan Skor 1-4 (Ramah Guru Gaptek) --}}
                    <div class="mt-3">
                        <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Kondisi Madrasah Saat Ini (Klik 1 kotak):</div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($definisi['tingkat'] ?? [] as $lvl => $ket)
                                @php $dipilih = $skorAktif === $lvl; @endphp
                                <button
                                    type="button"
                                    wire:click="pilihSkor({{ $indikator->id }}, {{ $lvl }})"
                                    class="flex flex-col justify-between rounded-xl border p-3 text-left transition {{ $dipilih ? 'border-emerald-600 bg-emerald-50 ring-2 ring-emerald-500/30 dark:bg-emerald-500/15' : 'border-gray-200 bg-gray-50/60 hover:border-emerald-400 dark:border-white/10 dark:bg-white/5' }}"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-bold {{ $dipilih ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-800 dark:text-gray-200' }}">
                                                Tingkat {{ $lvl }}
                                            </span>
                                            @if ($skorSaran === $lvl)
                                                <span class="rounded bg-amber-200/80 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Saran</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-[11px] leading-relaxed text-gray-600 dark:text-gray-300">{{ $ket }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Asisten AI Gemini & Narasi --}}
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Catatan Kondisi Nyata &amp; Tindak Lanjut</span>
                                <button
                                    type="button"
                                    wire:click="bantuAi({{ $indikator->id }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1 rounded-lg bg-amber-500 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-amber-600"
                                >
                                    <x-filament::icon icon="heroicon-o-sparkles" class="size-3.5" />
                                    <span>Bantu Isi AI Gemini</span>
                                </button>
                            </div>

                            <input
                                type="text"
                                wire:model.defer="catatanAiInput.{{ $indikator->id }}"
                                placeholder="Tulis kata kunci singkat untuk AI (opsional, misal: sudah rutin tiap hari)..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 dark:border-white/15 dark:bg-gray-800 dark:text-white"
                            >

                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Kondisi Nyata</label>
                                <textarea
                                    rows="2"
                                    wire:model.defer="kondisiInput.{{ $indikator->id }}"
                                    class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 dark:border-white/15 dark:bg-gray-800 dark:text-white"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">Rencana Tindak Lanjut (e-RKAM)</label>
                                <textarea
                                    rows="2"
                                    wire:model.defer="tindakLanjutInput.{{ $indikator->id }}"
                                    class="mt-0.5 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 dark:border-white/15 dark:bg-gray-800 dark:text-white"
                                ></textarea>
                            </div>

                            <button
                                type="button"
                                wire:click="simpanNarasi({{ $indikator->id }})"
                                class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-gray-700 dark:bg-white/15 dark:hover:bg-white/25"
                            >
                                Simpan Catatan
                            </button>
                        </div>

                        {{-- Bukti Gambar & Dokumen (Upload + Galeri Otomatis) --}}
                        <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                            <div class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                Bukti Gambar &amp; Dokumen ({{ $buktiList->count() }} berkas)
                            </div>

                            @if (!empty($definisi['bukti']))
                                <ul class="list-disc space-y-0.5 pl-4 text-[11px] text-gray-500 dark:text-gray-400">
                                    @foreach ($definisi['bukti'] as $syarat)
                                        <li>{{ $syarat }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($buktiList->isNotEmpty())
                                <div class="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-3">
                                    @foreach ($buktiList as $idx => $item)
                                        <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-1.5 dark:border-white/10 dark:bg-white/5">
                                            @if ($item['is_gambar'])
                                                <img
                                                    src="{{ route('akreditasi.edm.gambar', ['indikator' => $indikator, 'indeks' => $idx]) }}"
                                                    alt="{{ $item['judul'] }}"
                                                    class="h-20 w-full rounded object-cover"
                                                >
                                            @else
                                                <div class="flex h-20 items-center justify-center rounded bg-gray-200/70 text-[11px] font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                    PDF / Berkas
                                                </div>
                                            @endif
                                            <div class="mt-1 truncate text-[10px] font-medium text-gray-700 dark:text-gray-200">
                                                [{{ strtoupper($item['sumber']) }}] {{ $item['judul'] }}
                                            </div>
                                            @if ($item['id_bukti'] !== null)
                                                <button
                                                    type="button"
                                                    wire:click="hapusBukti({{ $item['id_bukti'] }})"
                                                    class="mt-0.5 text-[10px] font-semibold text-red-600 hover:underline"
                                                >
                                                    Hapus
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="space-y-1.5 border-t border-gray-100 pt-2 dark:border-white/10">
                                <input
                                    type="text"
                                    wire:model.defer="judulUpload.{{ $indikator->id }}"
                                    placeholder="Judul foto/dokumen bukti (opsional)..."
                                    class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-900 dark:border-white/15 dark:bg-gray-800 dark:text-white"
                                >
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        type="file"
                                        wire:model="berkasUpload.{{ $indikator->id }}"
                                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                                        class="text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-emerald-700 dark:text-gray-300"
                                    >
                                    <button
                                        type="button"
                                        wire:click="unggahBukti({{ $indikator->id }})"
                                        wire:loading.attr="disabled"
                                        class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                                    >
                                        Unggah
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
