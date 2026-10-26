<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $leger->judul() }}</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7px; color: #000; }
        h1 { font-size: 12px; text-align: center; margin: 0 0 2px; }
        p.sub { text-align: center; margin: 0 0 6px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 2px; }
        th { background: #f3f4f6; }
        td.center, th { text-align: center; }
        td.nama { white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <h1>LEGER NILAI {{ strtoupper(config('sekolah.nama')) }}</h1>
    <p class="sub">Kelas {{ $leger->kelas->nama }} &middot; {{ $leger->semester->getLabel() }} &middot; Tahun Ajaran {{ $leger->tahunAjaran->nama }}</p>

    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">NIS</th>
                <th rowspan="2">Nama Siswa</th>
                @foreach ($leger->mataPelajaran as $mapel)
                    <th colspan="2">{{ $mapel->nama }}</th>
                @endforeach
                <th rowspan="2">Jml</th>
                <th rowspan="2">Rata</th>
                <th rowspan="2">Rank</th>
            </tr>
            <tr>
                @foreach ($leger->mataPelajaran as $mapel)
                    <th>P</th><th>K</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($leger->baris as $baris)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $baris['siswa']->nis }}</td>
                    <td class="nama">{{ $baris['siswa']->nama_lengkap }}</td>
                    @foreach ($leger->mataPelajaran as $mapel)
                        <td class="center">{{ $baris['nilai'][$mapel->id]['p'] }}</td>
                        <td class="center">{{ $baris['nilai'][$mapel->id]['k'] }}</td>
                    @endforeach
                    <td class="center">{{ $baris['rata_rata'] === null ? '' : $baris['jumlah'] }}</td>
                    <td class="center">{{ $baris['rata_rata'] === null ? '' : number_format($baris['rata_rata'], 2, ',', '.') }}</td>
                    <td class="center">{{ $baris['peringkat'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + ($leger->mataPelajaran->count() * 2) }}" class="center">Belum ada siswa di kelas ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Rata-rata kelas</td>
                @foreach ($leger->mataPelajaran as $mapel)
                    <td class="center">{{ $rataRataMapel[$mapel->id]['p'] === null ? '' : number_format($rataRataMapel[$mapel->id]['p'], 1, ',', '.') }}</td>
                    <td class="center">{{ $rataRataMapel[$mapel->id]['k'] === null ? '' : number_format($rataRataMapel[$mapel->id]['k'], 1, ',', '.') }}</td>
                @endforeach
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top: 6px;">P = Pengetahuan (KI-3), K = Keterampilan (KI-4). Peringkat berdasarkan rata-rata seluruh nilai; rata-rata yang sama mendapat peringkat yang sama. Dicetak {{ now()->translatedFormat('d F Y H:i') }}.</p>
</body>
</html>
