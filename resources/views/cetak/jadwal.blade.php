@php
    /** @var \App\Services\Jadwal\TampilanJadwal $tampilan */
    /** @var \App\Models\PenandaTangan|null $wakilKurikulum */
    $jadwal = $tampilan->jadwal;
    $kelas = $tampilan->kelas;
    $semester = $jadwal->semester === \App\Enums\Semester::Ganjil ? 'GANJIL' : 'GENAP';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Jadwal Pelajaran {{ $jadwal->judul() }}</title>
    <style>
        @page { size: A4 landscape; margin: 7mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 6.5px; color: #000; margin: 0; }
        h1 { font-size: 10px; text-align: center; margin: 0; line-height: 1.35; }
        .tata-letak { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .tata-letak > tbody > tr > td { vertical-align: top; padding: 0; }
        table.grid, table.legenda { border-collapse: collapse; width: 100%; }
        table.grid { margin-bottom: 6px; }
        .grid th, .grid td, .legenda th, .legenda td { border: 0.6px solid #000; padding: 1.5px 2px; text-align: center; }
        .grid th, .legenda th { font-weight: bold; }
        .grid td.waktu { white-space: nowrap; font-size: 6px; }
        .grid td.jeda { font-style: italic; }
        .grid td.kode { font-weight: bold; white-space: nowrap; }
        .grid td.hari-awal, .grid th.hari-awal { border-left: 1.4px solid #000; }
        .legenda td.kiri { text-align: left; }
        .legenda tfoot td { font-weight: bold; }
        .ttd { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .ttd td { width: 50%; vertical-align: top; text-align: left; padding-left: 30px; font-size: 7px; }
        .ttd .nama { margin-top: 34px; font-weight: bold; text-decoration: underline; }
        @media print { .tanpa-cetak { display: none; } }
        .tanpa-cetak { text-align: right; margin-bottom: 4px; }
    </style>
</head>
<body>
    @unless ($untukPdf)
        <div class="tanpa-cetak"><button type="button" onclick="window.print()">Cetak</button></div>
    @endunless

    <h1>
        JADWAL PELAJARAN<br>
        SEMESTER {{ $semester }} TAHUN PELAJARAN {{ $jadwal->tahunAjaran->nama }}<br>
        {{ strtoupper(config('sekolah.jenjang')) }} {{ strtoupper(config('sekolah.nama_singkat')) }} {{ strtoupper(\Illuminate\Support\Str::before(config('sekolah.wilayah'), ',')) }}
    </h1>

    <table class="tata-letak">
        <tr>
            <td style="width: 46%; padding-right: 8px;">
                @foreach ($tampilan->kelompokHari() as $kelompok)
                    <table class="grid">
                        <thead>
                            <tr>
                                @foreach ($kelompok as $hari)
                                    <th colspan="{{ $kelas->count() + 1 }}" class="hari-awal">{{ $hari->getLabel() }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($kelompok as $hari)
                                    <th class="hari-awal">Waktu</th>
                                    @foreach ($kelas as $satuKelas)
                                        <th>{{ $satuKelas->nama }}</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @for ($baris = 0; $baris < $tampilan->jumlahBaris($kelompok); $baris++)
                                <tr>
                                    @foreach ($kelompok as $hari)
                                        @php($jam = $tampilan->pola->baris($hari)->get($baris))
                                        @if ($jam === null)
                                            <td colspan="{{ $kelas->count() + 1 }}" class="hari-awal"></td>
                                        @elseif ($jam->jp_ke === null)
                                            <td class="waktu hari-awal">{{ $jam->rentang() }}</td>
                                            <td colspan="{{ $kelas->count() }}" class="jeda">{{ $jam->label() }}</td>
                                        @else
                                            <td class="waktu hari-awal">{{ $jam->jp_ke }}. {{ $jam->rentang() }}</td>
                                            @foreach ($kelas as $satuKelas)
                                                @php($kode = $tampilan->kodeUntuk($tampilan->isi($satuKelas->id, $hari, $jam->jp_ke)))
                                                <td class="kode" @if ($kode) style="background: {{ $kode['warna'] }};" @endif>{{ $kode['kode'] ?? '' }}</td>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                @endforeach
            </td>
            <td style="width: 54%;">
                <table class="legenda">
                    <thead>
                        <tr>
                            <th rowspan="2">No</th>
                            <th rowspan="2">Nama Guru</th>
                            <th rowspan="2">Kode</th>
                            <th rowspan="2">Mata Pelajaran</th>
                            <th colspan="{{ $kelas->count() }}">Kelas</th>
                            <th rowspan="2">Jumlah</th>
                            <th rowspan="2">Ket.</th>
                        </tr>
                        <tr>
                            @foreach ($kelas as $satuKelas)
                                <th>{{ $satuKelas->nama }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tampilan->legenda as $nomor => $guru)
                            @foreach ($guru['baris'] as $baris)
                                <tr>
                                    @if ($loop->first)
                                        <td rowspan="{{ count($guru['baris']) }}">{{ $nomor + 1 }}</td>
                                        <td rowspan="{{ count($guru['baris']) }}" class="kiri">{{ $guru['guru'] }}</td>
                                    @endif
                                    <td style="background: {{ $baris['warna'] }}; font-weight: bold;">{{ $baris['kode'] }}</td>
                                    <td class="kiri">{{ $baris['mapel'] }}</td>
                                    @foreach ($kelas as $satuKelas)
                                        <td>{{ $baris['jp'][$satuKelas->id] ?? 0 }}</td>
                                    @endforeach
                                    <td>{{ $baris['jumlah'] }}</td>
                                    <td>{{ $baris['terpasang'] === $baris['jumlah'] ? 'Ok' : $baris['terpasang'].' JP' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Total Jam Pelajaran</td>
                            @foreach ($kelas as $satuKelas)
                                <td>{{ $tampilan->totalPerKelas[$satuKelas->id] ?? 0 }}</td>
                            @endforeach
                            <td>{{ $tampilan->totalJp() }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <table class="ttd">
                    <tr>
                        <td>
                            <br>Kepala Madrasah
                            <div class="nama">{{ $jadwal->tahunAjaran->kepala_madrasah ?? '....................................' }}</div>
                            @if (filled($jadwal->tahunAjaran->nip_kepala_madrasah))
                                NIP. {{ $jadwal->tahunAjaran->nip_kepala_madrasah }}
                            @endif
                        </td>
                        <td>
                            {{ config('sekolah.tempat_tanda_tangan') }}, {{ ($jadwal->diterbitkan_pada ?? now())->translatedFormat('j F Y') }}<br>
                            {{ $wakilKurikulum->jabatan ?? 'Wakil Kepala Bidang Kurikulum' }}
                            <div class="nama">{{ $wakilKurikulum->nama ?? '....................................' }}</div>
                            @if (filled($wakilKurikulum?->nip))
                                NIP. {{ $wakilKurikulum->nip }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
