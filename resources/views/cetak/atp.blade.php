<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Alur Tujuan Pembelajaran (ATP) - {{ $atp->mataPelajaran->nama }} Kelas {{ $atp->kelas->nama }}</title>
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
            width: 140px;
            font-weight: bold;
        }
        .tabel-identitas .separator {
            width: 8px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #333;
            padding: 5px 6px;
            font-size: 9.5px;
            vertical-align: top;
        }
        table.data-table th {
            background-color: #f1f3f5;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.3px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .box-cp {
            background: #fafafa;
            border: 1px solid #ccc;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 9.5px;
        }
        .box-cp strong {
            display: block;
            margin-bottom: 3px;
            color: #222;
        }
        .tanda-tangan {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }
        .tanda-tangan td {
            width: 50%;
            text-align: center;
            font-size: 10px;
            vertical-align: top;
        }
        .tanda-tangan .nama-ttd {
            margin-top: 55px;
            font-weight: bold;
            text-decoration: underline;
        }
        .no-print {
            margin-bottom: 14px;
            padding: 8px 12px;
            background: #e7f5ff;
            border: 1px solid #74c0fc;
            border-radius: 4px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn {
            display: inline-block;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary { background: #1971c2; color: #fff; }
        .btn-secondary { background: #495057; color: #fff; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

@if (! $untukPdf)
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">Cetak Dokumen</button>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-secondary">Unduh PDF</a>
        <span style="font-size: 10px; color: #495057; margin-left: 8px;">Gunakan orientasi Lanskap (Landscape) saat mencetak.</span>
    </div>
@endif

<div class="header-kop">
    <h2>{{ config('sekolah.nama', 'ALUR TUJUAN PEMBELAJARAN (ATP)') }}</h2>
    <h1>ALUR TUJUAN PEMBELAJARAN (ATP) KURIKULUM MERDEKA</h1>
    <p class="instansi">Tahun Ajaran {{ $atp->tahunAjaran->nama }} &middot; Jenjang {{ $atp->jenjang }} &middot; Fase {{ $atp->fase }}</p>
</div>

<table class="tabel-identitas">
    <tr>
        <td class="label">Satuan Pendidikan</td>
        <td class="separator">:</td>
        <td>{{ config('sekolah.nama', 'Madrasah') }}</td>
        <td class="label">Jenjang / Kelas</td>
        <td class="separator">:</td>
        <td>{{ $atp->jenjang }} / Kelas {{ $atp->kelas->nama }}</td>
    </tr>
    <tr>
        <td class="label">Mata Pelajaran</td>
        <td class="separator">:</td>
        <td><strong>{{ $atp->mataPelajaran->nama }}</strong></td>
        <td class="label">Fase Capaian</td>
        <td class="separator">:</td>
        <td>Fase {{ $atp->fase }}</td>
    </tr>
    <tr>
        <td class="label">Guru Pengampu</td>
        <td class="separator">:</td>
        <td>{{ $atp->gtk->nama ?? '-' }}</td>
        <td class="label">Total Alokasi Waktu</td>
        <td class="separator">:</td>
        <td><strong>{{ $totalJp }} JP</strong> ({{ $jumlahTp }} Tujuan Pembelajaran)</td>
    </tr>
</table>

@if (! empty($atp->capaian_pembelajaran_fase))
    <div class="box-cp">
        <strong>Capaian Pembelajaran (CP) Fase {{ $atp->fase }}:</strong>
        {{ $atp->capaian_pembelajaran_fase }}
    </div>
@endif

<table class="data-table">
    <thead>
        <tr>
            <th style="width: 30px;">No</th>
            <th style="width: 110px;">Elemen CP</th>
            <th style="width: 220px;">Capaian Pembelajaran (CP)</th>
            <th style="width: 220px;">Tujuan Pembelajaran (TP)</th>
            <th style="width: 170px;">Materi Pembelajaran</th>
            <th style="width: 120px;">Alur Tujuan Pembelajaran</th>
            <th style="width: 45px;">Alokasi JP</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $item->elemen_cp }}</strong></td>
                <td>{{ $item->capaian_pembelajaran }}</td>
                <td>{{ $item->tujuan_pembelajaran }}</td>
                <td>{{ $item->materi_pembelajaran }}</td>
                <td><span style="font-weight: bold; color: #1971c2;">{{ $item->alur_tujuan_pembelajaran }}</span></td>
                <td class="text-center font-bold">{{ $item->alokasi_waktu_jp }} JP</td>
                <td>{{ $item->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 16px;">Belum ada rincian alur tujuan pembelajaran yang disusun.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <th colspan="6" class="text-right">TOTAL ALOKASI WAKTU (1 TAHUN) :</th>
            <th class="text-center font-bold">{{ $totalJp }} JP</th>
            <th></th>
        </tr>
    </tfoot>
</table>

@if (! empty($atp->catatan))
    <div class="box-cp" style="margin-top: 8px;">
        <strong>Catatan / Karakteristik Peserta Didik:</strong>
        {{ $atp->catatan }}
    </div>
@endif

<table class="tanda-tangan">
    <tr>
        <td>
            Mengetahui,<br>
            Kepala Sekolah / Madrasah
            <div class="nama-ttd">{{ config('sekolah.kepala_madrasah', 'Kepala Madrasah') }}</div>
            <div>NIP. {{ config('sekolah.nip_kepala_madrasah', '-') }}</div>
        </td>
        <td>
            {{ config('sekolah.kota', 'Kota') }}, {{ now()->translatedFormat('d F Y') }}<br>
            Guru Mata Pelajaran
            <div class="nama-ttd">{{ $atp->gtk->nama ?? 'Guru Pengampu' }}</div>
            <div>NIP. {{ $atp->gtk->nip ?? '-' }}</div>
        </td>
    </tr>
</table>

</body>
</html>
