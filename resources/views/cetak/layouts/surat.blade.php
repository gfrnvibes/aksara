@php
    /** @var bool $untukPdf */
    /** @var string $judul */
    /** @var string|null $nomorSurat */
    /** @var \Carbon\CarbonInterface $tanggalSurat */
    /** @var \App\Models\TahunAjaran|null $tahunAjaran */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body { font-family: {{ $untukPdf ? '"DejaVu Serif", serif' : '"Times New Roman", Times, serif' }}; font-size: {{ $untukPdf ? '10.5px' : '13px' }}; line-height: 1.5; color: #000; margin: 0; background: {{ $untukPdf ? '#fff' : '#e5e7eb' }}; }
        .sheet { background: #fff; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 14px; }
        .kop .instansi { font-size: 1.1em; font-weight: bold; }
        .kop .madrasah { font-size: 1.5em; font-weight: bold; }
        .kop .alamat { font-style: italic; font-size: 0.92em; }
        .judul { text-align: center; margin-bottom: 14px; }
        .judul h1 { font-size: 1.2em; margin: 0; text-decoration: underline; }
        .judul .nomor { margin-top: 2px; }
        p { margin: 0 0 8px; text-align: justify; }
        table { border-collapse: collapse; width: 100%; }
        table.data { margin: 4px 0 10px 24px; width: calc(100% - 24px); }
        table.data td { padding: 1px 4px; vertical-align: top; }
        table.data td.label { width: 34%; }
        table.data td.sep { width: 12px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 4px; }
        table.grid th { background: #f3f4f6; }
        table.grid .center { text-align: center; }
        table.grid tr.kelompok td { font-weight: bold; background: #fafafa; }
        .ttd { width: 46%; margin-left: auto; margin-top: 18px; text-align: center; }
        .ttd .ruang { height: 64px; }
        .toolbar { text-align: center; margin: 16px; font-family: Arial, sans-serif; }
        .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
        @if (! $untukPdf)
            .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 18mm; }
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
        <div class="toolbar"><button type="button" onclick="window.print()">Cetak</button></div>
    @endunless

    <div class="sheet">
        @include('cetak.partials.kop')

        <div class="judul">
            <h1>{{ strtoupper($judul) }}</h1>
            @if (filled($nomorSurat))
                <div class="nomor">Nomor: {{ $nomorSurat }}</div>
            @endif
        </div>

        @yield('isi')

        <div class="ttd">
            {{ config('sekolah.tempat_tanda_tangan') }}, {{ $tanggalSurat->translatedFormat('d F Y') }}<br>
            Kepala Madrasah
            <div class="ruang"></div>
            <strong><u>{{ $tahunAjaran?->kepala_madrasah ?: '...............................................' }}</u></strong><br>
            NIP. {{ $tahunAjaran?->nip_kepala_madrasah ?: '-' }}
        </div>
    </div>
</body>
</html>
