@php
    $tanggal = fn ($date): string => $date?->translatedFormat('d F Y') ?? '-';
    $tampil = fn ($value): string => filled($value) ? (string) $value : '-';

    $orangTuaRows = fn ($orangTua): array => [
        'Nama' => $tampil($orangTua?->nama),
        'Tempat dan Tanggal Lahir' => $orangTua ? $tampil($orangTua->tempat_lahir).', '.$tanggal($orangTua->tanggal_lahir) : '-',
        'Agama' => $tampil($orangTua?->agama?->getLabel()),
        'Kewarganegaraan' => $tampil($orangTua?->kewarganegaraan),
        'Pendidikan' => $tampil($orangTua?->pendidikan),
        'Pekerjaan' => $tampil($orangTua?->pekerjaan),
        'Penghasilan per Bulan' => $tampil($orangTua?->penghasilan),
        'Alamat Rumah / Nomor Telepon' => $orangTua ? $tampil($orangTua->alamat).' / '.$tampil($orangTua->nomor_telepon) : '-',
    ];

    $sections = [
        'A. Keterangan Tentang Diri Siswa' => [
            'Nama Lengkap' => $siswa->nama_lengkap,
            'Nama Panggilan' => $tampil($siswa->nama_panggilan),
            'Jenis Kelamin' => $siswa->jenis_kelamin->getLabel(),
            'Tempat dan Tanggal Lahir' => $tampil($siswa->tempat_lahir).', '.$tanggal($siswa->tanggal_lahir),
            'Agama' => $tampil($siswa->agama?->getLabel()),
            'Kewarganegaraan' => $tampil($siswa->kewarganegaraan),
            'Anak Keberapa' => $tampil($siswa->anak_ke),
            'Jumlah Saudara Kandung / Tiri / Angkat' => $tampil($siswa->jumlah_saudara_kandung).' / '.$tampil($siswa->jumlah_saudara_tiri).' / '.$tampil($siswa->jumlah_saudara_angkat),
            'Anak Yatim / Piatu / Yatim Piatu' => $tampil($siswa->status_yatim),
            'Bahasa Sehari-hari di Rumah' => $tampil($siswa->bahasa_sehari_hari),
        ],
        'B. Keterangan Tempat Tinggal' => [
            'Alamat' => $tampil($siswa->alamat),
            'Nomor Telepon' => $tampil($siswa->nomor_telepon),
            'Tinggal Dengan' => $tampil($siswa->tinggal_dengan),
            'Jarak Tempat Tinggal ke Sekolah' => $tampil($siswa->jarak_ke_sekolah),
        ],
        'C. Keterangan Kesehatan' => [
            'Golongan Darah' => $tampil($siswa->golongan_darah),
            'Penyakit yang Pernah Diderita' => $tampil($siswa->riwayat_penyakit),
            'Kelainan Jasmani' => $tampil($siswa->kelainan_jasmani),
            'Tinggi dan Berat Badan' => $tampil($siswa->tinggi_badan).' cm / '.$tampil($siswa->berat_badan).' kg',
        ],
        'D. Keterangan Pendidikan' => [
            'Lulusan Dari' => $tampil($siswa->lulusan_dari),
            'Tanggal dan Nomor STTB' => $tanggal($siswa->tanggal_sttb).' / '.$tampil($siswa->nomor_sttb),
            'Lama Belajar' => $tampil($siswa->lama_belajar),
            'Pindahan Dari Sekolah' => $tampil($siswa->pindahan_dari),
            'Alasan Pindah' => $tampil($siswa->alasan_pindah),
            'Diterima di Tingkat' => $tampil($siswa->diterima_di_tingkat),
            'Tanggal Diterima' => $tanggal($siswa->tanggal_diterima),
        ],
        'E. Keterangan Tentang Ayah Kandung' => [
            ...$orangTuaRows($siswa->ayah),
            'Masih Hidup / Meninggal Dunia' => $siswa->ayah ? ($siswa->ayah->masih_hidup ? 'Masih Hidup' : 'Meninggal Dunia') : '-',
        ],
        'F. Keterangan Tentang Ibu Kandung' => [
            ...$orangTuaRows($siswa->ibu),
            'Masih Hidup / Meninggal Dunia' => $siswa->ibu ? ($siswa->ibu->masih_hidup ? 'Masih Hidup' : 'Meninggal Dunia') : '-',
        ],
        'G. Keterangan Tentang Wali' => $orangTuaRows($siswa->wali),
        'H. Kegemaran Siswa' => [
            'Kesenian' => $tampil($siswa->kegemaran_kesenian),
            'Olah Raga' => $tampil($siswa->kegemaran_olahraga),
            'Kemasyarakatan / Organisasi' => $tampil($siswa->kegemaran_organisasi),
            'Lain-lain' => $tampil($siswa->kegemaran_lain),
        ],
        'I. Keterangan Perkembangan Siswa' => [
            'Menerima Bea Siswa' => collect($siswa->beasiswa ?? [])
                ->map(fn (array $beasiswa): string => 'Tahun '.$tampil($beasiswa['tahun'] ?? null).' / Kelas '.$tampil($beasiswa['kelas'] ?? null).' dari '.$tampil($beasiswa['dari'] ?? null))
                ->implode('; ') ?: '-',
            'Tanggal Meninggalkan Sekolah' => $tanggal($siswa->tanggal_meninggalkan_sekolah),
            'Alasan Meninggalkan Sekolah' => $tampil($siswa->alasan_meninggalkan_sekolah),
            'Tamat Belajar' => $tanggal($siswa->tanggal_tamat),
            'STTB Nomor' => $tampil($siswa->nomor_sttb_tamat),
        ],
        'J. Keterangan Setelah Selesai Pendidikan' => [
            'Melanjutkan di' => $tampil($siswa->melanjutkan_di),
            'Tanggal Mulai Bekerja' => $tanggal($siswa->tanggal_mulai_bekerja),
            'Nama Perusahaan / Lembaga' => $tampil($siswa->nama_tempat_bekerja),
            'Penghasilan' => $tampil($siswa->penghasilan_bekerja),
        ],
    ];

    $fotoTersedia = filled($siswa->foto) && Storage::disk('public')->exists($siswa->foto);
    $sumberFoto = $fotoTersedia ? ($untukPdf ? Storage::disk('public')->path($siswa->foto) : Storage::disk('public')->url($siswa->foto)) : null;
    $nomor = 1;
@endphp
<div class="sheet {{ $pageBreak ? 'page-break' : '' }}">
    <h1>LEMBAR BUKU INDUK SISWA</h1>

    <table class="layout">
        <tr>
            <td>
                <table class="data">
                    <tr><td style="width: 40%;">Nomor Induk Siswa</td><td class="sep">:</td><td><strong>{{ $siswa->nis }}</strong></td></tr>
                    <tr><td>Nomor Induk Siswa Nasional</td><td class="sep">:</td><td><strong>{{ $tampil($siswa->nisn) }}</strong></td></tr>
                    <tr><td>Kelas / Status</td><td class="sep">:</td><td>{{ $tampil($siswa->kelas?->nama) }} / {{ $siswa->status->getLabel() }}</td></tr>
                </table>
            </td>
            <td style="width: 32mm;">
                <table><tr><td class="foto">
                    @if ($sumberFoto)
                        <img src="{{ $sumberFoto }}" alt="Foto {{ $siswa->nama_lengkap }}">
                    @else
                        Pas Photo<br>3 x 4
                    @endif
                </td></tr></table>
            </td>
        </tr>
    </table>

    @foreach ($sections as $judul => $rows)
        <h2>{{ $judul }}</h2>
        <table class="data">
            @foreach ($rows as $label => $value)
                <tr>
                    <td class="no">{{ $nomor++ }}.</td>
                    <td class="label">{{ $label }}</td>
                    <td class="sep">:</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <h2>K. Catatan Prestasi</h2>
    <table class="grid">
        <thead><tr><th style="width: 40px;">No</th><th>Prestasi yang Pernah Dicapai</th></tr></thead>
        <tbody>
            @forelse ($siswa->prestasi ?? [] as $prestasi)
                <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $prestasi }}</td></tr>
            @empty
                <tr><td class="center">-</td><td>-</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>L. Lain-lain</h2>
    <p style="margin: 0;">{{ $tampil($siswa->catatan_penting) }}</p>
</div>
