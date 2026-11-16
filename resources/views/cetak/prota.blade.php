<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Program Tahunan (PROTA) - {{ $prota->mataPelajaran->nama }} Kelas {{ $prota->kelas->nama }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.35;
        }
        .header-kop {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .header-kop h2 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-kop h1 {
            margin: 3px 0;
            font-size: 15px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-kop .instansi {
            font-size: 11px;
            font-weight: normal;
            margin: 0;
        }
        .tabel-identitas {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .tabel-identitas td {
            padding: 2px 4px;
            font-size: 10px;
            vertical-align: top;
        }
        .tabel-identitas .label {
            width: 150px;
            font-weight: bold;
        }
        .tabel-identitas .separator {
            width: 10px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #444;
            padding: 5px 6px;
            font-size: 9.5px;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .bg-sub {
            background-color: #e9edf0;
            font-weight: bold;
        }
        .bg-total {
            background-color: #dce4ec;
            font-weight: bold;
        }
        .tabel-ttd {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .tabel-ttd td {
            width: 50%;
            vertical-align: top;
            font-size: 10px;
        }
        .ruang-ttd {
            height: 60px;
        }
        .toolbar {
            margin-bottom: 16px;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .btn {
            display: inline-block;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            margin-right: 8px;
        }
        .btn-primary {
            background-color: #059669;
            color: #fff;
        }
        .btn-secondary {
            background-color: #4b5563;
            color: #fff;
        }
        @media print {
            .toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

@unless($untukPdf)
    <div class="toolbar">
        <button onclick="window.print()" class="btn btn-primary">Cetak Dokumen</button>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-secondary">Unduh PDF</a>
    </div>
@endunless

<div class="header-kop">
    <h2>PROGRAM TAHUNAN (PROTA)</h2>
    <h1>KURIKULUM MERDEKA</h1>
    <p class="instansi">{{ config('sekolah.nama', 'Madrasah Tsanawiyah') }} · Tahun Pelajaran {{ $prota->tahunAjaran->nama }}</p>
</div>

<table class="tabel-identitas">
    <tr>
        <td class="label">Satuan Pendidikan</td>
        <td class="separator">:</td>
        <td>{{ config('sekolah.nama', 'MTs At-Taufiq Cisurupan') }}</td>
        <td class="label">Kelas / Fase</td>
        <td class="separator">:</td>
        <td>{{ $prota->kelas->nama }} / Fase {{ $prota->fase ?? 'D' }}</td>
    </tr>
    <tr>
        <td class="label">Mata Pelajaran</td>
        <td class="separator">:</td>
        <td>{{ $prota->mataPelajaran->nama }}</td>
        <td class="label">Tahun Pelajaran</td>
        <td class="separator">:</td>
        <td>{{ $prota->tahunAjaran->nama }}</td>
    </tr>
    <tr>
        <td class="label">Guru Pengampu</td>
        <td class="separator">:</td>
        <td>{{ $prota->gtk->nama }}</td>
        <td class="label">Jumlah Alokasi Waktu</td>
        <td class="separator">:</td>
        <td><strong>{{ $totalJp }} JP</strong> ({{ $totalMinggu }} Minggu Efektif)</td>
    </tr>
</table>

<table class="data-table">
    <thead>
        <tr>
            <th style="width: 3%;">No</th>
            <th style="width: 7%;">Semester</th>
            <th style="width: 16%;">Elemen Capaian Pembelajaran</th>
            <th style="width: 22%;">Materi Pokok / Ruang Lingkup</th>
            <th style="width: 32%;">Tujuan Pembelajaran (TP)</th>
            <th style="width: 7%;">Alokasi Waktu</th>
            <th style="width: 6%;">Minggu Efektif</th>
            <th style="width: 7%;">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <!-- SEMESTER 1 -->
        <tr class="bg-sub">
            <td colspan="8">SEMESTER 1 (GANJIL)</td>
        </tr>
        @forelse($itemsGanjil as $item)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="text-center">1 (Ganjil)</td>
                <td>{{ $item->elemen_cp }}</td>
                <td>{{ $item->materi_pokok }}</td>
                <td>{{ $item->tujuan_pembelajaran }}</td>
                <td class="text-center">{{ $item->alokasi_waktu_jp }} JP</td>
                <td class="text-center">{{ $item->minggu_efektif }} mgg</td>
                <td>{{ $item->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center" style="font-style: italic; color: #666;">Belum ada rincian materi untuk Semester 1.</td>
            </tr>
        @endforelse
        <tr class="bg-sub">
            <td colspan="5" class="text-right"><strong>Jumlah Alokasi Waktu Semester 1:</strong></td>
            <td class="text-center font-bold">{{ $totalJpGanjil }} JP</td>
            <td class="text-center font-bold">{{ $totalMingguGanjil }} mgg</td>
            <td></td>
        </tr>

        <!-- SEMESTER 2 -->
        <tr class="bg-sub">
            <td colspan="8">SEMESTER 2 (GENAP)</td>
        </tr>
        @forelse($itemsGenap as $item)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="text-center">2 (Genap)</td>
                <td>{{ $item->elemen_cp }}</td>
                <td>{{ $item->materi_pokok }}</td>
                <td>{{ $item->tujuan_pembelajaran }}</td>
                <td class="text-center">{{ $item->alokasi_waktu_jp }} JP</td>
                <td class="text-center">{{ $item->minggu_efektif }} mgg</td>
                <td>{{ $item->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center" style="font-style: italic; color: #666;">Belum ada rincian materi untuk Semester 2.</td>
            </tr>
        @endforelse
        <tr class="bg-sub">
            <td colspan="5" class="text-right"><strong>Jumlah Alokasi Waktu Semester 2:</strong></td>
            <td class="text-center font-bold">{{ $totalJpGenap }} JP</td>
            <td class="text-center font-bold">{{ $totalMingguGenap }} mgg</td>
            <td></td>
        </tr>

        <!-- TOTAL 1 TAHUN -->
        <tr class="bg-total">
            <td colspan="5" class="text-right"><strong>TOTAL KESELURUHAN (1 TAHUN PELAJARAN):</strong></td>
            <td class="text-center font-bold">{{ $totalJp }} JP</td>
            <td class="text-center font-bold">{{ $totalMinggu }} mgg</td>
            <td></td>
        </tr>
    </tbody>
</table>

@if(!empty($prota->catatan))
    <div style="margin-top: 8px; font-size: 9px; color: #444;">
        <strong>Catatan Perencanaan:</strong> {{ $prota->catatan }}
    </div>
@endif

<table class="tabel-ttd">
    <tr>
        <td style="text-align: left; padding-left: 30px;">
            Mengetahui,<br>
            Kepala {{ config('sekolah.nama', 'Madrasah') }}<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $prota->tahunAjaran->kepala_madrasah ?: '......................................................' }}</u></strong><br>
            NIP. {{ $prota->tahunAjaran->nip_kepala_madrasah ?: '-' }}
        </td>
        <td style="text-align: right; padding-right: 30px;">
            {{ config('sekolah.tempat_tanda_tangan', 'Cisurupan') }}, {{ now()->translatedFormat('d F Y') }}<br>
            Guru Mata Pelajaran<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $prota->gtk->nama }}</u></strong><br>
            NIP. {{ $prota->gtk->nip ?: '-' }}
        </td>
    </tr>
</table>

</body>
</html>
