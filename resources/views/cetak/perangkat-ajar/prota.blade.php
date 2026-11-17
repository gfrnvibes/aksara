<div class="halaman-dokumen halaman-prota">
    <div class="header-kop">
        <h2>PROGRAM TAHUNAN (PROTA)</h2>
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
    </table>

    @php
        $masterTp = $perangkat->master_tp ?? [];
        $totalSetahun = 0;
    @endphp

    @foreach ([1 => 'SEMESTER 1 (GANJIL)', 2 => 'SEMESTER 2 (GENAP)'] as $semNum => $semJudul)
        @php
            $tpSem = collect($masterTp)->filter(fn($t) => (int)($t['semester'] ?? 1) === $semNum);
            $totSem = $tpSem->sum('alokasi_jp');
            $totalSetahun += $totSem;
        @endphp
        <h3 class="section-title" style="margin-top: {{ $loop->first ? '10px' : '18px' }};">{{ $semJudul }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">No</th>
                    <th style="width: 55px;" class="text-center">Kode TP</th>
                    <th style="width: 140px;">Elemen CP</th>
                    <th style="width: 180px;">Materi Pokok / Lingkup Materi</th>
                    <th>Tujuan Pembelajaran (TP)</th>
                    <th style="width: 65px;" class="text-center font-bold">Alokasi Waktu</th>
                    <th style="width: 100px;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tpSem as $idx => $tp)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center font-bold" style="color: #047857;">{{ $tp['kode_tp'] ?? '-' }}</td>
                        <td><strong>{{ $tp['elemen_cp'] ?? '-' }}</strong></td>
                        <td class="font-bold">{{ $tp['materi_pokok'] ?? '-' }}</td>
                        <td style="text-align: justify; line-height: 1.3;">{{ $tp['tujuan_pembelajaran'] ?? '-' }}</td>
                        <td class="text-center font-bold" style="white-space: nowrap;">{{ $tp['alokasi_jp'] ?? 0 }} JP</td>
                        <td style="font-size: 8pt;">{{ $tp['jenis_asesmen'] ?? 'LM' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 10px;">Belum ada rincian TP untuk semester ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($tpSem->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">JUMLAH ALOKASI JP {{ $semJudul }} :</th>
                        <th class="text-center font-bold">{{ $totSem }} JP</th>
                        <th></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endforeach

    <div class="ringkasan-box" style="margin-top: 15px; border: 1px solid #111; padding: 6px 12px; font-size: 10pt; font-weight: bold; text-align: right;">
        TOTAL ALOKASI WAKTU PEMBELAJARAN (1 TAHUN) : {{ $totalSetahun }} JP
    </div>

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
