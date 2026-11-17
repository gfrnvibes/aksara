<div class="halaman-dokumen halaman-prosem">
    <div class="header-kop">
        <h2>PROGRAM SEMESTER (PROSEM)</h2>
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
        $bulanSem1List = ['juli' => 'Juli', 'agustus' => 'Agustus', 'september' => 'September', 'oktober' => 'Oktober', 'november' => 'November', 'desember' => 'Desember'];
        $bulanSem2List = ['januari' => 'Januari', 'februari' => 'Februari', 'maret' => 'Maret', 'april' => 'April', 'mei' => 'Mei', 'juni' => 'Juni'];
    @endphp

    @foreach ([1 => ['judul' => 'SEMESTER 1 (GANJIL)', 'bulanMap' => $bulanSem1List], 2 => ['judul' => 'SEMESTER 2 (GENAP)', 'bulanMap' => $bulanSem2List]] as $semNum => $config)
        @php
            $tpSem = collect($masterTp)->filter(fn($t) => (int)($t['semester'] ?? 1) === $semNum);
            $totSem = $tpSem->sum('alokasi_jp');
        @endphp
        <h3 class="section-title" style="margin-top: {{ $loop->first ? '10px' : '20px' }};">{{ $config['judul'] }}</h3>
        <table class="data-table matriks-prosem">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 20px;" class="text-center">No</th>
                    <th rowspan="2" style="width: 45px;" class="text-center">Kode</th>
                    <th rowspan="2">Materi Pokok / Lingkup Materi</th>
                    <th rowspan="2" style="width: 35px;" class="text-center font-bold">JP</th>
                    @foreach ($config['bulanMap'] as $key => $namaBulan)
                        @php
                            $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                            $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                        @endphp
                        <th colspan="{{ $weekCount }}" class="text-center bg-gray font-bold">{{ $namaBulan }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($config['bulanMap'] as $key => $namaBulan)
                        @php
                            $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                            $weekCount = count($sampleWeeks) ?: (($key === 'juli' || $key === 'september' || $key === 'desember' || $key === 'maret' || $key === 'mei') ? 5 : 4);
                        @endphp
                        @for ($w = 1; $w <= $weekCount; $w++)
                            <th class="text-center th-week" style="width: 15px; font-size: 7pt;">{{ $w }}</th>
                        @endfor
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($tpSem as $idx => $tp)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center font-bold" style="color: #047857;">{{ $tp['kode_tp'] ?? '-' }}</td>
                        <td>
                            <strong>{{ $tp['materi_pokok'] ?? '-' }}</strong>
                            <div style="font-size: 7.5pt; color: #444;">{{ Str::limit($tp['tujuan_pembelajaran'] ?? '', 80) }}</div>
                        </td>
                        <td class="text-center font-bold">{{ $tp['alokasi_jp'] ?? 0 }}</td>
                        @foreach ($config['bulanMap'] as $key => $namaBulan)
                            @php
                                $weeks = $tp['jadwal_prosem'][$key] ?? [];
                                $sampleWeeks = $tpSem->first()['jadwal_prosem'][$key] ?? [];
                                $weekCount = count($sampleWeeks) ?: count($weeks) ?: 4;
                            @endphp
                            @for ($w = 0; $w < $weekCount; $w++)
                                @php $jam = (int)($weeks[$w] ?? 0); @endphp
                                <td class="text-center td-week {{ $jam > 0 ? 'bg-filled font-bold' : '' }}">
                                    {{ $jam > 0 ? $jam : '' }}
                                </td>
                            @endfor
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="35" class="text-center" style="padding: 10px;">Belum ada jadwal distribusi PROSEM untuk semester ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($tpSem->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">JUMLAH ALOKASI JP {{ $config['judul'] }} :</th>
                        <th class="text-center font-bold">{{ $totSem }}</th>
                        <th colspan="30" class="text-left font-bold">Total: {{ $totSem }} JP Terdistribusi</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endforeach

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
