<?php

namespace App\Services\Harian;

use App\Enums\Semester;
use App\Filament\Guru\Pages\InputNilai;
use App\Filament\Guru\Pages\JurnalMengajar as HalamanJurnal;
use App\Filament\Guru\Pages\KehadiranGtk;
use App\Filament\Guru\Pages\PerangkatAjar as HalamanPerangkatAjar;
use App\Filament\Guru\Pages\PresensiKelas;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Models\AlurTujuanPembelajaran;
use App\Models\Kelas;
use App\Models\NilaiRapor;
use App\Models\PembagianMengajar;
use App\Models\PerangkatAjar;
use App\Models\PresensiGtk;
use App\Models\PresensiSiswa;
use App\Models\ProgramSemester;
use App\Models\ProgramTahunan;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;

class RingkasanTugasGuru
{
    /**
     * @return array{
     *     total: int,
     *     selesai: int,
     *     belum: int,
     *     persen: int,
     *     items: list<array{
     *         id: string,
     *         kategori: string,
     *         judul: string,
     *         ringkasan: string,
     *         status: 'selesai'|'sebagian'|'belum',
     *         labelStatus: string,
     *         url: string,
     *         labelAksi: string,
     *         detail: array<int, array{label: string, selesai: bool}>,
     *     }>,
     * }
     */
    public function untuk(User $user): array
    {
        return once(function () use ($user): array {
            $gtk = $user->gtk;
            if ($gtk === null) {
                return ['total' => 0, 'selesai' => 0, 'belum' => 0, 'persen' => 0, 'items' => []];
            }

            $hariIni = AksesHarian::hariIni();
            $tahunAjaran = TahunAjaran::query()->where('is_aktif', true)->first();
            $semester = Semester::berdasarkanTanggal(now());
            $items = [];

            // 1. Kehadiran GTK hari ini
            $presensiGtk = PresensiGtk::query()
                ->where('gtk_id', $gtk->id)
                ->where('tanggal', $hariIni)
                ->value('status');
            $hadirTercatat = $presensiGtk !== null;
            $items[] = [
                'id' => 'kehadiran-gtk',
                'kategori' => 'Administrasi Harian',
                'judul' => 'Kehadiran GTK Hari Ini',
                'ringkasan' => $hadirTercatat
                    ? 'Tercatat: '.(PresensiGtk::STATUS[$presensiGtk] ?? ucfirst((string) $presensiGtk))
                    : 'Belum mencatat kehadiran hari ini.',
                'status' => $hadirTercatat ? 'selesai' : 'belum',
                'labelStatus' => $hadirTercatat ? 'Selesai' : 'Perlu Diisi',
                'url' => KehadiranGtk::getUrl(),
                'labelAksi' => $hadirTercatat ? 'Lihat' : 'Catat Hadir',
                'detail' => [],
            ];

            // 2. Jurnal Mengajar hari ini
            $pertemuan = app(PertemuanMengajar::class)->daftar($user, $hariIni, false);
            $totalJurnal = $pertemuan->count();
            $jurnalLengkap = $pertemuan->filter(fn (array $p): bool => ($p['jurnal_id'] ?? null) !== null && filled($p['materi'] ?? null))->count();
            $statusJurnal = match (true) {
                $totalJurnal === 0 => 'selesai',
                $jurnalLengkap >= $totalJurnal => 'selesai',
                $jurnalLengkap > 0 => 'sebagian',
                default => 'belum',
            };
            $items[] = [
                'id' => 'jurnal-mengajar',
                'kategori' => 'Administrasi Harian',
                'judul' => 'Jurnal Mengajar Hari Ini',
                'ringkasan' => $totalJurnal === 0
                    ? 'Tidak ada jadwal mengajar pada hari ini.'
                    : "{$jurnalLengkap} dari {$totalJurnal} sesi mengajar sudah dilengkapi.",
                'status' => $statusJurnal,
                'labelStatus' => $totalJurnal === 0 ? 'Tidak Ada Jadwal' : ($statusJurnal === 'selesai' ? 'Selesai' : "{$jurnalLengkap}/{$totalJurnal}"),
                'url' => HalamanJurnal::getUrl(),
                'labelAksi' => $statusJurnal === 'selesai' ? 'Lihat' : 'Isi Jurnal',
                'detail' => $pertemuan->map(fn (array $p): array => [
                    'label' => ($p['kelas'] ?? '-').' — '.($p['mapel'] ?? '-').' ('.($p['jam'] ?? '-').')',
                    'selesai' => ($p['jurnal_id'] ?? null) !== null && filled($p['materi'] ?? null),
                ])->values()->all(),
            ];

            // 3. Presensi Kelas hari ini (Wali Kelas atau Petugas Piket)
            $isPiket = AksesHarian::piket($user, $hariIni);
            $kelasWali = $gtk->kelasPerwalian()->aktif()->orderBy('tingkat')->orderBy('nama')->get();
            if ($isPiket || $kelasWali->isNotEmpty()) {
                $daftarKelas = $isPiket
                    ? Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->get()
                    : $kelasWali;
                $kelasTerisi = $daftarKelas->filter(fn (Kelas $k): bool => PresensiSiswa::query()
                    ->where('tanggal', $hariIni)
                    ->whereHas('siswa', fn ($q) => $q->where('kelas_id', $k->id))
                    ->exists())->count();
                $totalKelas = $daftarKelas->count();
                $statusPresensi = match (true) {
                    $totalKelas === 0 => 'selesai',
                    $kelasTerisi >= $totalKelas => 'selesai',
                    $kelasTerisi > 0 => 'sebagian',
                    default => 'belum',
                };
                $items[] = [
                    'id' => 'presensi-kelas',
                    'kategori' => 'Administrasi Harian',
                    'judul' => $isPiket ? 'Presensi Kelas (Piket Hari Ini)' : 'Presensi Kelas Perwalian',
                    'ringkasan' => "{$kelasTerisi} dari {$totalKelas} kelas sudah dicatat presensinya hari ini.",
                    'status' => $statusPresensi,
                    'labelStatus' => $statusPresensi === 'selesai' ? 'Selesai' : "{$kelasTerisi}/{$totalKelas} Kelas",
                    'url' => PresensiKelas::getUrl(),
                    'labelAksi' => $statusPresensi === 'selesai' ? 'Lihat' : 'Isi Presensi',
                    'detail' => $daftarKelas->map(fn (Kelas $k): array => [
                        'label' => 'Kelas '.$k->nama,
                        'selesai' => PresensiSiswa::query()
                            ->where('tanggal', $hariIni)
                            ->whereHas('siswa', fn ($q) => $q->where('kelas_id', $k->id))
                            ->exists(),
                    ])->values()->all(),
                ];
            }

            // 4. Perangkat Pembelajaran & Penilaian (Tahun Ajaran Aktif)
            if ($tahunAjaran !== null) {
                $tugasMengajar = PembagianMengajar::query()
                    ->with(['kelas', 'mataPelajaran'])
                    ->milik($gtk, $tahunAjaran->id)
                    ->get();

                $detailPerangkat = $tugasMengajar->map(function (PembagianMengajar $t) use ($gtk, $tahunAjaran): array {
                    $punyaPerangkat = PerangkatAjar::query()
                        ->where('tahun_ajaran_id', $tahunAjaran->id)
                        ->where('gtk_id', $gtk->id)
                        ->where('kelas_id', $t->kelas_id)
                        ->where('mata_pelajaran_id', $t->mata_pelajaran_id)
                        ->exists();
                    $punyaProta = ProgramTahunan::query()
                        ->where('tahun_ajaran_id', $tahunAjaran->id)
                        ->where('gtk_id', $gtk->id)
                        ->where('kelas_id', $t->kelas_id)
                        ->where('mata_pelajaran_id', $t->mata_pelajaran_id)
                        ->exists();
                    $punyaProsem = ProgramSemester::query()
                        ->where('tahun_ajaran_id', $tahunAjaran->id)
                        ->where('gtk_id', $gtk->id)
                        ->where('kelas_id', $t->kelas_id)
                        ->where('mata_pelajaran_id', $t->mata_pelajaran_id)
                        ->exists();
                    $punyaAtp = AlurTujuanPembelajaran::query()
                        ->where('tahun_ajaran_id', $tahunAjaran->id)
                        ->where('gtk_id', $gtk->id)
                        ->where('kelas_id', $t->kelas_id)
                        ->where('mata_pelajaran_id', $t->mata_pelajaran_id)
                        ->exists();

                    return [
                        'label' => $t->mataPelajaran->nama.' · Kelas '.$t->kelas->nama,
                        'selesai' => $punyaPerangkat || ($punyaProta && $punyaProsem && $punyaAtp),
                    ];
                })->values()->all();

                $totalTugas = count($detailPerangkat);
                $perangkatSelesai = collect($detailPerangkat)->where('selesai', true)->count();
                $statusPerangkat = match (true) {
                    $totalTugas === 0 => 'selesai',
                    $perangkatSelesai >= $totalTugas => 'selesai',
                    $perangkatSelesai > 0 => 'sebagian',
                    default => 'belum',
                };

                $items[] = [
                    'id' => 'perangkat-ajar',
                    'kategori' => 'Perangkat Pembelajaran',
                    'judul' => 'Perangkat Ajar ('.$tahunAjaran->nama.')',
                    'ringkasan' => $totalTugas === 0
                        ? 'Belum ada pembagian mengajar pada tahun ajaran ini.'
                        : "{$perangkatSelesai} dari {$totalTugas} mapel/kelas sudah memiliki perangkat ajar.",
                    'status' => $statusPerangkat,
                    'labelStatus' => $totalTugas === 0 ? 'Kosong' : ($statusPerangkat === 'selesai' ? 'Lengkap' : "{$perangkatSelesai}/{$totalTugas}"),
                    'url' => HalamanPerangkatAjar::getUrl(),
                    'labelAksi' => $statusPerangkat === 'selesai' ? 'Buka' : 'Susun Perangkat',
                    'detail' => $detailPerangkat,
                ];

                $detailNilai = $tugasMengajar->map(function (PembagianMengajar $t) use ($tahunAjaran, $semester): array {
                    $jumlahSiswa = Siswa::query()->anggotaKelas($t->kelas_id, $tahunAjaran->id)->count();
                    $dinilai = NilaiRapor::query()
                        ->where('mata_pelajaran_id', $t->mata_pelajaran_id)
                        ->whereHas('rapor', fn ($q) => $q
                            ->where('tahun_ajaran_id', $tahunAjaran->id)
                            ->where('semester', $semester)
                            ->where('kelas_id', $t->kelas_id))
                        ->count();

                    return [
                        'label' => $t->mataPelajaran->nama.' · Kelas '.$t->kelas->nama." ({$dinilai}/{$jumlahSiswa} siswa)",
                        'selesai' => $jumlahSiswa > 0 && $dinilai >= $jumlahSiswa,
                    ];
                })->values()->all();

                $nilaiSelesai = collect($detailNilai)->where('selesai', true)->count();
                $statusNilai = match (true) {
                    $totalTugas === 0 => 'selesai',
                    $nilaiSelesai >= $totalTugas => 'selesai',
                    $nilaiSelesai > 0 => 'sebagian',
                    default => 'belum',
                };

                $items[] = [
                    'id' => 'input-nilai',
                    'kategori' => 'Penilaian '.$semester->getLabel(),
                    'judul' => 'Input Nilai Mata Pelajaran',
                    'ringkasan' => $totalTugas === 0
                        ? 'Belum ada kelas yang diajar pada tahun ajaran ini.'
                        : "{$nilaiSelesai} dari {$totalTugas} mapel/kelas sudah terisi penuh.",
                    'status' => $statusNilai,
                    'labelStatus' => $totalTugas === 0 ? 'Kosong' : ($statusNilai === 'selesai' ? 'Lengkap' : "{$nilaiSelesai}/{$totalTugas}"),
                    'url' => InputNilai::getUrl(),
                    'labelAksi' => $statusNilai === 'selesai' ? 'Lihat' : 'Input Nilai',
                    'detail' => $detailNilai,
                ];

                if ($kelasWali->isNotEmpty()) {
                    $detailWali = $kelasWali->map(function (Kelas $k) use ($tahunAjaran, $semester): array {
                        $anggota = Siswa::query()->anggotaKelas($k->id, $tahunAjaran->id)->pluck('id');
                        $bercatatan = Rapor::query()
                            ->whereIn('siswa_id', $anggota)
                            ->where('tahun_ajaran_id', $tahunAjaran->id)
                            ->where('semester', $semester)
                            ->whereNotNull('catatan')
                            ->count();

                        return [
                            'label' => 'Kelas '.$k->nama." ({$bercatatan}/{$anggota->count()} siswa)",
                            'selesai' => $anggota->isNotEmpty() && $bercatatan >= $anggota->count(),
                        ];
                    })->values()->all();

                    $waliSelesai = collect($detailWali)->where('selesai', true)->count();
                    $statusWali = match (true) {
                        $waliSelesai >= $kelasWali->count() => 'selesai',
                        $waliSelesai > 0 => 'sebagian',
                        default => 'belum',
                    };

                    $items[] = [
                        'id' => 'rapor-wali',
                        'kategori' => 'Tugas Wali Kelas',
                        'judul' => 'Catatan & Kelengkapan Rapor Kelas',
                        'ringkasan' => "{$waliSelesai} dari {$kelasWali->count()} kelas perwalian sudah lengkap catatannya.",
                        'status' => $statusWali,
                        'labelStatus' => $statusWali === 'selesai' ? 'Lengkap' : "{$waliSelesai}/{$kelasWali->count()} Kelas",
                        'url' => RaporKelasSaya::getUrl(),
                        'labelAksi' => $statusWali === 'selesai' ? 'Lihat' : 'Isi Rapor',
                        'detail' => $detailWali,
                    ];
                }
            }

            $total = count($items);
            $selesai = collect($items)->where('status', 'selesai')->count();
            $belum = $total - $selesai;

            return [
                'total' => $total,
                'selesai' => $selesai,
                'belum' => $belum,
                'persen' => (int) round(($selesai / $total) * 100),
                'items' => $items,
            ];
        });
    }
}
