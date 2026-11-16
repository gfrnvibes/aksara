<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Program Semester (PROSEM) - {{ $prosem->mataPelajaran->nama }} Kelas {{ $prosem->kelas->nama }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 12mm 12mm;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #111;
            line-height: 1.3;
        }
        .header-kop {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .header-kop h2 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-kop h1 {
            margin: 2px 0;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-kop .instansi {
            font-size: 10px;
            font-weight: normal;
            margin: 0;
        }
        .tabel-identitas {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .tabel-identitas td {
            padding: 2px 4px;
            font-size: 9px;
            vertical-align: top;
        }
        .tabel-identitas .label {
            width: 130px;
            font-weight: bold;
        }
        .tabel-identitas .separator {
            width: 10px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #444;
            padding: 3px 4px;
            font-size: 8px;
            vertical-align: middle;
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
        .cell-minggu {
            width: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 7.5px;
        }
        .tabel-ttd {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .tabel-ttd td {
            width: 50%;
            vertical-align: top;
            font-size: 9px;
        }
        .ruang-ttd {
            height: 50px;
        }
        .toolbar {
            margin-bottom: 14px;
            padding: 8px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .btn {
            display: inline-block;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            margin-right: 6px;
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
    <h2>PROGRAM SEMESTER (PROSEM)</h2>
    <h1>KURIKULUM MERDEKA</h1>
    <p class="instansi">{{ config('sekolah.nama', 'Madrasah Tsanawiyah') }} · Tahun Pelajaran {{ $prosem->tahunAjaran->nama }}</p>
</div>

<table class="tabel-identitas">
    <tr>
        <td class="label">Satuan Pendidikan</td>
        <td class="separator">:</td>
        <td>{{ config('sekolah.nama', 'MTs At-Taufiq Cisurupan') }}</td>
        <td class="label">Kelas / Fase</td>
        <td class="separator">:</td>
        <td>{{ $prosem->kelas->nama }} / Fase {{ $prosem->fase }}</td>
    </tr>
    <tr>
        <td class="label">Mata Pelajaran</td>
        <td class="separator">:</td>
        <td>{{ $prosem->mataPelajaran->nama }}</td>
        <td class="label">Semester</td>
        <td class="separator">:</td>
        <td><strong>{{ $prosem->labelSemester() }}</strong></td>
    </tr>
    <tr>
        <td class="label">Guru Pengampu</td>
        <td class="separator">:</td>
        <td>{{ $prosem->gtk->nama }}</td>
        <td class="label">Alokasi Waktu</td>
        <td class="separator">:</td>
        <td><strong>{{ $totalJp }} JP</strong> ({{ $prosem->jp_per_minggu }} JP/minggu · {{ $prosem->total_minggu_efektif }} Minggu Efektif)</td>
    </tr>
</table>

<table class="data-table">
    <thead>
        <tr>
            <th rowspan="2" style="width: 25px;">No</th>
            <th rowspan="2" style="width: 175px;">Tujuan Pembelajaran (TP)</th>
            <th rowspan="2" style="width: 120px;">Materi Pokok</th>
            <th rowspan="2" style="width: 35px;">Alokasi Waktu</th>
            @foreach($daftarBulan as $bulan)
                <th colspan="5">{{ $bulan }}</th>
            @endforeach
            <th rowspan="2" style="width: 90px;">Keterangan</th>
        </tr>
        <tr>
            @foreach($daftarBulan as $bulan)
                <th class="cell-minggu">1</th>
                <th class="cell-minggu">2</th>
                <th class="cell-minggu">3</th>
                <th class="cell-minggu">4</th>
                <th class="cell-minggu">5</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td>{{ $item->tujuan_pembelajaran }}</td>
                <td>{{ $item->materi_pokok }}</td>
                <td class="text-center font-bold">{{ $item->alokasi_waktu_jp }} JP</td>

                @foreach($daftarBulan as $bulan)
                    @for($m = 1; $m <= 5; $m++)
                        @php
                            $jpDiMinggu = 0;
                            if (is_array($item->distribusi_minggu) && isset($item->distribusi_minggu[$bulan][$m - 1])) {
                                $jpDiMinggu = (int) $item->distribusi_minggu[$bulan][$m - 1];
                            } elseif (str_contains(mb_strtolower($item->bulan), mb_strtolower($bulan))) {
                                if (str_contains($item->minggu_ke, (string)$m)) {
                                    $jpDiMinggu = $prosem->jp_per_minggu;
                                }
                            }
                        @endphp
                        <td class="cell-minggu" style="{{ $jpDiMinggu > 0 ? 'background-color: #e6f4ea; font-weight: bold;' : '' }}">
                            {{ $jpDiMinggu > 0 ? $jpDiMinggu : '' }}
                        </td>
                    @endfor
                @endforeach

                <td>{{ $item->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 4 + (count($daftarBulan) * 5) + 1 }}" class="text-center" style="font-style: italic; color: #666; padding: 12px;">
                    Belum ada butir materi pembelajaran untuk Program Semester ini.
                </td>
            </tr>
        @endforelse

        <!-- BARIS TOTAL JP -->
        <tr class="bg-total">
            <td colspan="3" class="text-right"><strong>TOTAL ALOKASI WAKTU SEMESTER:</strong></td>
            <td class="text-center font-bold">{{ $totalJp }} JP</td>
            @foreach($daftarBulan as $bulan)
                @for($m = 1; $m <= 5; $m++)
                    @php
                        $subtotalMinggu = 0;
                        foreach ($items as $it) {
                            if (is_array($it->distribusi_minggu) && isset($it->distribusi_minggu[$bulan][$m - 1])) {
                                $subtotalMinggu += (int) $it->distribusi_minggu[$bulan][$m - 1];
                            } elseif (str_contains(mb_strtolower($it->bulan), mb_strtolower($bulan)) && str_contains($it->minggu_ke, (string)$m)) {
                                $subtotalMinggu += $prosem->jp_per_minggu;
                            }
                        }
                    @endphp
                    <td class="cell-minggu" style="font-weight: bold; background-color: #dce4ec;">
                        {{ $subtotalMinggu > 0 ? $subtotalMinggu : '' }}
                    </td>
                @endfor
            @endforeach
            <td class="text-center font-bold">{{ $totalJp }} JP Total</td>
        </tr>
    </tbody>
</table>

@if(!empty($prosem->catatan))
    <div style="margin-top: 6px; font-size: 8px; color: #444;">
        <strong>Catatan Pelaksanaan Kalender Pendidikan:</strong> {{ $prosem->catatan }}
    </div>
@endif

<table class="tabel-ttd">
    <tr>
        <td style="text-align: left; padding-left: 20px;">
            Mengetahui,<br>
            Kepala {{ config('sekolah.nama', 'Madrasah') }}<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $prosem->tahunAjaran->kepala_madrasah ?: '......................................................' }}</u></strong><br>
            NIP. {{ $prosem->tahunAjaran->nip_kepala_madrasah ?: '-' }}
        </td>
        <td style="text-align: right; padding-right: 20px;">
            {{ config('sekolah.tempat_tanda_tangan', 'Cisurupan') }}, {{ now()->translatedFormat('d F Y') }}<br>
            Guru Mata Pelajaran<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $prosem->gtk->nama }}</u></strong><br>
            NIP. {{ $prosem->gtk->nip ?: '-' }}
        </td>
    </tr>
</table>

</body>
</html>
