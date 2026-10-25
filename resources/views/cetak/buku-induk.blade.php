<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buku Induk{{ $daftarSiswa->count() === 1 ? ' - '.$daftarSiswa->first()->nama_lengkap : '' }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body { font-family: {{ $untukPdf ? '"DejaVu Sans", sans-serif' : 'Arial, Helvetica, sans-serif' }}; font-size: {{ $untukPdf ? '9px' : '11px' }}; color: #000; margin: 0; background: {{ $untukPdf ? '#fff' : '#e5e7eb' }}; }
        .sheet { background: #fff; }
        .page-break { page-break-before: always; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 10px; }
        h2 { font-size: 11px; margin: 10px 0 4px; }
        table { border-collapse: collapse; width: 100%; }
        table.data td { padding: 1px 4px; vertical-align: top; }
        table.data td.no { width: 24px; }
        table.data td.label { width: 42%; }
        table.data td.sep { width: 10px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 3px; }
        table.grid th { background: #f3f4f6; }
        table.grid td.center { text-align: center; }
        table.layout > tbody > tr > td { vertical-align: top; padding: 0; }
        .kelompok td { font-weight: bold; background: #fafafa; }
        .foto { width: 30mm; height: 40mm; border: 1px solid #000; text-align: center; vertical-align: middle; }
        .foto img { width: 30mm; height: 40mm; }
        .toolbar { text-align: center; margin: 16px; }
        .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
        @if (! $untukPdf)
            .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 15mm; }
            @media print {
                body { background: #fff; }
                .sheet { margin: 0; padding: 0; width: auto; min-height: auto; }
                .toolbar { display: none; }
            }
        @endif
    </style>
</head>
<body>
    @unless ($untukPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Cetak</button>
        </div>
    @endunless

    @php
        $halamanPertama = true;
    @endphp

    @foreach ($daftarSiswa as $siswa)
        @if ($isi->termasukSiswa())
            @include('cetak.partials.lembar-siswa', ['siswa' => $siswa, 'pageBreak' => ! $halamanPertama])
            @php
                $halamanPertama = false;
            @endphp
        @endif

        @if ($isi->termasukNilai())
            @foreach ($siswa->rapor->sortBy(fn ($rapor): string => $rapor->tahunAjaran->nama.$rapor->semester->value)->groupBy(fn ($rapor): string => $rapor->tahunAjaran->nama) as $tahunAjaran => $raporTahunIni)
                @include('cetak.partials.lembar-nilai', ['siswa' => $siswa, 'tahunAjaran' => $tahunAjaran, 'raporTahunIni' => $raporTahunIni, 'pageBreak' => ! $halamanPertama])
                @php
                    $halamanPertama = false;
                @endphp
            @endforeach
        @endif
    @endforeach

    @if ($halamanPertama)
        <div class="sheet">
            <h1>Tidak ada data untuk dicetak</h1>
        </div>
    @endif
</body>
</html>
