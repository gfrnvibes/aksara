@php
    $tampil = fn ($value): string => filled($value) ? (string) $value : '-';
    $tanggal = fn ($date): string => $date?->translatedFormat('d F Y') ?? '-';

    $raporPerSemester = $raporTahunIni->keyBy(fn ($rapor): int => $rapor->semester->value);
    $semesterGanjil = $raporPerSemester->get(\App\Enums\Semester::Ganjil->value);
    $semesterGenap = $raporPerSemester->get(\App\Enums\Semester::Genap->value);
    $daftarSemester = [$semesterGanjil, $semesterGenap];
    $nilaiPerMapel = collect($daftarSemester)->map(fn ($rapor) => $rapor?->nilaiRapor->keyBy('mata_pelajaran_id') ?? collect());
    $raporTerakhir = $semesterGenap ?? $semesterGanjil;
@endphp
<div class="sheet {{ $pageBreak ? 'page-break' : '' }}">
    <h1>DATA NILAI SISWA MADRASAH TSANAWIYAH</h1>
    <table class="data" style="margin-bottom: 8px;">
        <tr><td style="width: 18%;">Nama Siswa</td><td class="sep">:</td><td>{{ $siswa->nama_lengkap }}</td><td style="width: 22%;">Tahun Pelajaran</td><td class="sep">:</td><td>{{ $tahunAjaran }}</td></tr>
        <tr><td>No Induk / NISN</td><td class="sep">:</td><td>{{ $siswa->nis }} / {{ $tampil($siswa->nisn) }}</td><td>Kelas</td><td class="sep">:</td><td>{{ $tampil($raporTerakhir?->kelas?->nama) }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="3" style="width: 20px;">No</th>
                <th rowspan="3">Mata Pelajaran</th>
                @foreach ($daftarSemester as $index => $rapor)
                    <th colspan="6">Semester {{ $index + 1 }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($daftarSemester as $rapor)
                    <th rowspan="2">KKM</th>
                    <th colspan="2">Pengetahuan</th>
                    <th colspan="2">Keterampilan</th>
                    <th rowspan="2">Sikap</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($daftarSemester as $rapor)
                    <th>Angka</th><th>Pred</th><th>Angka</th><th>Pred</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($mataPelajaran as $kelompok => $daftarMapel)
                <tr class="kelompok"><td colspan="14">Kelompok {{ $kelompok }}</td></tr>
                @foreach ($daftarMapel as $mapel)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $mapel->nama }}</td>
                        @foreach ($nilaiPerMapel as $nilaiSemester)
                            @php
                                $nilai = $nilaiSemester->get($mapel->id);
                            @endphp
                            <td class="center">{{ $nilai?->kkm ?? '' }}</td>
                            <td class="center">{{ $nilai?->nilai_pengetahuan ?? '' }}</td>
                            <td class="center">{{ $nilai?->predikat_pengetahuan ?? '' }}</td>
                            <td class="center">{{ $nilai?->nilai_keterampilan ?? '' }}</td>
                            <td class="center">{{ $nilai?->predikat_keterampilan ?? '' }}</td>
                            <td class="center">{{ $nilai?->sikap ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="layout" style="margin-top: 10px;">
        <tr>
            @foreach ($daftarSemester as $index => $rapor)
                <td style="width: 50%; {{ $index === 0 ? 'padding-right: 6px;' : 'padding-left: 6px;' }}">
                    <h2>Semester {{ $index + 1 }}</h2>
                    <table class="grid">
                        <thead><tr><th>Ekstrakurikuler</th><th style="width: 40px;">Nilai</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @forelse ($rapor?->nilaiEkstrakurikuler ?? [] as $ekstra)
                                <tr><td>{{ $ekstra->ekstrakurikuler->nama }}</td><td class="center">{{ $ekstra->nilai }}</td><td>{{ $ekstra->keterangan }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="center">-</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="grid" style="margin-top: 6px;">
                        <tr><th style="text-align: left;">Akhlak</th><td class="center">{{ $tampil($rapor?->nilai_akhlak) }}</td><th style="text-align: left;">Kepribadian</th><td class="center">{{ $tampil($rapor?->nilai_kepribadian) }}</td></tr>
                    </table>

                    <table class="grid" style="margin-top: 6px;">
                        <tr><th colspan="2">Ketidakhadiran</th></tr>
                        <tr><td>Sakit</td><td class="center">{{ $rapor?->sakit ?? '-' }} hari</td></tr>
                        <tr><td>Izin</td><td class="center">{{ $rapor?->izin ?? '-' }} hari</td></tr>
                        <tr><td>Tanpa Keterangan</td><td class="center">{{ $rapor?->tanpa_keterangan ?? '-' }} hari</td></tr>
                    </table>

                    @if (filled($rapor?->prestasi))
                        <table class="grid" style="margin-top: 6px;">
                            <thead><tr><th>Prestasi</th><th>Nilai</th><th>Keterangan</th></tr></thead>
                            <tbody>
                                @foreach ($rapor->prestasi as $prestasi)
                                    <tr><td>{{ $prestasi['prestasi'] ?? '' }}</td><td>{{ $prestasi['nilai'] ?? '' }}</td><td>{{ $prestasi['keterangan'] ?? '' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <table class="grid" style="margin-top: 6px;">
                        <tr><th style="text-align: left;">Catatan Wali Kelas</th></tr>
                        <tr><td style="height: 48px; vertical-align: top; text-align: justify;">{{ $tampil($rapor?->catatan) }}</td></tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Keputusan Hasil Semester I dan II</h2>
    <table class="grid">
        <tr>
            <td>{{ $semesterGenap?->keputusan?->getLabel() ?? '-' }}</td>
            <td style="width: 40%;">Tanggal: {{ $tanggal($semesterGenap?->tanggal_keputusan) }}</td>
        </tr>
    </table>

    @php
        $tahunAjaranRapor = $raporTerakhir?->tahunAjaran;
        $waliKelas = $raporTerakhir?->penandaTanganWaliKelas() ?? ['nama' => null, 'nip' => null];
        $tanggalTandaTangan = $semesterGenap?->tanggal_keputusan?->translatedFormat('d F Y') ?? '............................';
    @endphp
    <table class="layout" style="margin-top: 20px;">
        <tr>
            <td style="width: 50%; text-align: center;">
                <br>
                Wali Kelas
                <div style="height: 64px;"></div>
                <strong><u>{{ $waliKelas['nama'] ?: '...............................................' }}</u></strong><br>
                NIP. {{ $tampil($waliKelas['nip']) }}
            </td>
            <td style="width: 50%; text-align: center;">
                {{ config('sekolah.tempat_tanda_tangan') }}, {{ $tanggalTandaTangan }}<br>
                Kepala Madrasah
                <div style="height: 64px;"></div>
                <strong><u>{{ $tahunAjaranRapor?->kepala_madrasah ?: '...............................................' }}</u></strong><br>
                NIP. {{ $tampil($tahunAjaranRapor?->nip_kepala_madrasah) }}
            </td>
        </tr>
    </table>
</div>
