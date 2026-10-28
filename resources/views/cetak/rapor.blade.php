@php
    /** @var \App\Models\Rapor $rapor */
    /** @var \App\Services\Kurikulum\SkemaPenilaian $skema */
    $tampil = fn ($value): string => filled($value) ? (string) $value : '-';
    $siswa = $rapor->siswa;
    $merdeka = $skema->pakaiCapaianKompetensi();
    $kolomSkor = $skema->kolomNilai();
    $jumlahKolom = $merdeka ? 2 + count($kolomSkor) + 1 : 8;
    $tanggal = $rapor->tanggal_keputusan?->translatedFormat('d F Y') ?? '............................';
    $prestasi = array_pad($rapor->prestasi ?? [], 3, null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapor {{ $siswa->nama_lengkap }} - {{ $rapor->semester->getLabel() }} {{ $rapor->tahunAjaran->nama }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body { font-family: {{ $untukPdf ? '"DejaVu Sans", sans-serif' : 'Arial, Helvetica, sans-serif' }}; font-size: {{ $untukPdf ? '9px' : '11px' }}; color: #000; margin: 0; background: {{ $untukPdf ? '#fff' : '#e5e7eb' }}; }
        .sheet { background: #fff; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 8px; }
        .kop .instansi { font-size: 1.15em; font-weight: bold; }
        .kop .madrasah { font-size: 1.6em; font-weight: bold; }
        .kop .alamat { font-style: italic; }
        h1 { font-size: 1.35em; text-align: center; margin: 12px 0 6px; }
        h2 { font-size: 1.1em; margin: 12px 0 4px; }
        table { border-collapse: collapse; width: 100%; }
        table.identitas td { padding: 1px 3px; vertical-align: top; }
        table.identitas td.sep { width: 10px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        table.grid th { background: #f3f4f6; }
        table.grid .center { text-align: center; }
        table.grid tr.kelompok td { font-weight: bold; background: #fafafa; }
        table.grid td.capaian { text-align: justify; font-size: 0.92em; }
        .kotak { border: 1px solid #000; min-height: 48px; padding: 4px 6px; text-align: justify; }
        .ttd td { text-align: center; vertical-align: top; padding-top: 12px; }
        .ttd .ruang { height: 60px; }
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
        <div class="toolbar"><button type="button" onclick="window.print()">Cetak</button></div>
    @endunless

    <div class="sheet">
        <div class="kop">
            <div class="instansi">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
            <div class="madrasah">{{ strtoupper(config('sekolah.nama')) }}</div>
            @if (filled(config('sekolah.alamat')))
                <div class="alamat">{{ config('sekolah.alamat') }}</div>
            @endif
            <div class="alamat">{{ config('sekolah.wilayah') }}</div>
        </div>

        <table class="identitas">
            <tr>
                <td style="width: 16%;">Nama</td><td class="sep">:</td><td style="width: 44%;">{{ strtoupper($siswa->nama_lengkap) }}</td>
                <td style="width: 16%;">Kelas</td><td class="sep">:</td><td>{{ $rapor->kelas->nama }}</td>
            </tr>
            <tr>
                <td>NIS/NISN</td><td class="sep">:</td><td>{{ $siswa->nis }} / {{ $tampil($siswa->nisn) }}</td>
                @if ($fase !== null)
                    <td>Fase</td><td class="sep">:</td><td>{{ $fase }}</td>
                @else
                    <td></td><td></td><td></td>
                @endif
            </tr>
            <tr>
                <td>Madrasah</td><td class="sep">:</td><td>{{ config('sekolah.nama') }}</td>
                <td>Semester</td><td class="sep">:</td><td>{{ $rapor->semester->getLabel() }}</td>
            </tr>
            <tr>
                <td>Alamat</td><td class="sep">:</td><td>{{ $tampil(config('sekolah.alamat')) }}</td>
                <td>Tahun Pelajaran</td><td class="sep">:</td><td>{{ $rapor->tahunAjaran->nama }}</td>
            </tr>
        </table>

        <h1>CAPAIAN HASIL BELAJAR</h1>

        <table class="grid">
            <thead>
                @if ($merdeka)
                    <tr>
                        <th style="width: 24px;">No</th>
                        <th style="width: 30%;">Mata Pelajaran</th>
                        @foreach (array_keys($kolomSkor) as $kolom)
                            <th style="width: 50px;">{{ $skema->labelKolomNilai($kolom) }}</th>
                        @endforeach
                        <th>Capaian Kompetensi</th>
                    </tr>
                @else
                    <tr>
                        <th rowspan="2" style="width: 24px;">No</th>
                        <th rowspan="2">Mata Pelajaran</th>
                        <th rowspan="2">{{ $skema->labelKkm() }}</th>
                        <th colspan="2">Pengetahuan</th>
                        <th colspan="2">Keterampilan</th>
                        <th rowspan="2">Sikap</th>
                    </tr>
                    <tr><th>Nilai</th><th>Predikat</th><th>Nilai</th><th>Predikat</th></tr>
                @endif
            </thead>
            <tbody>
                @forelse ($nilaiPerKelompok as $kelompok => $daftarNilai)
                    <tr class="kelompok"><td colspan="{{ $jumlahKolom }}">{{ $kelompok }}</td></tr>
                    @foreach ($daftarNilai as $nilai)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $nilai->mataPelajaran->nama }}</td>
                            @if ($merdeka)
                                @foreach (array_keys($kolomSkor) as $kolom)
                                    <td class="center">{{ $nilai->getAttribute($kolom) }}</td>
                                @endforeach
                                <td class="capaian">{!! nl2br(e($nilai->capaian_kompetensi)) !!}</td>
                            @else
                                <td class="center">{{ $nilai->kkm }}</td>
                                <td class="center">{{ $nilai->nilai_pengetahuan }}</td>
                                <td class="center">{{ $nilai->predikat_pengetahuan }}</td>
                                <td class="center">{{ $nilai->nilai_keterampilan }}</td>
                                <td class="center">{{ $nilai->predikat_keterampilan }}</td>
                                <td class="center">{{ $nilai->sikap }}</td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="{{ $jumlahKolom }}" class="center">Belum ada nilai mata pelajaran.</td></tr>
                @endforelse
                @if ($merdeka)
                    <tr>
                        <td colspan="2"><strong>Jumlah</strong></td>
                        <td class="center"><strong>{{ $jumlahNilai }}</strong></td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>

        <h2>Ekstrakurikuler</h2>
        <table class="grid">
            <thead><tr><th style="width: 24px;">No</th><th style="width: 34%;">Kegiatan Ekstrakurikuler</th><th style="width: 14%;">Nilai</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse ($rapor->nilaiEkstrakurikuler as $ekstra)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $ekstra->ekstrakurikuler->nama }}</td>
                        <td class="center">{{ $skema->labelNilaiEkstrakurikuler($ekstra->nilai) }}</td>
                        <td>{{ $ekstra->keterangan }}</td>
                    </tr>
                @empty
                    <tr><td class="center">1</td><td></td><td></td><td></td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Prestasi</h2>
        <table class="grid">
            <thead><tr><th style="width: 24px;">No</th><th style="width: 48%;">Jenis Prestasi</th><th>Keterangan</th></tr></thead>
            <tbody>
                @foreach ($prestasi as $baris)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $baris['prestasi'] ?? '' }}{{ filled($baris['nilai'] ?? null) ? ' ('.$baris['nilai'].')' : '' }}</td>
                        <td>{{ $baris['keterangan'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($skema->pakaiAkhlakKepribadian())
            <h2>Akhlak dan Kepribadian</h2>
            <table class="grid">
                <tr><td style="width: 50%;">Akhlak</td><td class="center">{{ $tampil($rapor->nilai_akhlak) }}</td></tr>
                <tr><td>Kepribadian</td><td class="center">{{ $tampil($rapor->nilai_kepribadian) }}</td></tr>
            </table>
        @endif

        <h2>Ketidakhadiran</h2>
        <table class="grid">
            <tr><td style="width: 50%;"><strong>Sakit</strong></td><td class="center" style="width: 15%;">{{ $rapor->sakit }}</td><td>Hari</td></tr>
            <tr><td><strong>Izin</strong></td><td class="center">{{ $rapor->izin }}</td><td>Hari</td></tr>
            <tr><td><strong>Alpa</strong></td><td class="center">{{ $rapor->tanpa_keterangan }}</td><td>Hari</td></tr>
        </table>

        <h2>Catatan Wali Kelas</h2>
        <div class="kotak">{{ $rapor->catatan }}</div>

        <h2>Tanggapan Orang Tua/Wali</h2>
        <div class="kotak"></div>

        @if ($keputusan !== null)
            <p><strong>Keterangan Kenaikan Kelas: {{ $keputusan }}</strong></p>
        @endif

        <table class="ttd">
            <tr>
                <td style="width: 50%;">
                    <br>Orang Tua/Wali
                    <div class="ruang"></div>
                    ...............................................
                </td>
                <td style="width: 50%;">
                    {{ config('sekolah.tempat_tanda_tangan') }}, {{ $tanggal }}<br>Wali Kelas
                    <div class="ruang"></div>
                    <strong><u>{{ $waliKelas['nama'] ?: '...............................................' }}</u></strong><br>
                    NIP. {{ $tampil($waliKelas['nip']) }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    Mengetahui<br>Kepala Madrasah
                    <div class="ruang"></div>
                    <strong><u>{{ $rapor->tahunAjaran->kepala_madrasah ?: '...............................................' }}</u></strong><br>
                    NIP. {{ $tampil($rapor->tahunAjaran->nip_kepala_madrasah) }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
