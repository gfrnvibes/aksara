<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BUNDEL LENGKAP PERANGKAT AJAR - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #111;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* PAGE BREAK RULES */
        .page-break {
            page-break-before: always;
            break-before: page;
        }

        /* ORIENTASI SEKSI */
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        .halaman-landscape {
            page-break-before: always;
            break-before: page;
        }

        @media print {
            .halaman-landscape {
                size: A4 landscape;
            }
        }

        /* COVER STYLES */
        .halaman-cover {
            min-height: 95vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            border: 3px double #111;
            padding: 30px 25px;
            box-sizing: border-box;
        }
        .cover-wrapper {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .cover-instansi .kemenag-title {
            margin: 0;
            font-size: 11pt;
            letter-spacing: 1px;
            color: #333;
        }
        .cover-instansi .madrasah-title {
            margin: 5px 0 2px 0;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .cover-instansi .madrasah-sub {
            margin: 0;
            font-size: 9pt;
            color: #555;
        }
        .cover-divider {
            height: 2px;
            background: #111;
            margin: 15px auto;
            width: 80%;
        }
        .cover-logo-box {
            margin: 20px 0;
        }
        .cover-logo {
            max-height: 110px;
            width: auto;
        }
        .cover-main-title {
            margin: 25px 0;
        }
        .dokumen-judul {
            font-size: 24pt;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 0;
            text-transform: uppercase;
        }
        .kurikulum-judul {
            font-size: 16pt;
            font-weight: bold;
            color: #047857;
            margin: 5px 0 10px 0;
            letter-spacing: 1px;
        }
        .bundel-badge {
            display: inline-block;
            border: 1.5px solid #047857;
            color: #047857;
            padding: 4px 16px;
            font-size: 9pt;
            font-weight: bold;
            border-radius: 20px;
        }
        .cover-box-identitas {
            margin: 25px auto;
            width: 85%;
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 12px 18px;
        }
        .tabel-cover-identitas {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            text-align: left;
        }
        .tabel-cover-identitas td {
            padding: 3px 4px;
        }
        .tabel-cover-identitas .lbl {
            width: 140px;
            color: #444;
        }
        .tabel-cover-identitas .sep {
            width: 10px;
        }
        .cover-footer {
            margin-top: 30px;
        }
        .cover-guru-box {
            margin-bottom: 25px;
        }
        .lbl-penyusun {
            font-size: 9pt;
            color: #555;
        }
        .nama-guru {
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 4px;
        }
        .nip-guru {
            font-size: 9.5pt;
            color: #333;
        }
        .cover-tahun {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* HEADER & KOP */
        .header-kop {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .header-kop h2 {
            margin: 0;
            font-size: 13pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header-kop h1 {
            margin: 3px 0;
            font-size: 14pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header-kop .instansi {
            font-size: 10pt;
            margin: 0;
        }

        /* TABEL IDENTITAS */
        .tabel-identitas {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .tabel-identitas td {
            padding: 2px 4px;
            font-size: 9pt;
            vertical-align: top;
        }
        .tabel-identitas .label {
            width: 130px;
            font-weight: bold;
        }
        .tabel-identitas .separator {
            width: 8px;
        }

        /* DATA TABLE */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #333;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        table.matriks-prosem {
            font-size: 7pt;
        }
        table.matriks-prosem th, table.matriks-prosem td {
            padding: 2px 1px;
        }
        .bg-gray { background-color: #e9ecef; }
        .bg-filled { background-color: #d1fae5; color: #065f46; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        /* TTD */
        .tanda-tangan {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .tanda-tangan td {
            text-align: center;
            vertical-align: top;
            font-size: 9pt;
            width: 50%;
        }
        .tanda-tangan .nama-ttd {
            margin-top: 55px;
            font-weight: bold;
            text-decoration: underline;
        }

        /* MODUL AJAR */
        .modul-box {
            border: 1px solid #ddd;
            padding: 5px 8px;
            margin-bottom: 6px;
            font-size: 8.5pt;
        }
        .modul-section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #047857;
            border-bottom: 1.5px solid #047857;
            padding-bottom: 2px;
            margin: 12px 0 5px 0;
            text-transform: uppercase;
        }
        .tabel-modul-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .tabel-modul-info td {
            padding: 2px 3px;
        }

        /* PRINT BAR */
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #111827;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 9999;
            font-family: sans-serif;
            font-size: 13px;
        }
        .print-bar button, .print-bar a {
            background: #059669;
            color: white;
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .print-bar a.btn-gray { background: #4b5563; }

        @media print {
            .print-bar { display: none !important; }
            body { margin-top: 0 !important; }
        }

        @media screen {
            body {
                background: #f3f4f6;
                padding-top: 65px;
                padding-bottom: 40px;
            }
            .bundel-container {
                max-width: 210mm;
                margin: 0 auto;
            }
            .page-sheet {
                background: white;
                min-height: 297mm;
                padding: 15mm 15mm 15mm 15mm;
                margin-bottom: 20px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                box-sizing: border-box;
            }
            .page-sheet.landscape {
                max-width: 297mm;
                min-height: 210mm;
                padding: 10mm 15mm 15mm 15mm;
            }
        }
    </style>
</head>
<body>

@if (! $untukPdf)
    <div class="print-bar">
        <div>
            <strong>📚 Bundel Dokumen Perangkat Ajar Lengkap</strong> — {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }} (Fase {{ $perangkat->fase }})
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()">🖨️ Cetak Seluruh Bundel</button>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn-gray">📥 Unduh PDF Bundel</a>
        </div>
    </div>
@endif

<div class="bundel-container">

    {{-- DOKUMEN 1: COVER --}}
    <div class="page-sheet">
        @include('cetak.perangkat-ajar.cover')
    </div>

    {{-- DOKUMEN 2: LEMBAR PENGESAHAN --}}
    <div class="page-break page-sheet">
        @include('cetak.perangkat-ajar.pengesahan')
    </div>

    {{-- DOKUMEN 3: ANALISIS MINGGU EFEKTIF (AME) --}}
    <div class="page-break page-sheet">
        @include('cetak.perangkat-ajar.ame')
    </div>

    {{-- DOKUMEN 4: ALUR TUJUAN PEMBELAJARAN (ATP) --}}
    <div class="page-break page-sheet landscape halaman-landscape">
        @include('cetak.perangkat-ajar.atp')
    </div>

    {{-- DOKUMEN 5: PROGRAM TAHUNAN (PROTA) --}}
    <div class="page-break page-sheet landscape halaman-landscape">
        @include('cetak.perangkat-ajar.prota')
    </div>

    {{-- DOKUMEN 6: PROGRAM SEMESTER (PROSEM) --}}
    <div class="page-break page-sheet landscape halaman-landscape">
        @include('cetak.perangkat-ajar.prosem')
    </div>

    {{-- DOKUMEN 7: SELURUH MODUL AJAR YANG SUDAH DIGENERATE --}}
    @php
        $modulList = $perangkat->modul_ajars ?? [];
    @endphp
    @forelse ($modulList as $kode => $modul)
        <div class="page-break page-sheet">
            @include('cetak.perangkat-ajar.modul', ['modul' => $modul])
        </div>
    @empty
        <div class="page-break page-sheet">
            <div style="text-align: center; padding: 60px 20px; color: #666;">
                <h3>MODUL AJAR BELUM DIGENERATE</h3>
                <p>Silakan buka panel guru pada Tab Modul Ajar dan lakukan generate modul ajar untuk masing-masing Tujuan Pembelajaran.</p>
            </div>
        </div>
    @endforelse

</div>

</body>
</html>
