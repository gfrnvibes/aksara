<div class="halaman-dokumen halaman-ame">
    <div class="header-kop">
        <h2>ANALISIS MINGGU EFEKTIF (AME)</h2>
        <h1>{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</h1>
        <p class="instansi">TAHUN PELAJARAN {{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }}</p>
    </div>

    <table class="tabel-identitas">
        <tr>
            <td class="label">Mata Pelajaran</td>
            <td class="separator">:</td>
            <td><strong>{{ $perangkat->mataPelajaran?->nama ?? '-' }}</strong></td>
            <td class="label">Kelas / Fase</td>
            <td class="separator">:</td>
            <td>Kelas {{ $perangkat->kelas ?? 'VII' }} / Fase {{ $perangkat->fase ?? 'D' }}</td>
        </tr>
        <tr>
            <td class="label">Satuan Pendidikan</td>
            <td class="separator">:</td>
            <td>{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</td>
            <td class="label">Alokasi Waktu</td>
            <td class="separator">:</td>
            <td><strong>{{ $perangkat->jp_per_minggu ?? 5 }} JP / Minggu</strong></td>
        </tr>
    </table>

    {{-- SEMESTER 1 --}}
    <h3 class="section-title">I. SEMESTER 1 (JULI – DESEMBER)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th style="width: 140px;">Bulan</th>
                <th style="width: 80px;" class="text-center">Jml Minggu</th>
                <th style="width: 100px;" class="text-center">Mgg Tidak Efektif</th>
                <th style="width: 100px;" class="text-center font-bold">Minggu Efektif</th>
                <th>Keterangan / Rincian Kegiatan Non-Efektif</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sem1 = $perangkat->data_ame['semester_1'] ?? [];
                $totJml1 = 0; $totTdk1 = 0; $totEfk1 = 0;
            @endphp
            @forelse ($sem1 as $i => $row)
                @php
                    $totJml1 += (int)($row['jml_minggu'] ?? 0);
                    $totTdk1 += (int)($row['mgg_tidak_efektif'] ?? 0);
                    $totEfk1 += (int)($row['mgg_efektif'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $row['bulan'] ?? '-' }}</td>
                    <td class="text-center">{{ $row['jml_minggu'] ?? 0 }}</td>
                    <td class="text-center">{{ $row['mgg_tidak_efektif'] ?? 0 }}</td>
                    <td class="text-center font-bold">{{ $row['mgg_efektif'] ?? 0 }}</td>
                    <td>{{ $row['keterangan'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Data Analisis Minggu Efektif Semester 1 belum disusun.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right">JUMLAH SEMESTER 1 :</th>
                <th class="text-center">{{ $totJml1 }}</th>
                <th class="text-center">{{ $totTdk1 }}</th>
                <th class="text-center font-bold">{{ $totEfk1 }}</th>
                <th class="text-left font-bold">Total JP: {{ $totEfk1 * ($perangkat->jp_per_minggu ?: 5) }} JP ({{ $totEfk1 }} mgg × {{ $perangkat->jp_per_minggu ?: 5 }} JP)</th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 15px;"></div>

    {{-- SEMESTER 2 --}}
    <h3 class="section-title">II. SEMESTER 2 (JANUARI – JUNI)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th style="width: 140px;">Bulan</th>
                <th style="width: 80px;" class="text-center">Jml Minggu</th>
                <th style="width: 100px;" class="text-center">Mgg Tidak Efektif</th>
                <th style="width: 100px;" class="text-center font-bold">Minggu Efektif</th>
                <th>Keterangan / Rincian Kegiatan Non-Efektif</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sem2 = $perangkat->data_ame['semester_2'] ?? [];
                $totJml2 = 0; $totTdk2 = 0; $totEfk2 = 0;
            @endphp
            @forelse ($sem2 as $i => $row)
                @php
                    $totJml2 += (int)($row['jml_minggu'] ?? 0);
                    $totTdk2 += (int)($row['mgg_tidak_efektif'] ?? 0);
                    $totEfk2 += (int)($row['mgg_efektif'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $row['bulan'] ?? '-' }}</td>
                    <td class="text-center">{{ $row['jml_minggu'] ?? 0 }}</td>
                    <td class="text-center">{{ $row['mgg_tidak_efektif'] ?? 0 }}</td>
                    <td class="text-center font-bold">{{ $row['mgg_efektif'] ?? 0 }}</td>
                    <td>{{ $row['keterangan'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Data Analisis Minggu Efektif Semester 2 belum disusun.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right">JUMLAH SEMESTER 2 :</th>
                <th class="text-center">{{ $totJml2 }}</th>
                <th class="text-center">{{ $totTdk2 }}</th>
                <th class="text-center font-bold">{{ $totEfk2 }}</th>
                <th class="text-left font-bold">Total JP: {{ $totEfk2 * ($perangkat->jp_per_minggu ?: 5) }} JP ({{ $totEfk2 }} mgg × {{ $perangkat->jp_per_minggu ?: 5 }} JP)</th>
            </tr>
        </tfoot>
    </table>

    {{-- RINGKASAN SETAHUN --}}
    <div class="ringkasan-box" style="margin-top: 15px; border: 1px solid #111; padding: 8px 12px; font-size: 10pt;">
        <strong>RINGKASAN TOTAL JAM PELAJARAN SETAHUN :</strong>
        <ul style="margin: 4px 0 0 16px;">
            <li>Semester 1 : {{ $totEfk1 }} minggu efektif × {{ $perangkat->jp_per_minggu ?: 5 }} JP = <strong>{{ $totEfk1 * ($perangkat->jp_per_minggu ?: 5) }} JP</strong></li>
            <li>Semester 2 : {{ $totEfk2 }} minggu efektif × {{ $perangkat->jp_per_minggu ?: 5 }} JP = <strong>{{ $totEfk2 * ($perangkat->jp_per_minggu ?: 5) }} JP</strong></li>
            <li>Total Alokasi Pembelajaran Setahun = <strong>{{ ($totEfk1 + $totEfk2) * ($perangkat->jp_per_minggu ?: 5) }} JP</strong></li>
        </ul>
    </div>

    {{-- TANDA TANGAN --}}
    <table class="tanda-tangan" style="margin-top: 30px;">
        <tr>
            <td style="width: 50%;">
                Mengetahui,<br>
                Kepala Madrasah / Sekolah
                <div class="nama-ttd">{{ $perangkat->kepala_madrasah ?? config('sekolah.kepala_madrasah', 'Kepala Madrasah') }}</div>
                <div>NIP. {{ $perangkat->nip_kepala ?? config('sekolah.nip_kepala_madrasah', '-') }}</div>
            </td>
            <td style="width: 50%;">
                {{ $perangkat->kota_titimangsa ?? 'Cisurupan' }}, {{ $perangkat->tanggal_titimangsa ? $perangkat->tanggal_titimangsa->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}<br>
                Guru Mata Pelajaran
                <div class="nama-ttd">{{ $perangkat->guru_pengampu ?? $perangkat->gtk?->nama ?? 'Guru Pengampu' }}</div>
                <div>NIP. {{ $perangkat->nip_guru ?? $perangkat->gtk?->nip ?? '-' }}</div>
            </td>
        </tr>
    </table>
</div>
