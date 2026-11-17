<div class="halaman-pengesahan">
    <div class="kop-surat-dokumen">
        <h2 class="instansi">LEMBAR PENGESAHAN PERANGKAT AJAR</h2>
        <h1 class="nama-madrasah">{{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</h1>
        <p class="alamat">{{ config('sekolah.wilayah', 'Cisurupan, Garut, Jawa Barat') }}</p>
        <div class="garis-kop"></div>
    </div>

    <div class="isi-pengesahan">
        <p style="text-align: justify; line-height: 1.8; margin-top: 24px; font-size: 11pt;">
            Setelah melalui proses penelaahan, verifikasi, dan validasi kurikulum secara terpadu, maka dokumen <strong>Perangkat Ajar Kurikulum Merdeka</strong> yang terdiri dari:
        </p>

        <ol style="margin-left: 24px; line-height: 1.8; font-size: 11pt;">
            <li>Analisis Minggu Efektif (AME) Semester 1 dan Semester 2</li>
            <li>Alur Tujuan Pembelajaran (ATP)</li>
            <li>Program Tahunan (PROTA)</li>
            <li>Program Semester (PROSEM)</li>
            <li>Modul Ajar Pembelajaran Kurikulum Merdeka Terintegrasi</li>
        </ol>

        <table style="width: 100%; margin: 20px 0; font-size: 11pt; line-height: 1.7;">
            <tr>
                <td style="width: 180px; font-weight: bold;">Mata Pelajaran</td>
                <td style="width: 12px;">:</td>
                <td>{{ $perangkat->mataPelajaran?->nama ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Kelas / Tingkat</td>
                <td>:</td>
                <td>Kelas {{ $perangkat->kelas ?? $perangkat->kelas?->nama ?? 'VII' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Fase Kurikulum</td>
                <td>:</td>
                <td>Fase {{ $perangkat->fase ?? 'D' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Tahun Pelajaran</td>
                <td>:</td>
                <td>{{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Penyusun / Guru Pengampu</td>
                <td>:</td>
                <td>{{ $perangkat->guru_pengampu ?? $perangkat->gtk?->nama ?? '-' }} (NIP. {{ $perangkat->nip_guru ?? $perangkat->gtk?->nip ?? '-' }})</td>
            </tr>
        </table>

        <p style="text-align: justify; line-height: 1.8; font-size: 11pt;">
            Dinyatakan <strong>SAH dan BERLAKU</strong> untuk dipergunakan sebagai acuan dan pedoman pelaksanaan kegiatan pembelajaran pada Tahun Pelajaran {{ $perangkat->tahun_pelajaran ?? $perangkat->tahunAjaran?->nama ?? '2024/2025' }} di {{ $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}.
        </p>
    </div>

    <div class="titimangsa-pengesahan" style="margin-top: 40px;">
        <table style="width: 100%; text-align: center; font-size: 11pt;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    Ditetapkan di: {{ $perangkat->kota_titimangsa ?? 'Cisurupan' }}<br>
                    Pada tanggal: {{ $perangkat->tanggal_titimangsa ? $perangkat->tanggal_titimangsa->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}
                </td>
            </tr>
            <tr>
                <td style="padding-top: 20px;">
                    Mengetahui,<br>
                    Kepala Madrasah / Sekolah
                    <div style="height: 70px;"></div>
                    <div style="font-weight: bold; text-decoration: underline;">{{ $perangkat->kepala_madrasah ?? config('sekolah.kepala_madrasah', 'Kepala Madrasah') }}</div>
                    <div>NIP. {{ $perangkat->nip_kepala ?? config('sekolah.nip_kepala_madrasah', '-') }}</div>
                </td>
                <td style="padding-top: 20px;">
                    Guru Mata Pelajaran
                    <div style="height: 70px;"></div>
                    <div style="font-weight: bold; text-decoration: underline;">{{ $perangkat->guru_pengampu ?? $perangkat->gtk?->nama ?? 'Guru Pengampu' }}</div>
                    <div>NIP. {{ $perangkat->nip_guru ?? $perangkat->gtk?->nip ?? '-' }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
