@php
    /** @var \App\Models\KegiatanUjian $kegiatan */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RuangUjian> $ruangList */
    /** @var array<string, mixed> $kop */
    $semester = $kegiatan->semester === \App\Enums\Semester::Ganjil ? 'Ganjil' : 'Genap';
    $kepala = $kegiatan->penandaTangan;
    $ketua = $kegiatan->ketuaPanitia;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Denah Ruang Ujian - {{ $kegiatan->nama }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 8.5pt;
            color: #111;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #111827;
            color: #fff;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .toolbar button {
            background: #10b981;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .halaman {
            width: 194mm;
            margin: 12px auto;
            background: #fff;
            padding: 6mm;
            page-break-after: always;
            break-after: page;
        }
        .halaman:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        .judul-denah {
            text-align: center;
            margin: 2mm 0 4mm;
        }
        .judul-denah h1 {
            font-size: 11pt;
            margin: 0;
            text-transform: uppercase;
        }
        .judul-denah p {
            margin: 1mm 0 0;
            font-size: 9pt;
            color: #374151;
        }
        .pengawas {
            border: 1.5px solid #111;
            text-align: center;
            font-weight: 700;
            padding: 2mm 6mm;
            width: 65mm;
            margin: 0 auto 4mm;
            background: #f9fafb;
            font-size: 8.5pt;
        }
        .denah-grid {
            display: grid;
            gap: 2.5mm;
        }
        .meja {
            border: 1.2px solid #111;
            display: grid;
            background: #fff;
            break-inside: avoid;
        }
        .kursi {
            padding: 1.5mm;
            min-height: 18mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 7.2pt;
            line-height: 1.2;
        }
        .kursi + .kursi {
            border-left: 1px dashed #9ca3af;
        }
        .kursi-head {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.6mm;
            margin-bottom: 0.6mm;
        }
        .kursi-no {
            background: #111;
            color: #fff;
            padding: 0.2mm 1.2mm;
            border-radius: 2px;
            font-size: 6.8pt;
        }
        .kursi-nama {
            font-weight: 600;
            word-break: break-word;
        }
        .kursi-kelas {
            color: #4b5563;
            font-size: 6.8pt;
        }
        .footer-denah {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 5mm;
            font-size: 8pt;
        }
        .rekap-kelas {
            border-collapse: collapse;
        }
        .rekap-kelas th, .rekap-kelas td {
            border: 1px solid #374151;
            padding: 1mm 2.5mm;
            text-align: left;
        }
        .ttd {
            min-width: 50mm;
            line-height: 1.3;
        }
        .ttd .nama {
            margin-top: 12mm;
            font-weight: 700;
            text-decoration: underline;
        }
        @media print {
            body {
                background: #fff;
            }
            .toolbar {
                display: none;
            }
            .halaman {
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>Denah Ruang Ujian — {{ $kegiatan->nama }}</strong> ({{ $ruangList->count() }} Ruang)
        </div>
        <button type="button" onclick="window.print()">Cetak / Print (Ctrl+P)</button>
    </div>

    @foreach ($ruangList as $ruang)
        @php
            $kursiPerMeja = max(1, $ruang->kursi_per_meja);
            $jumlahMeja = (int) ceil($ruang->kapasitas / $kursiPerMeja);
            $slotMap = $ruang->penempatan->keyBy('nomor_kursi');
            $rekapKelas = $ruang->penempatan->groupBy(fn ($p) => $p->kelas?->nama ?? 'Tanpa Kelas')
                ->map->count()
                ->sortKeys();
        @endphp
        <div class="halaman">
            @include('cetak.partials.kop', ['kop' => $kop])

            <div class="judul-denah">
                <h1>Denah Tempat Duduk {{ $kegiatan->nama }}</h1>
                <p>{{ $ruang->nama }} ({{ $ruang->kode }}) · Tahun Ajaran {{ $kegiatan->tahunAjaran?->nama }} · Semester {{ $semester }}</p>
            </div>

            <div class="pengawas">MEJA PENGAWAS / PAPAN TULIS</div>

            <div class="denah-grid" style="grid-template-columns: repeat({{ max(1, $ruang->jumlah_kolom) }}, 1fr);">
                @for ($meja = 0; $meja < $jumlahMeja; $meja++)
                    <div class="meja" style="grid-template-columns: repeat({{ $kursiPerMeja }}, 1fr);">
                        @for ($k = 1; $k <= $kursiPerMeja; $k++)
                            @php
                                $nomorKursi = ($meja * $kursiPerMeja) + $k;
                                $peserta = $slotMap->get($nomorKursi);
                            @endphp
                            @if ($nomorKursi <= $ruang->kapasitas)
                                <div class="kursi">
                                    <div class="kursi-head">
                                        <span class="kursi-no">{{ str_pad((string) $nomorKursi, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span>{{ $peserta?->nomor_peserta ?? '-' }}</span>
                                    </div>
                                    <div class="kursi-nama">{{ $peserta?->siswa?->nama_lengkap ?? 'Kosong' }}</div>
                                    <div class="kursi-kelas">{{ $peserta?->kelas?->nama ?? '' }}</div>
                                </div>
                            @endif
                        @endfor
                    </div>
                @endfor
            </div>

            <div class="footer-denah">
                <div>
                    <strong>Rekap Peserta ({{ $ruang->penempatan->count() }}/{{ $ruang->kapasitas }} kursi):</strong>
                    <table class="rekap-kelas" style="margin-top: 1.5mm;">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rekapKelas as $namaKelas => $jumlah)
                                <tr>
                                    <td>{{ $namaKelas }}</td>
                                    <td>{{ $jumlah }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 10mm;">
                    <div class="ttd">
                        <div>Mengetahui,</div>
                        <div>{{ $kepala?->jabatan ?? 'Kepala Madrasah' }},</div>
                        <div class="nama">{{ $kepala?->nama ?? '________________' }}</div>
                        @if (filled($kepala?->nip))
                            <div>NIP. {{ $kepala->nip }}</div>
                        @endif
                    </div>
                    <div class="ttd">
                        <div>&nbsp;</div>
                        <div>{{ $ketua?->jabatan ?? 'Ketua Panitia Ujian' }},</div>
                        <div class="nama">{{ $ketua?->nama ?? '________________' }}</div>
                        @if (filled($ketua?->nip))
                            <div>NIP. {{ $ketua->nip }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
