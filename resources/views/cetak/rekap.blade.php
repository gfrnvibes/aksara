<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Rekap Buku Induk</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #000; }
        h1 { font-size: 13px; text-align: center; margin: 0 0 2px; }
        p.sub { text-align: center; margin: 0 0 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 2px 3px; vertical-align: top; }
        th { background: #f3f4f6; }
        td.center { text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @if ($isi->termasukSiswa())
        <h1>DAFTAR DATA SISWA</h1>
        <p class="sub">Dicetak {{ now()->translatedFormat('d F Y H:i') }} &middot; {{ $daftarSiswa->count() }} siswa</p>
        <table>
            <thead>
                <tr>
                    <th>No</th><th>NIS</th><th>NISN</th><th>Nama Lengkap</th><th>L/P</th><th>Tempat, Tgl Lahir</th>
                    <th>Agama</th><th>Kelas</th><th>Status</th><th>Nama Ayah</th><th>Nama Ibu</th><th>Wali</th><th>Alamat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarSiswa as $siswa)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $siswa->nis }}</td>
                        <td>{{ $siswa->nisn }}</td>
                        <td>{{ $siswa->nama_lengkap }}</td>
                        <td class="center">{{ $siswa->jenis_kelamin->value }}</td>
                        <td>{{ $siswa->tempat_lahir }}{{ $siswa->tanggal_lahir ? ', '.$siswa->tanggal_lahir->format('d-m-Y') : '' }}</td>
                        <td>{{ $siswa->agama?->getLabel() }}</td>
                        <td>{{ $siswa->kelas?->nama }}</td>
                        <td>{{ $siswa->status->getLabel() }}</td>
                        <td>{{ $siswa->ayah?->nama }}</td>
                        <td>{{ $siswa->ibu?->nama }}</td>
                        <td>{{ $siswa->wali?->nama }}</td>
                        <td>{{ $siswa->alamat }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="center">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if ($isi->termasukNilai())
        <div class="{{ $isi->termasukSiswa() ? 'page-break' : '' }}">
            <h1>REKAP DATA NILAI SISWA</h1>
            <p class="sub">Nilai K13 ditulis Pengetahuan/Keterampilan, Kurikulum Merdeka ditulis Nilai Akhir &middot; {{ $daftarRapor->count() }} rapor</p>
            <table>
                <thead>
                    <tr>
                        <th>No</th><th>NIS</th><th>Nama</th><th>Tahun Ajaran</th><th>Smt</th><th>Kelas</th>
                        @foreach ($mataPelajaran as $mapel)
                            <th>{{ $mapel->nama }}</th>
                        @endforeach
                        <th>Rata-rata</th><th>S/I/A</th><th>Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarRapor as $rapor)
                        @php
                            $nilaiPerMapel = $rapor->nilaiRapor->keyBy('mata_pelajaran_id');
                            $skema = $rapor->skema();
                            $skor = $rapor->nilaiRapor->flatMap(fn ($nilai) => $skema->nilaiUntukRataRata($nilai));
                            $rataRata = $skor->isEmpty() ? null : $skor->avg();
                        @endphp
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $rapor->siswa->nis }}</td>
                            <td>{{ $rapor->siswa->nama_lengkap }}</td>
                            <td>{{ $rapor->tahunAjaran->nama }}</td>
                            <td class="center">{{ $rapor->semester->value }}</td>
                            <td>{{ $rapor->kelas->nama }}</td>
                            @foreach ($mataPelajaran as $mapel)
                                @php
                                    $nilai = $nilaiPerMapel->get($mapel->id);
                                @endphp
                                <td class="center">{{ $nilai ? collect(array_keys($skema->kolomNilai()))->map(fn (string $kolom) => $nilai->getAttribute($kolom) ?? '-')->implode('/') : '' }}</td>
                            @endforeach
                            <td class="center">{{ $rataRata !== null ? number_format($rataRata, 2, ',', '.') : '-' }}</td>
                            <td class="center">{{ $rapor->sakit }}/{{ $rapor->izin }}/{{ $rapor->tanpa_keterangan }}</td>
                            <td>{{ $rapor->keputusan?->getLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 9 + $mataPelajaran->count() }}" class="center">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
