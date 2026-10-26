@php
    /** @var \App\Services\Leger\Leger|null $leger */
    $rataRataMapel = $leger?->rataRataMapel() ?? [];
    $angka = fn (?float $nilai, int $desimal = 2): string => $nilai === null ? '' : number_format($nilai, $desimal, ',', '.');
@endphp

<div>
    @if ($leger === null)
        <x-filament::empty-state
            icon="heroicon-o-presentation-chart-bar"
            heading="Pilih tahun ajaran, semester, dan kelas"
            description="Leger menampilkan nilai seluruh mata pelajaran siswa satu kelas beserta jumlah, rata-rata, dan peringkat."
        />
    @else
        {{-- Styles are scoped here so the table does not depend on utilities being compiled into the theme. --}}
        <style>
            .leger-wrap { overflow-x: auto; border: 1px solid var(--gray-200); }
            .leger { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 0.8125rem; }
            .leger th, .leger td { border-bottom: 1px solid var(--gray-200); border-right: 1px solid var(--gray-200); padding: 0.375rem 0.5rem; white-space: nowrap; }
            .leger thead th { background: var(--gray-50); color: var(--gray-600); font-weight: 600; text-align: center; vertical-align: bottom; }
            .leger thead th.leger-label { font-family: var(--font-mono); font-size: 0.6875rem; letter-spacing: 0.05em; text-transform: uppercase; color: var(--gray-500); vertical-align: middle; }
            .leger thead th.leger-mapel { width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem; white-space: normal; line-height: 1.2; font-size: 0.75rem; font-weight: 500; }
            .leger thead th.leger-pk { font-family: var(--font-mono); font-size: 0.6875rem; color: var(--gray-500); padding: 0.25rem; }
            .leger tbody td { background: #fff; }
            .leger tbody tr:nth-child(even) td { background: var(--gray-50); }
            .leger tbody tr:hover td { background: var(--primary-50); }
            .leger tfoot td { background: var(--gray-100); font-weight: 600; border-bottom: 0; }
            .leger td.leger-angka { font-family: var(--font-mono); font-variant-numeric: tabular-nums; text-align: center; width: 2.75rem; min-width: 2.75rem; }
            .leger td.leger-kanan { font-family: var(--font-mono); font-variant-numeric: tabular-nums; text-align: right; }
            .leger td.leger-rendah { color: var(--danger-600); font-weight: 600; }
            .leger .leger-siswa { position: sticky; left: 0; z-index: 2; text-align: left; min-width: 18rem; box-shadow: 1px 0 0 var(--gray-300); }
            .leger thead .leger-siswa { z-index: 3; }
            .leger .leger-no { display: inline-block; width: 1.75rem; color: var(--gray-400); font-family: var(--font-mono); font-size: 0.75rem; }
            .leger .leger-nis { color: var(--gray-500); font-family: var(--font-mono); font-size: 0.75rem; margin-right: 0.5rem; }
            .leger .leger-peringkat { font-weight: 700; text-align: center; }
            .leger-kosong { padding: 1.5rem; text-align: center; color: var(--gray-500); }
            .leger-keterangan { margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-500); }

            .dark .leger-wrap { border-color: rgb(255 255 255 / 0.1); }
            .dark .leger th, .dark .leger td { border-color: rgb(255 255 255 / 0.08); }
            .dark .leger thead th { background: var(--gray-800); color: var(--gray-300); }
            .dark .leger tbody td { background: var(--gray-900); }
            .dark .leger tbody tr:nth-child(even) td { background: var(--gray-800); }
            .dark .leger tbody tr:hover td { background: var(--gray-700); }
            .dark .leger tfoot td { background: var(--gray-800); }
            .dark .leger .leger-siswa { box-shadow: 1px 0 0 rgb(255 255 255 / 0.15); }
            .dark .leger td.leger-rendah { color: var(--danger-400); }
        </style>

        <div class="leger-wrap">
            <table class="leger">
                <thead>
                    <tr>
                        <th rowspan="2" class="leger-label leger-siswa"><span class="leger-no">No</span>Siswa</th>
                        @foreach ($leger->mataPelajaran as $mapel)
                            <th colspan="2" class="leger-mapel" title="{{ $mapel->nama }}">{{ $mapel->nama }}</th>
                        @endforeach
                        <th rowspan="2" class="leger-label">Jumlah</th>
                        <th rowspan="2" class="leger-label">Rata-rata</th>
                        <th rowspan="2" class="leger-label">Peringkat</th>
                    </tr>
                    <tr>
                        @foreach ($leger->mataPelajaran as $mapel)
                            <th class="leger-pk">P</th>
                            <th class="leger-pk">K</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leger->baris as $baris)
                        <tr>
                            <td class="leger-siswa">
                                <span class="leger-no">{{ $loop->iteration }}</span><span class="leger-nis">{{ $baris['siswa']->nis }}</span>{{ $baris['siswa']->nama_lengkap }}
                            </td>
                            @foreach ($leger->mataPelajaran as $mapel)
                                @foreach (['p', 'k'] as $jenis)
                                    @php($nilai = $baris['nilai'][$mapel->id][$jenis])
                                    <td @class(['leger-angka', 'leger-rendah' => $nilai !== null && $nilai < $baris['nilai'][$mapel->id]['kkm']])>{{ $nilai }}</td>
                                @endforeach
                            @endforeach
                            <td class="leger-kanan">{{ $baris['rata_rata'] === null ? '' : $baris['jumlah'] }}</td>
                            <td class="leger-kanan">{{ $angka($baris['rata_rata']) }}</td>
                            <td class="leger-peringkat">{{ $baris['peringkat'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + ($leger->mataPelajaran->count() * 2) }}" class="leger-kosong">
                                Belum ada siswa di kelas ini pada tahun ajaran tersebut.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($leger->baris !== [])
                    <tfoot>
                        <tr>
                            <td class="leger-siswa"><span class="leger-no"></span>Rata-rata kelas</td>
                            @foreach ($leger->mataPelajaran as $mapel)
                                <td class="leger-angka">{{ $angka($rataRataMapel[$mapel->id]['p'], 1) }}</td>
                                <td class="leger-angka">{{ $angka($rataRataMapel[$mapel->id]['k'], 1) }}</td>
                            @endforeach
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="leger-keterangan">
            P = Pengetahuan (KI-3), K = Keterampilan (KI-4). Nilai merah berada di bawah KKM. Peringkat berdasarkan rata-rata seluruh nilai; rata-rata yang sama mendapat peringkat yang sama. Geser tabel ke samping untuk melihat mata pelajaran lainnya.
        </p>
    @endif
</div>
