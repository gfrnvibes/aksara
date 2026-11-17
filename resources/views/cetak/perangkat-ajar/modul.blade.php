<div class="halaman-dokumen halaman-modul">
    <div class="header-kop">
        <h2>MODUL AJAR KURIKULUM MERDEKA</h2>
        <h1>{{ $modul['identitas']['satuan_pendidikan'] ?? $perangkat->satuan_pendidikan ?? config('sekolah.nama') }}</h1>
        <p class="instansi">TAHUN PELAJARAN {{ $perangkat->tahun_pelajaran ?? '2024/2025' }}</p>
    </div>

    {{-- I. IDENTITAS & INFORMASI UMUM --}}
    <h3 class="modul-section-title">I. INFORMASI UMUM</h3>
    <table class="tabel-modul-info">
        <tr>
            <td style="width: 140px; font-weight: bold;">Satuan Pendidikan</td>
            <td style="width: 8px;">:</td>
            <td style="width: 35%;">{{ $modul['identitas']['satuan_pendidikan'] ?? '-' }}</td>
            <td style="width: 140px; font-weight: bold;">Mata Pelajaran</td>
            <td style="width: 8px;">:</td>
            <td><strong>{{ $modul['identitas']['mata_pelajaran'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Kelas / Fase</td>
            <td>:</td>
            <td>{{ $modul['identitas']['kelas_fase'] ?? '-' }}</td>
            <td style="font-weight: bold;">Semester</td>
            <td>:</td>
            <td>{{ $modul['identitas']['semester_tahun'] ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Kode TP</td>
            <td>:</td>
            <td><strong>{{ $modul['identitas']['kode_tp'] ?? '-' }}</strong></td>
            <td style="font-weight: bold;">Alokasi Waktu</td>
            <td>:</td>
            <td><strong>{{ $modul['identitas']['alokasi_waktu_teks'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Materi Pokok</td>
            <td>:</td>
            <td colspan="4"><strong>{{ $modul['identitas']['materi_pokok'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Penyusun</td>
            <td>:</td>
            <td colspan="4">{{ $modul['identitas']['penyusun'] ?? $perangkat->guru_pengampu ?? '-' }}</td>
        </tr>
    </table>

    <div class="modul-box">
        <strong>A. Kompetensi Awal:</strong>
        <ul style="margin: 4px 0 0 16px;">
            @foreach ((array)($modul['kompetensi_awal'] ?? []) as $ka)
                <li>{{ $ka }}</li>
            @endforeach
        </ul>
    </div>

    <div class="modul-box">
        <strong>B. Profil Pelajar Pancasila & Rahmatan Lil Alamin (P5RA):</strong>
        <ul style="margin: 4px 0 0 16px;">
            @foreach ((array)($modul['profil_p5ra'] ?? []) as $p5)
                <li><strong>{{ $p5['dimensi'] ?? '' }}:</strong> {{ $p5['deskripsi'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>

    <div class="modul-box">
        <strong>C. Sarana dan Prasarana:</strong>
        <table style="width: 100%; margin-top: 4px;">
            <tr><td style="width: 110px;">Media</td><td style="width: 8px;">:</td><td>{{ $modul['sarana_prasarana']['media'] ?? '-' }}</td></tr>
            <tr><td>Alat / Bahan</td><td>:</td><td>{{ $modul['sarana_prasarana']['alat'] ?? '-' }}</td></tr>
            <tr><td>Sumber Belajar</td><td>:</td><td>{{ $modul['sarana_prasarana']['sumber_belajar'] ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="modul-box">
        <strong>D. Model & Metode Pembelajaran:</strong>
        <table style="width: 100%; margin-top: 4px;">
            <tr><td style="width: 110px;">Model</td><td style="width: 8px;">:</td><td>{{ $modul['model_metode']['model'] ?? '-' }}</td></tr>
            <tr><td>Pendekatan</td><td>:</td><td>{{ $modul['model_metode']['pendekatan'] ?? '-' }}</td></tr>
            <tr><td>Metode</td><td>:</td><td>{{ $modul['model_metode']['metode'] ?? '-' }}</td></tr>
        </table>
    </div>

    {{-- II. KOMPONEN INTI --}}
    <h3 class="modul-section-title" style="margin-top: 15px;">II. KOMPONEN INTI</h3>
    
    <div class="modul-box">
        <strong>A. Capaian Pembelajaran:</strong>
        <p style="margin: 4px 0 0 0; text-align: justify;">{{ $modul['capaian_pembelajaran'] ?? '-' }}</p>
    </div>

    <div class="modul-box">
        <strong>B. Tujuan Pembelajaran (TP):</strong>
        <ul style="margin: 4px 0 0 16px;">
            @foreach ((array)($modul['tujuan_pembelajaran'] ?? []) as $tpText)
                <li>{{ $tpText }}</li>
            @endforeach
        </ul>
    </div>

    <div class="modul-box">
        <strong>C. Pemahaman Bermakna:</strong>
        <p style="margin: 4px 0 0 0; text-align: justify;">{{ $modul['pemahaman_bermakna'] ?? '-' }}</p>
    </div>

    <div class="modul-box">
        <strong>D. Pertanyaan Pemantik:</strong>
        <ul style="margin: 4px 0 0 16px;">
            @foreach ((array)($modul['pertanyaan_pemantik'] ?? []) as $pemantik)
                <li>{{ $pemantik }}</li>
            @endforeach
        </ul>
    </div>

    {{-- III. KEGIATAN PEMBELAJARAN --}}
    <h3 class="modul-section-title" style="margin-top: 15px;">III. KEGIATAN PEMBELAJARAN</h3>
    @foreach ((array)($modul['kegiatan_pembelajaran'] ?? []) as $keg)
        <div class="pertemuan-box" style="margin-bottom: 12px; border: 1px solid #ccc; padding: 8px 10px;">
            <div style="font-weight: bold; background: #eee; padding: 4px 6px; margin: -8px -10px 8px -10px; border-bottom: 1px solid #ccc;">
                Pertemuan Ke-{{ $keg['pertemuan_ke'] ?? 1 }} ({{ $keg['jp_pertemuan'] ?? 2 }} JP × 40 menit)
            </div>
            
            <strong>1. Pendahuluan (10 Menit):</strong>
            <ul style="margin: 2px 0 6px 16px;">
                @foreach ((array)($keg['pendahuluan'] ?? []) as $pnd)
                    <li>{{ $pnd }}</li>
                @endforeach
            </ul>

            <strong>2. Kegiatan Inti (60 Menit):</strong>
            <ul style="margin: 2px 0 6px 16px;">
                @foreach ((array)($keg['inti'] ?? []) as $int)
                    <li>{{ $int }}</li>
                @endforeach
            </ul>

            <strong>3. Penutup (10 Menit):</strong>
            <ul style="margin: 2px 0 0 16px;">
                @foreach ((array)($keg['penutup'] ?? []) as $pnt)
                    <li>{{ $pnt }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach

    {{-- IV. ASESMEN & RUBRIK --}}
    <h3 class="modul-section-title" style="margin-top: 15px;">IV. ASESMEN DAN RUBRIK PENILAIAN</h3>
    <table class="data-table" style="font-size: 8pt;">
        <thead>
            <tr>
                <th style="width: 120px;">Aspek Penilaian</th>
                <th>Sangat Baik (4)</th>
                <th>Baik (3)</th>
                <th>Cukup (2)</th>
                <th>Perlu Bimbingan (1)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ((array)($modul['asesmen']['formatif_rubrik'] ?? []) as $rubrik)
                <tr>
                    <td class="font-bold">{{ $rubrik['aspek'] ?? '-' }}</td>
                    <td>{{ $rubrik['sangat_baik'] ?? '-' }}</td>
                    <td>{{ $rubrik['baik'] ?? '-' }}</td>
                    <td>{{ $rubrik['cukup'] ?? '-' }}</td>
                    <td>{{ $rubrik['perlu_bimbingan'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- V. PENGAYAAN, REMEDIAL & REFLEKSI --}}
    <div style="margin-top: 12px;" class="modul-box">
        <strong>V. Pengayaan dan Remedial:</strong>
        <p style="margin: 2px 0;"><strong>Pengayaan:</strong> {{ $modul['pengayaan_remedial']['pengayaan'] ?? '-' }}</p>
        <p style="margin: 2px 0;"><strong>Remedial:</strong> {{ $modul['pengayaan_remedial']['remedial'] ?? '-' }}</p>
    </div>

    {{-- VI. LAMPIRAN LKPD --}}
    @if (!empty($modul['lampiran']['lkpd']))
        <div style="page-break-before: always; margin-top: 20px;"></div>
        <div class="header-kop" style="margin-bottom: 10px;">
            <h2>{{ $modul['lampiran']['lkpd']['judul'] ?? 'LEMBAR KERJA PESERTA DIDIK (LKPD)' }}</h2>
            <p class="instansi">{{ $modul['identitas']['satuan_pendidikan'] ?? config('sekolah.nama') }} • Kelas {{ $perangkat->kelas ?? 'VII' }}</p>
        </div>

        <div style="border: 2px solid #111; padding: 12px; margin-bottom: 15px;">
            <table style="width: 100%; font-size: 9pt; margin-bottom: 8px;">
                <tr><td style="width: 90px; font-weight: bold;">Kelompok</td><td>: ....................................................</td><td style="width: 70px; font-weight: bold;">Hari/Tgl</td><td>: ...................................</td></tr>
                <tr><td style="font-weight: bold;">Nama Anggota</td><td colspan="3">: 1. ........................................ 2. ........................................ 3. ........................................</td></tr>
            </table>

            <div style="border-top: 1px solid #111; padding-top: 8px;">
                <strong>Petunjuk Kerja:</strong>
                <p style="margin: 2px 0 8px 0;">{{ $modul['lampiran']['lkpd']['petunjuk'] ?? '-' }}</p>

                @if (!empty($modul['lampiran']['lkpd']['teks_stimulus']))
                    <div style="background: #f7f7f7; border: 1px solid #ccc; padding: 8px; margin-bottom: 10px;">
                        <strong>Stimulus Masalah / Teks Pengantar:</strong>
                        <p style="margin: 4px 0 0 0; text-align: justify;">{{ $modul['lampiran']['lkpd']['teks_stimulus'] }}</p>
                    </div>
                @endif

                <strong>Pertanyaan & Tugas:</strong>
                <ol style="margin: 4px 0 0 16px;">
                    @foreach ((array)($modul['lampiran']['lkpd']['daftar_tugas'] ?? []) as $tgs)
                        <li style="margin-bottom: 25px;">{{ $tgs }}<br><div style="height: 40px; border-bottom: 1px dotted #888;"></div></li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif

    {{-- TANDA TANGAN PENGESAHAN --}}
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
