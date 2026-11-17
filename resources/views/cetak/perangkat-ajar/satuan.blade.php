<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>
        @if ($tab === 'ame')
            Analisis Minggu Efektif (AME) - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}
        @elseif ($tab === 'atp')
            Alur Tujuan Pembelajaran (ATP) - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}
        @elseif ($tab === 'prota')
            Program Tahunan (PROTA) - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}
        @elseif ($tab === 'prosem')
            Program Semester (PROSEM) - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}
        @elseif ($tab === 'modul')
            Modul Ajar TP {{ $kodeTp }} - {{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }}
        @else
            Perangkat Ajar - {{ $perangkat->mataPelajaran?->nama }}
        @endif
    </title>
    <style>
        @php
            $isLandscape = in_array($tab, ['atp', 'prota', 'prosem']);
        @endphp
        @page {
            size: A4 {{ $isLandscape ? 'landscape' : 'portrait' }};
            margin: {{ $isLandscape ? '10mm 15mm 15mm 15mm' : '15mm 15mm 15mm 15mm' }};
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: {{ $isLandscape ? '9.5pt' : '10pt' }};
            color: #111;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
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
            letter-spacing: 0.5px;
        }
        .header-kop h1 {
            margin: 3px 0;
            font-size: 14pt;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-kop .instansi {
            font-size: 10pt;
            margin: 0;
        }
        .tabel-identitas {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .tabel-identitas td {
            padding: 2px 4px;
            font-size: 9.5pt;
            vertical-align: top;
        }
        .tabel-identitas .label {
            width: 140px;
            font-weight: bold;
        }
        .tabel-identitas .separator {
            width: 8px;
        }
        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 6px 0;
            color: #111;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #333;
            padding: 4px 5px;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            font-size: 8.5pt;
        }
        table.matriks-prosem {
            font-size: 7.5pt;
        }
        table.matriks-prosem th, table.matriks-prosem td {
            padding: 2px 1px;
        }
        .bg-gray {
            background-color: #e9ecef;
        }
        .bg-filled {
            background-color: #d1fae5;
            color: #065f46;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .tanda-tangan {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .tanda-tangan td {
            text-align: center;
            vertical-align: top;
            font-size: 9.5pt;
            width: 50%;
        }
        .tanda-tangan .nama-ttd {
            margin-top: 60px;
            font-weight: bold;
            text-decoration: underline;
        }
        .modul-box {
            border: 1px solid #ddd;
            padding: 6px 10px;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        .modul-section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #047857;
            border-bottom: 1.5px solid #047857;
            padding-bottom: 3px;
            margin: 14px 0 6px 0;
            text-transform: uppercase;
        }
        .tabel-modul-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .tabel-modul-info td {
            padding: 2px 4px;
        }
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1f2937;
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
        .print-bar a.btn-gray {
            background: #4b5563;
        }
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
            .page-container {
                background: white;
                max-width: {{ $isLandscape ? '297mm' : '210mm' }};
                min-height: {{ $isLandscape ? '210mm' : '297mm' }};
                margin: 0 auto;
                padding: {{ $isLandscape ? '10mm 15mm 15mm 15mm' : '15mm 15mm 15mm 15mm' }};
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>

@if (! $untukPdf)
    <div class="print-bar">
        <div>
            <strong>Pratinjau Dokumen Perangkat Ajar</strong> — {{ strtoupper($tab) }} ({{ $perangkat->mataPelajaran?->nama }} Kelas {{ $perangkat->kelas }})
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()">🖨️ Cetak Sekarang</button>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn-gray">📥 Unduh PDF</a>
        </div>
    </div>
@endif

<div class="page-container">
    @if ($tab === 'ame')
        @include('cetak.perangkat-ajar.ame')
    @elseif ($tab === 'atp')
        @include('cetak.perangkat-ajar.atp')
    @elseif ($tab === 'prota')
        @include('cetak.perangkat-ajar.prota')
    @elseif ($tab === 'prosem')
        @include('cetak.perangkat-ajar.prosem')
    @elseif ($tab === 'modul')
        @if ($modul)
            @include('cetak.perangkat-ajar.modul', ['modul' => $modul])
        @else
            <div style="padding: 40px; text-align: center; color: #666;">
                <h3>Modul Ajar Belum Di-generate</h3>
                <p>Silakan klik tombol <strong>[✨ Generate Modul Ajar]</strong> pada Tujuan Pembelajaran di panel guru.</p>
            </div>
        @endif
    @else
        @include('cetak.perangkat-ajar.atp')
    @endif
</div>

</body>
</html>
