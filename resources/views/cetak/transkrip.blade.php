@extends('cetak.layouts.surat')

@php
    /** @var \App\Models\Siswa $siswa */
    /** @var list<array{label: string, tahun: string}> $kolomSemester */
    /** @var array<string, list<array{nama: string, nilai: list<int|null>, rata: float|null}>> $mapelPerKelompok */
    $angka = fn (?float $nilai): string => $nilai === null ? '' : number_format($nilai, 2, ',', '.');
@endphp

@section('isi')
    <table class="data" style="margin-left: 0; width: 100%;">
        <tr><td class="label">Nama</td><td class="sep">:</td><td><strong>{{ $siswa->nama_lengkap }}</strong></td></tr>
        <tr><td class="label">NIS / NISN</td><td class="sep">:</td><td>{{ $siswa->nis }} / {{ $siswa->nisn ?: '-' }}</td></tr>
        <tr><td class="label">Tempat, Tanggal Lahir</td><td class="sep">:</td><td>{{ $siswa->tempat_lahir ?: '-' }}, {{ $siswa->tanggal_lahir?->translatedFormat('d F Y') ?? '-' }}</td></tr>
        <tr><td class="label">Status</td><td class="sep">:</td><td>{{ $siswa->status->getLabel() }}{{ $siswa->tanggal_tamat ? ' ('.$siswa->tanggal_tamat->translatedFormat('d F Y').')' : '' }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2">Mata Pelajaran</th>
                @foreach ($kolomSemester as $kolom)
                    <th>{{ $kolom['label'] }}</th>
                @endforeach
                <th rowspan="2" style="width: 50px;">Rata-rata</th>
            </tr>
            <tr>
                @foreach ($kolomSemester as $kolom)
                    <th style="font-weight: normal; font-size: 0.85em;">{{ $kolom['tahun'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($mapelPerKelompok as $kelompok => $daftarMapel)
                <tr class="kelompok"><td colspan="{{ 3 + count($kolomSemester) }}">{{ $kelompok }}</td></tr>
                @foreach ($daftarMapel as $mapel)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $mapel['nama'] }}</td>
                        @foreach ($mapel['nilai'] as $nilai)
                            <td class="center">{{ $nilai }}</td>
                        @endforeach
                        <td class="center">{{ $angka($mapel['rata']) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="{{ 3 + count($kolomSemester) }}" class="center">Belum ada nilai rapor.</td></tr>
            @endforelse
        </tbody>
        @if ($mapelPerKelompok !== [])
            <tfoot>
                <tr>
                    <td colspan="{{ 2 + count($kolomSemester) }}" style="text-align: right;"><strong>Rata-rata Keseluruhan</strong></td>
                    <td class="center"><strong>{{ $angka($rataRataKeseluruhan) }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <p style="margin-top: 6px; font-size: 0.85em;">
        Nilai tiap semester adalah Nilai Akhir (Kurikulum Merdeka) atau rata-rata nilai Pengetahuan dan Keterampilan (K13), sesuai kurikulum tahun pelajarannya.
    </p>
@endsection
