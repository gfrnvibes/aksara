@php
    /** @var \App\Services\Jadwal\TampilanJadwal $tampilan */
    /** @var list<array{tingkat: string, pesan: string}> $pemeriksaan */
    $kelas = $tampilan->kelas;
    $pesanPemeriksaan = array_column($pemeriksaan, 'pesan');
    $catatanPenyusunan = collect($tampilan->jadwal->laporan ?? [])
        ->reject(fn (array $temuan): bool => in_array($temuan['pesan'], $pesanPemeriksaan, true));
@endphp

<div class="jadwal">
    {{-- Styles are scoped here so the grid does not depend on utilities being compiled into the theme. --}}
    <style>
        .jadwal { display: flex; flex-direction: column; gap: 1.25rem; }
        .jadwal-wrap { overflow-x: auto; border: 1px solid var(--gray-200); background: #fff; }
        .jadwal-grid { border-collapse: collapse; width: max-content; min-width: 100%; font-size: 0.8125rem; }
        .jadwal-grid th, .jadwal-grid td { border: 1px solid var(--gray-200); padding: 0; text-align: center; white-space: nowrap; }
        .jadwal-grid thead th { background: var(--gray-50); color: var(--gray-700); font-weight: 600; padding: 0.375rem 0.5rem; }
        .jadwal-grid .jadwal-hari { border-left: 2px solid var(--gray-400); }
        .jadwal-grid .jadwal-waktu { font-family: var(--font-mono); font-size: 0.6875rem; color: var(--gray-500); padding: 0.25rem 0.5rem; background: var(--gray-50); line-height: 1.25; }
        .jadwal-grid .jadwal-waktu b { display: block; color: var(--gray-700); font-size: 0.75rem; }
        .jadwal-grid .jadwal-jeda { background: var(--gray-100); color: var(--gray-500); font-size: 0.75rem; font-style: italic; padding: 0.25rem 0.5rem; }
        .jadwal-grid .jadwal-kosong-hari { background: repeating-linear-gradient(45deg, var(--gray-50), var(--gray-50) 4px, #fff 4px, #fff 8px); }
        .jadwal-sel { display: block; width: 100%; min-width: 3.75rem; min-height: 2.25rem; padding: 0.25rem; font-family: var(--font-mono); font-weight: 600; color: #111827; cursor: pointer; position: relative; }
        .jadwal-sel:hover { outline: 2px solid var(--primary-500); outline-offset: -2px; }
        .jadwal-sel.jadwal-sel-kosong { color: var(--gray-300); font-weight: 400; }
        .jadwal-sel .jadwal-kunci { position: absolute; top: 1px; right: 2px; font-size: 0.625rem; opacity: 0.7; }
        .jadwal-legenda { border-collapse: collapse; font-size: 0.8125rem; width: 100%; }
        .jadwal-legenda th, .jadwal-legenda td { border: 1px solid var(--gray-200); padding: 0.25rem 0.5rem; }
        .jadwal-legenda thead th { background: var(--gray-50); font-weight: 600; }
        .jadwal-legenda td.angka, .jadwal-legenda th.angka { text-align: center; font-family: var(--font-mono); }
        .jadwal-legenda .kode { font-family: var(--font-mono); font-weight: 600; text-align: center; }
        .jadwal-legenda tfoot td { background: var(--gray-50); font-weight: 600; }
        .jadwal-temuan { display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.8125rem; }
        .jadwal-temuan li { padding: 0.375rem 0.625rem; border-left: 3px solid var(--gray-300); background: var(--gray-50); }
        .jadwal-temuan li.galat { border-color: var(--danger-500); background: var(--danger-50); color: var(--danger-700); }
        .jadwal-temuan li.peringatan { border-color: var(--warning-500); background: var(--warning-50); color: var(--warning-800); }
        .jadwal-temuan li.info { border-color: var(--success-500); background: var(--success-50); color: var(--success-800); }
        .jadwal-judul { font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.5rem; }
        .jadwal-bantuan { font-size: 0.75rem; color: var(--gray-500); }
    </style>

    @if ($tampilan->pola->kosong())
        <x-filament::empty-state
            icon="heroicon-o-clock"
            heading="Jam Pelajaran belum diatur"
            description="Atur dulu jam pelajaran tiap hari di menu Kurikulum › Jam Pelajaran (bisa memakai jam bawaan)."
        />
    @elseif ($kelas->isEmpty())
        <x-filament::empty-state
            icon="heroicon-o-rectangle-group"
            heading="Belum ada pembagian mengajar"
            description="Isi dulu Pembagian Mengajar untuk tahun ajaran jadwal ini."
        />
    @else
        <p class="jadwal-bantuan">Klik sel untuk mengisi, mengganti, atau mengosongkan pelajaran. 🔒 = sel terkunci, tidak diubah saat disusun otomatis.</p>

        @foreach ($tampilan->kelompokHari() as $kelompok)
            <div class="jadwal-wrap">
                <table class="jadwal-grid">
                    <thead>
                        <tr>
                            @foreach ($kelompok as $hari)
                                <th colspan="{{ $kelas->count() + 1 }}" class="jadwal-hari">{{ $hari->getLabel() }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($kelompok as $hari)
                                <th class="jadwal-hari">Waktu</th>
                                @foreach ($kelas as $satuKelas)
                                    <th>{{ $satuKelas->nama }}</th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @for ($baris = 0; $baris < $tampilan->jumlahBaris($kelompok); $baris++)
                            <tr>
                                @foreach ($kelompok as $hari)
                                    @php($jam = $tampilan->pola->baris($hari)->get($baris))
                                    @if ($jam === null)
                                        <td colspan="{{ $kelas->count() + 1 }}" class="jadwal-hari jadwal-kosong-hari"></td>
                                    @elseif ($jam->jp_ke === null)
                                        <td class="jadwal-hari jadwal-waktu">{{ $jam->rentang() }}</td>
                                        <td colspan="{{ $kelas->count() }}" class="jadwal-jeda">{{ $jam->label() }}</td>
                                    @else
                                        <td class="jadwal-hari jadwal-waktu"><b>JP {{ $jam->jp_ke }}</b>{{ $jam->rentang() }}</td>
                                        @foreach ($kelas as $satuKelas)
                                            @php($isi = $tampilan->isi($satuKelas->id, $hari, $jam->jp_ke))
                                            @php($kode = $tampilan->kodeUntuk($isi))
                                            <td @if ($kode) style="background: {{ $kode['warna'] }};" @endif>
                                                <button
                                                    type="button"
                                                    class="jadwal-sel {{ $kode ? '' : 'jadwal-sel-kosong' }}"
                                                    wire:click="mountAction('aturSel', @js(['kelas' => $satuKelas->id, 'hari' => $hari->value, 'jp' => $jam->jp_ke]))"
                                                    title="{{ $isi ? $isi->pembagianMengajar->mataPelajaran->nama.' — '.$isi->pembagianMengajar->gtk->nama : 'Kosong' }}"
                                                >
                                                    {{ $kode['kode'] ?? '+' }}
                                                    @if ($isi?->is_terkunci)
                                                        <span class="jadwal-kunci">🔒</span>
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        @endforeach

        <div>
            <div class="jadwal-judul">Pemeriksaan</div>
            @if ($pemeriksaan === [])
                <ul class="jadwal-temuan"><li class="info">Tidak ada bentrok, semua JP terpasang lengkap, dan semua permintaan guru terpenuhi.</li></ul>
            @else
                <ul class="jadwal-temuan">
                    @foreach ($pemeriksaan as $temuan)
                        <li class="{{ $temuan['tingkat'] }}">{{ $temuan['pesan'] }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($catatanPenyusunan->isNotEmpty())
                <div class="jadwal-judul" style="margin-top: 1rem;">Catatan penyusunan otomatis terakhir</div>
                <ul class="jadwal-temuan">
                    @foreach ($catatanPenyusunan as $temuan)
                        <li class="{{ $temuan['tingkat'] }}">{{ $temuan['pesan'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="jadwal-wrap">
            <table class="jadwal-legenda">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Guru</th>
                        <th>Kode</th>
                        <th>Mata Pelajaran</th>
                        @foreach ($kelas as $satuKelas)
                            <th class="angka">{{ $satuKelas->nama }}</th>
                        @endforeach
                        <th class="angka">Jumlah</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tampilan->legenda as $nomor => $guru)
                        @foreach ($guru['baris'] as $baris)
                            <tr>
                                @if ($loop->first)
                                    <td rowspan="{{ count($guru['baris']) }}" class="angka">{{ $nomor + 1 }}</td>
                                    <td rowspan="{{ count($guru['baris']) }}">{{ $guru['guru'] }}</td>
                                @endif
                                <td class="kode" style="background: {{ $baris['warna'] }};">{{ $baris['kode'] }}</td>
                                <td>{{ $baris['mapel'] }}</td>
                                @foreach ($kelas as $satuKelas)
                                    <td class="angka">{{ $baris['jp'][$satuKelas->id] ?? 0 }}</td>
                                @endforeach
                                <td class="angka">{{ $baris['jumlah'] }}</td>
                                <td>{{ $baris['terpasang'] === $baris['jumlah'] ? 'Ok' : "Terpasang {$baris['terpasang']}" }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: center;">Total Jam Pelajaran</td>
                        @foreach ($kelas as $satuKelas)
                            <td class="angka">{{ $tampilan->totalPerKelas[$satuKelas->id] ?? 0 }}</td>
                        @endforeach
                        <td class="angka">{{ $tampilan->totalJp() }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
