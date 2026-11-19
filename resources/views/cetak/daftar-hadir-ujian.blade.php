@php
    /** @var \App\Models\KegiatanUjian $kegiatan */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RuangUjian> $ruangList */
    /** @var list<array{hari: string, tanggal: string}> $daftarTanggal */
    $namaMadrasah = strtoupper((string) config('sekolah.nama', 'Madrasah Tsanawiyah'));
    $namaKepala = $kegiatan->tahunAjaran?->kepala_madrasah ?: ($kegiatan->penandaTangan?->nama ?? '________________');
    $nipKepala = $kegiatan->tahunAjaran?->nip_kepala_madrasah ?: $kegiatan->penandaTangan?->nip;
    $ketua = $kegiatan->ketuaPanitia;
    $kolomTanggal = $daftarTanggal !== [] ? $daftarTanggal : array_fill(0, 6, ['hari' => '........', 'tanggal' => '.../.../2026']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir Peserta - {{ $kegiatan->nama }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", serif;
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
            font-family: sans-serif;
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
            width: 277mm;
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
            position: relative;
            text-align: center;
            margin-bottom: 4mm;
            min-height: 16mm;
        }
        .header-wrap .judul {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
        }
        .kotak-ruang {
            position: absolute;
            top: 0;
            right: 0;
            border: 1.5px solid #000;
            padding: 2mm 6mm;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        table.daftar-hadir {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.daftar-hadir th,
        table.daftar-hadir td {
            border: 1px solid #000;
            padding: 1.5mm 2mm;
        }
        table.daftar-hadir th {
            background: #f3f4f6;
            text-align: center;
            font-weight: bold;
        }
        table.daftar-hadir td.no { text-align: center; width: 8mm; }
        table.daftar-hadir td.nisn { text-align: center; width: 24mm; }
        table.daftar-hadir td.nama { min-width: 48mm; }
        table.daftar-hadir td.paraf { width: 22mm; height: 6.5mm; }
        .ttd-wrap {
            display: flex;
            justify-content: space-between;
            margin-top: 6mm;
            font-size: 9pt;
            page-break-inside: avoid;
        }
        .ttd-box { min-width: 55mm; }
        .ttd-nama { margin-top: 12mm; font-weight: bold; text-decoration: underline; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .halaman { width: 100%; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>Daftar Hadir Peserta — {{ $kegiatan->nama }}</strong> ({{ $ruangList->count() }} Ruang · Minggu otomatis libur)</div>
        <button type="button" onclick="window.print()">Cetak / Print (Ctrl+P)</button>
    </div>

    @foreach ($ruangList as $ruang)
        <div class="halaman">
            <div class="header-wrap">
                <div class="judul">
                    DAFTAR HADIR PESERTA<br>
                    {{ strtoupper($kegiatan->nama) }}<br>
                    {{ $namaMadrasah }}<br>
                    TAHUN AJARAN {{ $kegiatan->tahunAjaran?->nama }}
                </div>
                <div class="kotak-ruang">{{ strtoupper($ruang->nama) }}</div>
            </div>

            <table class="daftar-hadir">
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">NISN</th>
                        <th rowspan="2">Nama Peserta</th>
                        <th colspan="{{ count($kolomTanggal) }}">Hari, tanggal</th>
                    </tr>
                    <tr>
                        @foreach ($kolomTanggal as $tgl)
                            <th>{{ $tgl['hari'] }},<br>{{ $tgl['tanggal'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ruang->penempatan as $idx => $item)
                        <tr>
                            <td class="no">{{ $idx + 1 }}</td>
                            <td class="nisn">{{ $item->siswa?->nisn ?: ($item->siswa?->nis ?: '-') }}</td>
                            <td class="nama">{{ $item->siswa?->nama_lengkap }}</td>
                            @foreach ($kolomTanggal as $tgl)
                                <td class="paraf"></td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + count($kolomTanggal) }}" style="text-align: center; padding: 6mm;">
                                Belum ada peserta di ruang ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

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
    @endforeach
</body>
</html>
