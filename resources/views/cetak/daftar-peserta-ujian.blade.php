@php
    /** @var \App\Models\KegiatanUjian $kegiatan */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RuangUjian> $ruangList */
    /** @var array<string, mixed> $kop */
    $namaMadrasah = strtoupper((string) ($kop['nama'] ?? config('sekolah.nama', 'Madrasah')));
    $semester = $kegiatan->semester === \App\Enums\Semester::Ganjil ? 'Ganjil' : 'Genap';
    $namaKepala = $kegiatan->tahunAjaran?->kepala_madrasah ?: ($kegiatan->penandaTangan?->nama ?? '________________');
    $nipKepala = $kegiatan->tahunAjaran?->nip_kepala_madrasah ?: $kegiatan->penandaTangan?->nip;
    $ketua = $kegiatan->ketuaPanitia;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Peserta Ujian - {{ $kegiatan->nama }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 9.5pt;
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
            width: 190mm;
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
        .header-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2mm 0 4mm;
            gap: 4mm;
        }
        .judul {
            font-weight: 700;
            font-size: 10.5pt;
            line-height: 1.35;
        }
        .kotak-ruang {
            border: 1.5px solid #000;
            padding: 2mm 5mm;
            font-size: 11pt;
            font-weight: 700;
            text-align: center;
            min-width: 36mm;
        }
        table.daftar {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.daftar th,
        table.daftar td {
            border: 1px solid #000;
            padding: 1.4mm 2.2mm;
        }
        table.daftar th {
            background: #f3f4f6;
            text-align: center;
            font-weight: 700;
        }
        table.daftar td.no {
            text-align: center;
            width: 10mm;
        }
        table.daftar td.nomor {
            text-align: center;
            width: 30mm;
            font-weight: 600;
        }
        table.daftar td.nisn {
            text-align: center;
            width: 28mm;
        }
        table.daftar td.kelas {
            text-align: center;
            width: 24mm;
        }
        .footer-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 6mm;
            font-size: 8.5pt;
            page-break-inside: avoid;
        }
        .rekap-kelas {
            border-collapse: collapse;
            margin-top: 1.5mm;
        }
        .rekap-kelas th,
        .rekap-kelas td {
            border: 1px solid #374151;
            padding: 1mm 2.5mm;
            text-align: left;
        }
        .ttd-wrap {
            display: flex;
            gap: 10mm;
        }
        .ttd-box {
            min-width: 48mm;
            line-height: 1.3;
        }
        .ttd-nama {
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
        <div><strong>Daftar Peserta per Ruangan — {{ $kegiatan->nama }}</strong> ({{ $ruangList->count() }} Ruang · Urut Kelas 7–9)</div>
        <button type="button" onclick="window.print()">Cetak / Print (Ctrl+P)</button>
    </div>

    @foreach ($ruangList as $ruang)
        @php
            $pesertaUrut = $ruang->penempatan
                ->sortBy(fn ($p) => sprintf(
                    '%02d-%s-%s',
                    $p->kelas?->tingkat ?? 99,
                    $p->kelas?->nama ?? '',
                    $p->nomor_peserta ?? ''
                ))
                ->values();

            $rekapKelas = $pesertaUrut
                ->groupBy(fn ($p) => $p->kelas?->nama ?? 'Tanpa Kelas')
                ->map->count();
        @endphp
        <div class="halaman">
            @include('cetak.partials.kop', ['kop' => $kop])

            <div class="header-wrap">
                <div class="judul">
                    DAFTAR PESERTA UJIAN<br>
                    {{ strtoupper($kegiatan->nama) }} · {{ $namaMadrasah }}<br>
                    Tahun Ajaran {{ $kegiatan->tahunAjaran?->nama }} · Semester {{ $semester }}
                </div>
                <div class="kotak-ruang">{{ strtoupper($ruang->nama) }}</div>
            </div>

            <table class="daftar">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nomor</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pesertaUrut as $idx => $item)
                        <tr>
                            <td class="no">{{ $idx + 1 }}</td>
                            <td class="nomor">{{ $item->nomor_peserta }}</td>
                            <td class="nisn">{{ $item->siswa?->nisn ?: '-' }}</td>
                            <td>{{ $item->siswa?->nama_lengkap }}</td>
                            <td class="kelas">{{ $item->kelas?->nama ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 6mm;">
                                Belum ada peserta di ruang ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="footer-wrap">
                <div>
                    <strong>Rekap Peserta ({{ $pesertaUrut->count() }} siswa):</strong>
                    <table class="rekap-kelas">
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

                <div class="ttd-wrap">
                    <div class="ttd-box">
                        <div>Mengetahui,</div>
                        <div>Kepala Madrasah,</div>
                        <div class="ttd-nama">{{ $namaKepala }}</div>
                        @if (filled($nipKepala))
                            <div>NIP. {{ $nipKepala }}</div>
                        @endif
                    </div>
                    <div class="ttd-box">
                        <div>&nbsp;</div>
                        <div>Ketua Panitia Ujian,</div>
                        <div class="ttd-nama">{{ $ketua?->nama ?? '________________' }}</div>
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
