<div class="halaman-dokumen halaman-atp">
    <div class="header-kop">
        <h2>ALUR TUJUAN PEMBELAJARAN (ATP)</h2>
        <h1>{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</h1>
        <p class="instansi">TAHUN PELAJARAN {{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }}</p>
    </div>

    <table class="tabel-identitas">
        <tr>
            <td class="label">Mata Pelajaran</td>
            <td class="separator">:</td>
            <td><strong>{{ $perangkat->mataPelajaran?->nama ?? '-' }}</strong></td>
            <td class="label">Fase</td>
            <td class="separator">:</td>
            <td><strong>Fase {{ $perangkat->fase ?? 'D' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Satuan Pendidikan</td>
            <td class="separator">:</td>
            <td>{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</td>
            <td class="label">Kelas / Tingkat</td>
            <td class="separator">:</td>
            <td>Kelas {{ $perangkat->kelas ?? 'VII' }}</td>
        </tr>
        <tr>
            <td class="label">Alokasi Waktu</td>
            <td class="separator">:</td>
            <td>{{ $perangkat->jp_per_minggu ?? 5 }} JP / Minggu</td>
            <td class="label">Tahun Pelajaran</td>
            <td class="separator">:</td>
            <td>{{ $perangkat->tahun_pelajaran ?? '2024/2025' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th style="width: 45px;" class="text-center">Sem / TP</th>
                <th style="width: 100px;">Elemen CP</th>
                <th style="width: 220px;">Capaian Pembelajaran (CP)</th>
                <th style="width: 130px;">Materi Pokok</th>
                <th>Tujuan Pembelajaran (TP)</th>
                <th style="width: 55px;" class="text-center font-bold">Alokasi JP</th>
                <th style="width: 90px;">Asesmen</th>
            </tr>
        </thead>
        <tbody>
            @php
                $tps = $perangkat->master_tp ?? [];
                $totalJp = 0;
            @endphp
            @forelse ($tps as $index => $item)
                @php $totalJp += (int)($item['alokasi_jp'] ?? 0); @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-bold">
                        <div>S{{ $item['semester'] ?? 1 }}</div>
                        <div style="color: #047857;">{{ $item['kode_tp'] ?? '-' }}</div>
                    </td>
                    <td><strong>{{ $item['elemen_cp'] ?? '-' }}</strong></td>
                    <td style="text-align: justify; line-height: 1.3;">{{ $item['capaian_pembelajaran'] ?? '-' }}</td>
                    <td class="font-bold">{{ $item['materi_pokok'] ?? '-' }}</td>
                    <td style="text-align: justify; line-height: 1.3;">{{ $item['tujuan_pembelajaran'] ?? '-' }}</td>
                    <td class="text-center font-bold" style="white-space: nowrap;">{{ $item['alokasi_jp'] ?? 0 }} JP</td>
                    <td style="font-size: 8pt;">{{ $item['jenis_asesmen'] ?? 'Formatif & Sumatif' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 16px;">Belum ada rincian alur tujuan pembelajaran yang disusun.</td>
                </tr>
            @endforelse
        </tbody>
        @if (!empty($tps))
            <tfoot>
                <tr>
                    <th colspan="6" class="text-right">TOTAL ALOKASI WAKTU PEMBELAJARAN (1 TAHUN) :</th>
                    <th class="text-center font-bold">{{ $totalJp }} JP</th>
                    <th></th>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="tanda-tangan" style="margin-top: 25px;">
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
