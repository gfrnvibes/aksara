<div class="halaman-cover">
    <div class="cover-wrapper">
        <div class="cover-instansi">
            <h3 class="kemenag-title">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h3>
            <h2 class="madrasah-title">{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama', 'MTs At-Taufiq Cisurupan') }}</h2>
            <p class="madrasah-sub">{{ config('sekolah.wilayah', 'Cisurupan, Garut, Jawa Barat') }}</p>
        </div>

        <div class="cover-divider"></div>

        <div class="cover-logo-box">
            @php
                $logoPath = public_path('LOGO ATTAUFIQ Revisi.png');
                $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
            @endphp
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo" class="cover-logo">
            @else
                <div class="cover-logo-placeholder">AKSARAMADRASAH</div>
            @endif
        </div>

        <div class="cover-main-title">
            <h1 class="dokumen-judul">PERANGKAT AJAR</h1>
            <h2 class="kurikulum-judul">KURIKULUM MERDEKA</h2>
            <div class="bundel-badge">PAKET LENGKAP BUNDEL DOKUMEN</div>
        </div>

        <div class="cover-box-identitas">
            <table class="tabel-cover-identitas">
                <tr>
                    <td class="lbl">Mata Pelajaran</td>
                    <td class="sep">:</td>
                    <td class="val font-bold">{{ $perangkat->mataPelajaran?->nama ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Kelas / Tingkat</td>
                    <td class="sep">:</td>
                    <td class="val">Kelas {{ $perangkat->kelas ?? $perangkat->kelas?->nama ?? 'VII' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Fase</td>
                    <td class="sep">:</td>
                    <td class="val font-bold">Fase {{ $perangkat->fase ?? 'D' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Tahun Ajaran</td>
                    <td class="sep">:</td>
                    <td class="val">{{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Alokasi Waktu</td>
                    <td class="sep">:</td>
                    <td class="val">{{ $perangkat->jp_per_minggu ?? 5 }} Jam Pelajaran / Minggu</td>
                </tr>
            </table>
        </div>

        <div class="cover-footer">
            <div class="cover-guru-box">
                <div class="lbl-penyusun">Disusun Oleh Guru Pengampu:</div>
                <div class="nama-guru">{{ $perangkat->guru_pengampu ?? $perangkat->gtk?->nama ?? 'Guru Pengampu' }}</div>
                <div class="nip-guru">NIP. {{ $perangkat->nip_guru ?? $perangkat->gtk?->nip ?? '-' }}</div>
            </div>

            <div class="cover-tahun">
                {{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}<br>
                Tahun Ajaran {{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }}
            </div>
        </div>
    </div>
</div>
