<?php

namespace App\Services\Akreditasi;

class KatalogEdm2026
{
    /** @var array<string, string> */
    public const array ASPEK = [
        'A' => 'A. Kedisiplinan Warga Madrasah',
        'B' => 'B. Pengembangan Diri Guru & Tendik',
        'C' => 'C. Proses Pembelajaran',
        'D' => 'D. Sarana & Prasarana',
        'E' => 'E. Pembiayaan',
    ];

    /**
     * 26 indikator resmi Data Indikator EDM 2026 (A1–E3).
     *
     * @return list<array{kode: string, aspek: string, pertanyaan: string, bukti: list<string>, tingkat: array<int, string>, sinkron_eksisting: bool}>
     */
    public static function semua(): array
    {
        return [
            // ASPEK A — Kedisiplinan Warga Madrasah
            [
                'kode' => 'A1',
                'aspek' => 'A',
                'pertanyaan' => 'Siswa menunjukkan perilaku religius dalam aktifitas sehari-hari di madrasah',
                'bukti' => [
                    'Rekapitulasi daftar kehadiran peserta didik dalam aktifitas sholat berjamaah, tadarus bersama, infaq mingguan, dan ibadah rutin lainnya',
                    'Jurnal guru pendidikan agama/PPKn/BK',
                    'Rekapitulasi daftar pelanggaran tata tertib',
                ],
                'tingkat' => [
                    1 => 'Selama 2 semester terakhir, kurang dari 70% siswa mengikuti kegiatan sholat berjamaah, tadarus bersama, infaq mingguan, dan ibadah rutin lainnya.',
                    2 => 'Selama 2 semester terakhir, 70%–<90% siswa mengikuti kegiatan sholat berjamaah, tadarus bersama, infaq mingguan, dan ibadah rutin lainnya.',
                    3 => 'Selama 2 semester terakhir, 90%–100% siswa mengikuti ibadah rutin, namun belum ada keterangan alasan ketidakikutsertaan.',
                    4 => 'Selama 2 semester terakhir, 90%–100% siswa mengikuti ibadah rutin dan ada keterangan alasan ketidakikutsertaan.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'A2',
                'aspek' => 'A',
                'pertanyaan' => 'Guru hadir di madrasah melakukan fungsi pembelajaran, pembimbingan, dan pelatihan sesuai jadwal dan waktu yang ditetapkan',
                'bukti' => [
                    'Rekapitulasi daftar kehadiran guru untuk kegiatan pembelajaran, pembimbingan, dan pelatihan sesuai jadwal',
                    'Penjelasan pokok materi pada jurnal kelas/mengajar',
                ],
                'tingkat' => [
                    1 => 'Selama 2 semester terakhir, kurang dari 80% guru hadir melaksanakan fungsi pembelajaran sesuai jadwal.',
                    2 => 'Selama 2 semester terakhir, 80%–<95% guru hadir melaksanakan fungsi pembelajaran sesuai jadwal.',
                    3 => 'Selama 2 semester terakhir, 95%–100% guru hadir, namun guru yang berhalangan tidak digantikan guru lain.',
                    4 => 'Selama 2 semester terakhir, 95%–100% guru hadir; bila ada yang berhalangan ada alasan jelas & digantikan guru lainnya.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'A3',
                'aspek' => 'A',
                'pertanyaan' => 'Kepala madrasah atau guru senior yang ditugasi dengan SK melakukan supervisi proses pembelajaran terhadap seluruh guru',
                'bukti' => [
                    'Program Supervisi dilengkapi jadwal dan instrumen supervisi',
                    'Laporan hasil supervisi kepala madrasah/guru senior, resume rapat & tindak lanjut',
                    'SK Tim Supervisi',
                    'Laporan hasil wawancara TPM dengan para guru',
                ],
                'tingkat' => [
                    1 => 'Selama 2 semester terakhir, supervisi & tindak lanjut dilaksanakan terhadap kurang dari 80% guru.',
                    2 => 'Selama 2 semester terakhir, supervisi & tindak lanjut dilaksanakan terhadap 80%–<90% guru.',
                    3 => 'Selama 2 semester terakhir, supervisi & tindak lanjut dilaksanakan terhadap 90%–<100% guru.',
                    4 => 'Selama 2 semester terakhir, supervisi & tindak lanjut dilaksanakan kepada seluruh (100%) guru.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'A4',
                'aspek' => 'A',
                'pertanyaan' => 'Siswa menunjukkan budaya berkomunikasi efektif, berpikir kritis, kreatif, inovatif, dan kolaboratif dalam aktifitas sehari-hari',
                'bukti' => [
                    'Laporan prestasi siswa dari guru kelas/wali kelas',
                    'Bukti prestasi siswa dalam lomba tingkat sekolah hingga internasional (piala, piagam, foto)',
                    'Bukti kegiatan ekstrakurikuler (daftar hadir, foto/video kegiatan, sertifikat)',
                    'MoU dengan pihak eksternal',
                ],
                'tingkat' => [
                    1 => 'Selama 2 semester terakhir, kurang dari 70% siswa menunjukkan budaya komunikasi efektif, berpikir kritis, kreatif, dan kolaboratif.',
                    2 => 'Selama 2 semester terakhir, 70%–<80% siswa menunjukkan budaya komunikasi efektif, berpikir kritis, kreatif, dan kolaboratif.',
                    3 => 'Selama 2 semester terakhir, 80%–<90% siswa menunjukkan budaya komunikasi efektif, berpikir kritis, kreatif, dan kolaboratif.',
                    4 => '90%–100% siswa menunjukkan budaya tersebut serta terdapat minimal 1 prestasi internasional dan/atau 3 prestasi tingkat nasional.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'A5',
                'aspek' => 'A',
                'pertanyaan' => 'Siswa aktif membaca/meminjam buku yang tersedia di perpustakaan (termasuk perpustakaan digital/ruang baca/pojok baca)',
                'bukti' => [
                    'Rekapitulasi daftar kunjungan perpustakaan termasuk perpustakaan digital',
                    'Rekapitulasi daftar peminjaman buku perpustakaan',
                ],
                'tingkat' => [
                    1 => 'Dalam 2 semester terakhir kurang dari 70% siswa membaca/meminjam buku perpustakaan, atau madrasah tidak memiliki perpustakaan.',
                    2 => 'Dalam 2 semester terakhir, 70%–<80% siswa membaca/meminjam buku yang tersedia di perpustakaan.',
                    3 => 'Dalam 2 semester terakhir, 80%–<90% siswa membaca/meminjam buku yang tersedia di perpustakaan.',
                    4 => 'Dalam 2 semester terakhir, 90%–100% siswa membaca/meminjam buku yang tersedia di perpustakaan.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'A6',
                'aspek' => 'A',
                'pertanyaan' => 'Madrasah terbiasa melaksanakan evaluasi terhadap kinerja pendidik dan tenaga kependidikan',
                'bukti' => [
                    'Rekapitulasi daftar penugasan guru & tendik beserta latar belakang pendidikannya',
                    'Rekapitulasi daftar beban kerja guru & tendik',
                    'Rekapitulasi daftar keaktifan guru & tendik dalam forum (KKG/MGMP, Seminar/Webinar)',
                    'Rekapitulasi daftar pencapaian prestasi guru (piagam, sertifikat, foto, link jurnal)',
                ],
                'tingkat' => [
                    1 => 'Evaluasi kinerja pendidik dan tenaga kependidikan meliputi kurang dari 3 dari 5 aspek (penugasan, beban kerja, keaktifan, prestasi, kompetisi).',
                    2 => 'Evaluasi kinerja pendidik dan tenaga kependidikan meliputi 3 dari 5 aspek.',
                    3 => 'Evaluasi kinerja pendidik dan tenaga kependidikan meliputi 4 dari 5 aspek.',
                    4 => 'Evaluasi kinerja pendidik dan tenaga kependidikan meliputi seluruh 5 aspek.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'A7',
                'aspek' => 'A',
                'pertanyaan' => 'Madrasah secara rutin melakukan pertemuan dengan guru, tendik, komite madrasah/orang tua, dan yayasan untuk evaluasi diri',
                'bukti' => [
                    'Rekapitulasi daftar hadir rapat guru, tenaga kependidikan, dan komite madrasah',
                    'Notulensi rapat pertemuan dengan guru, tendik, komite/orang tua, dan yayasan',
                ],
                'tingkat' => [
                    1 => 'Dalam 2 semester terakhir, madrasah tidak pernah melakukan pertemuan rutin evaluasi diri.',
                    2 => 'Dalam 2 semester terakhir, madrasah melakukan pertemuan rutin 1x per tahun.',
                    3 => 'Dalam 2 semester terakhir, madrasah melakukan pertemuan rutin 2x per tahun.',
                    4 => 'Dalam 2 semester terakhir, madrasah melakukan pertemuan rutin 3x atau lebih per tahun.',
                ],
                'sinkron_eksisting' => false,
            ],

            // ASPEK B — Pengembangan Diri Guru & Tendik
            [
                'kode' => 'B1',
                'aspek' => 'B',
                'pertanyaan' => 'Kepala Madrasah aktif mengikuti kegiatan pengembangan diri',
                'bukti' => [
                    'Daftar undangan pelatihan/workshop/pertemuan',
                    'Sertifikat keikutsertaan kegiatan lokal/nasional/internasional',
                    'Perjanjian Kerjasama/MoU',
                ],
                'tingkat' => [
                    1 => 'Dalam 2 semester terakhir, kepala madrasah tidak pernah mengikuti pelatihan/workshop/KKM peningkatan kompetensi.',
                    2 => 'Dalam 2 semester terakhir, kepala madrasah mengikuti kegiatan pengembangan diri 1x per tahun.',
                    3 => 'Dalam 2 semester terakhir, kepala madrasah mengikuti kegiatan pengembangan diri 2x per tahun.',
                    4 => 'Dalam 2 semester terakhir, kepala madrasah mengikuti kegiatan pengembangan diri minimal 3x per tahun.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'B2',
                'aspek' => 'B',
                'pertanyaan' => 'Guru aktif mengikuti KKG/MGMP atau kegiatan sejenis pelatihan/workshop dalam rangka peningkatan kompetensi',
                'bukti' => [
                    'Rekapitulasi daftar absensi kehadiran guru di KKG/MGMP/Seminar/Workshop',
                    'Bukti keaktifan guru mengikuti kegiatan pengembangan diri',
                    'Sertifikat pelatihan/seminar/workshop dan sejenisnya',
                ],
                'tingkat' => [
                    1 => 'Dalam 2 semester terakhir, kurang dari 70% guru aktif mengikuti KKG/MGMP atau pelatihan/workshop.',
                    2 => 'Dalam 2 semester terakhir, 70%–<80% guru aktif mengikuti KKG/MGMP atau pelatihan/workshop.',
                    3 => 'Dalam 2 semester terakhir, 80%–<90% guru aktif mengikuti KKG/MGMP atau pelatihan/workshop.',
                    4 => 'Dalam 2 semester terakhir, 90%–100% guru aktif mengikuti KKG/MGMP atau pelatihan/workshop.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'B3',
                'aspek' => 'B',
                'pertanyaan' => 'Kepala Madrasah membuat perencanaan program peningkatan mutu pembelajaran bagi guru',
                'bukti' => [
                    'Dokumen perencanaan madrasah (RKA K/L atau RKAM)',
                    'Daftar guru yang akan mengikuti program peningkatan mutu pembelajaran',
                ],
                'tingkat' => [
                    1 => 'Dalam satu tahun anggaran, kurang dari 50% guru direncanakan mengikuti program peningkatan mutu.',
                    2 => 'Dalam satu tahun anggaran, 50%–<65% guru direncanakan mengikuti program peningkatan mutu.',
                    3 => 'Dalam satu tahun anggaran, 65%–<80% guru direncanakan mengikuti program peningkatan mutu.',
                    4 => 'Dalam satu tahun anggaran, 80%–100% guru direncanakan mengikuti program peningkatan mutu.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'B4',
                'aspek' => 'B',
                'pertanyaan' => 'Tenaga kependidikan di madrasah aktif mengikuti pelatihan/workshop/bimtek peningkatan kompetensi teknis',
                'bukti' => [
                    'Daftar undangan atau surat dari penyelenggara kegiatan',
                    'Bukti keikutsertaan pelatihan/workshop/bimtek dalam bentuk sertifikat',
                ],
                'tingkat' => [
                    1 => 'Dalam 2 semester terakhir, kurang dari 50% tenaga kependidikan mengikuti pelatihan/workshop/bimtek.',
                    2 => 'Dalam 2 semester terakhir, 50%–<65% tenaga kependidikan mengikuti pelatihan/workshop/bimtek.',
                    3 => 'Dalam 2 semester terakhir, 65%–<80% tenaga kependidikan mengikuti pelatihan/workshop/bimtek.',
                    4 => 'Dalam 2 semester terakhir, 80%–100% tenaga kependidikan mengikuti pelatihan/workshop/bimtek.',
                ],
                'sinkron_eksisting' => false,
            ],

            // ASPEK C — Proses Pembelajaran
            [
                'kode' => 'C1',
                'aspek' => 'C',
                'pertanyaan' => 'Guru mengembangkan Perangkat Pembelajaran sesuai ketentuan yang berlaku (Prota, Prosem, RPP/ATP, Media, dsb.)',
                'bukti' => [
                    'Rekapitulasi hasil penilaian kepala madrasah',
                    'Perangkat pembelajaran dari guru (Prota, Prosem, ATP, Modul Ajar/RPP)',
                    'Daftar guru yang menyusun Perangkat Pembelajaran',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru telah mengembangkan perangkat pembelajaran sesuai ketentuan yang berlaku.',
                    2 => '70%–<80% guru telah mengembangkan perangkat pembelajaran sesuai ketentuan yang berlaku.',
                    3 => '80%–<90% guru telah mengembangkan perangkat pembelajaran sesuai ketentuan yang berlaku.',
                    4 => '90%–100% guru telah mengembangkan perangkat pembelajaran sesuai ketentuan yang berlaku.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'C2',
                'aspek' => 'C',
                'pertanyaan' => 'Guru menggunakan metode pembelajaran yang sesuai karakteristik siswa dan materi pembelajaran',
                'bukti' => [
                    'Hasil penilaian kepala madrasah',
                    'Laporan hasil wawancara TPM dengan siswa',
                    'Rekapitulasi daftar guru yang menggunakan ragam metode pembelajaran',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru telah menggunakan metode pembelajaran bervariasi sesuai karakteristik siswa dan materi.',
                    2 => '70%–<80% guru telah menggunakan metode pembelajaran bervariasi sesuai karakteristik siswa dan materi.',
                    3 => '80%–90% guru telah menggunakan metode pembelajaran bervariasi sesuai karakteristik siswa dan materi.',
                    4 => 'Lebih dari 90% guru telah menggunakan metode pembelajaran bervariasi sesuai karakteristik siswa dan materi.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'C3',
                'aspek' => 'C',
                'pertanyaan' => 'Guru menggunakan media pembelajaran (termasuk media berbasis TIK) yang sesuai karakteristik siswa dan materi',
                'bukti' => [
                    'Hasil penilaian kepala madrasah',
                    'Laporan hasil wawancara TPM dengan guru dan siswa',
                    'Rekapitulasi daftar guru yang menggunakan media pembelajaran sesuai karakteristik siswa dan materi',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru menggunakan media pembelajaran yang sesuai karakteristik siswa dan materi.',
                    2 => '70%–<80% guru menggunakan media pembelajaran yang sesuai karakteristik siswa dan materi.',
                    3 => '80%–<90% guru menggunakan media pembelajaran yang sesuai karakteristik siswa dan materi.',
                    4 => '90%–100% guru menggunakan media pembelajaran yang sesuai karakteristik siswa dan materi.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'C4',
                'aspek' => 'C',
                'pertanyaan' => 'Guru melakukan penilaian otentik dalam proses pembelajaran',
                'bukti' => [
                    'Catatan hasil supervisi kepala madrasah',
                    'Laporan hasil wawancara TPM dengan guru dan siswa',
                    'Daftar guru yang melakukan penilaian otentik dalam proses pembelajaran',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru melakukan penilaian otentik (kesiapan, proses, dan hasil belajar secara utuh).',
                    2 => '70%–<80% guru melakukan penilaian otentik secara utuh.',
                    3 => '80%–<90% guru melakukan penilaian otentik secara utuh.',
                    4 => '90%–100% guru melakukan penilaian otentik secara utuh.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'C5',
                'aspek' => 'C',
                'pertanyaan' => 'Guru melakukan penilaian terhadap siswa (PH, PTS, PAS, pengamatan, dan penugasan)',
                'bukti' => [
                    'Daftar nilai PH, PTS, dan PAS',
                    'Daftar catatan guru atas penilaian individu siswa',
                    'Rekapitulasi hasil penilaian kepala madrasah terhadap guru yang melakukan penilaian',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru melaksanakan penilaian hasil belajar (PH/PTS/PAS, pengamatan, penugasan) kepada seluruh siswa.',
                    2 => '70%–<80% guru melaksanakan penilaian hasil belajar lengkap kepada seluruh siswa.',
                    3 => '80%–<90% guru melaksanakan penilaian hasil belajar lengkap kepada seluruh siswa.',
                    4 => '90%–100% guru melaksanakan penilaian hasil belajar lengkap kepada seluruh siswa.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'C6',
                'aspek' => 'C',
                'pertanyaan' => 'Guru memanfaatkan hasil penilaian untuk perencanaan program remedial, pengayaan, dan perbaikan proses pembelajaran',
                'bukti' => [
                    'Dokumen perencanaan program remedial, pengayaan, pelayanan konseling, dan perbaikan pembelajaran dari setiap guru',
                    'Laporan hasil wawancara TPM dengan guru dan siswa',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% guru memanfaatkan hasil penilaian untuk merencanakan remedial, pengayaan, konseling, dan perbaikan pembelajaran.',
                    2 => '70%–<80% guru memanfaatkan hasil penilaian untuk merencanakan remedial, pengayaan, konseling, dan perbaikan pembelajaran.',
                    3 => '80%–<90% guru memanfaatkan hasil penilaian untuk merencanakan remedial, pengayaan, konseling, dan perbaikan pembelajaran.',
                    4 => '90%–100% guru memanfaatkan hasil penilaian untuk merencanakan remedial, pengayaan, konseling, dan perbaikan pembelajaran.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'C7',
                'aspek' => 'C',
                'pertanyaan' => 'Madrasah menyelenggarakan kegiatan remedial dan/atau pengayaan secara rutin',
                'bukti' => [
                    'Jadwal kegiatan remedial dan/atau pengayaan dalam 2 semester terakhir',
                    'Daftar peserta remedial dan kehadirannya',
                    'Daftar peserta pengayaan dan kehadirannya',
                ],
                'tingkat' => [
                    1 => 'Selama 2 semester terakhir, madrasah tidak menyelenggarakan kegiatan remedial dan/atau pengayaan sesuai jadwal.',
                    2 => 'Madrasah menyelenggarakan rutin sesuai jadwal, namun kurang dari 80% siswa mengikuti sesuai jadwal.',
                    3 => 'Madrasah menyelenggarakan rutin sesuai jadwal dan 80%–<90% siswa mengikuti sesuai jadwal.',
                    4 => 'Madrasah menyelenggarakan rutin sesuai jadwal dan 95%–100% siswa mengikuti sesuai jadwal.',
                ],
                'sinkron_eksisting' => false,
            ],

            // ASPEK D — Sarana & Prasarana
            [
                'kode' => 'D1',
                'aspek' => 'D',
                'pertanyaan' => 'Madrasah menyediakan Buku teks dan bacaan, baik cetak maupun digital',
                'bukti' => [
                    'Daftar buku teks dalam bentuk cetakan dan/atau digital di madrasah',
                    'Daftar buku bacaan dalam bentuk cetakan dan/atau digital di madrasah',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 80% buku teks dan bacaan mata pelajaran dalam bentuk cetakan dan/atau digital tersedia di madrasah.',
                    2 => '80%–<90% buku teks dan bacaan mata pelajaran tersedia di madrasah.',
                    3 => '90%–<100% buku teks dan bacaan mata pelajaran tersedia di madrasah.',
                    4 => 'Semua (100%) buku teks dan bacaan mata pelajaran tersedia di madrasah.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'D2',
                'aspek' => 'D',
                'pertanyaan' => 'Madrasah menyediakan Media/alat peraga/alat bantu proses pembelajaran dalam bentuk non-digital',
                'bukti' => [
                    'Daftar media/alat peraga/alat bantu proses pembelajaran non-digital yang tersedia di madrasah',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% media/alat peraga pembelajaran non-digital tersedia di madrasah.',
                    2 => '70%–<80% media/alat peraga pembelajaran non-digital tersedia di madrasah.',
                    3 => '80%–<90% media/alat peraga pembelajaran non-digital tersedia di madrasah.',
                    4 => '90%–100% media/alat peraga pembelajaran non-digital tersedia di madrasah.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'D3',
                'aspek' => 'D',
                'pertanyaan' => 'Madrasah menyediakan Media/alat peraga/alat bantu proses pembelajaran dalam bentuk digital',
                'bukti' => [
                    'Daftar inventaris media berbasis TIK yang tersedia di madrasah (perangkat lunak, laman web, dll.)',
                    'Tangkapan layar (capture) tampilan perangkat lunak dan laman web',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 70% media/alat bantu proses pembelajaran digital tersedia di madrasah.',
                    2 => '70%–<80% media/alat bantu proses pembelajaran digital tersedia di madrasah.',
                    3 => '80%–<90% media/alat bantu proses pembelajaran digital tersedia di madrasah.',
                    4 => '90%–100% media/alat bantu proses pembelajaran digital tersedia di madrasah.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'D4',
                'aspek' => 'D',
                'pertanyaan' => 'Guru menggunakan buku teks dalam bentuk cetakan dan/atau digital dalam proses pembelajaran',
                'bukti' => [
                    'Daftar buku teks/digital yang digunakan guru',
                    'Laporan hasil wawancara TPM dengan guru',
                    'Rekapitulasi daftar guru yang menggunakan buku teks atau buku digital',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 90% guru menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    2 => '90%–<95% guru menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    3 => '95%–<100% guru menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    4 => '100% guru menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                ],
                'sinkron_eksisting' => true,
            ],
            [
                'kode' => 'D5',
                'aspek' => 'D',
                'pertanyaan' => 'Siswa menggunakan buku teks dalam bentuk cetakan dan/atau digital dalam proses pembelajaran',
                'bukti' => [
                    'Daftar buku teks yang dimiliki siswa',
                    'Laporan hasil wawancara TPM dengan guru dan siswa',
                ],
                'tingkat' => [
                    1 => 'Kurang dari 80% siswa menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    2 => '80%–<90% siswa menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    3 => '90%–<100% siswa menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                    4 => '100% siswa menggunakan buku teks dan/atau buku digital dalam proses pembelajaran.',
                ],
                'sinkron_eksisting' => false,
            ],

            // ASPEK E — Pembiayaan
            [
                'kode' => 'E1',
                'aspek' => 'E',
                'pertanyaan' => 'Madrasah menggunakan aplikasi EDM dan e-RKAM dalam menyusun perencanaan kegiatan dan pengelolaan keuangan',
                'bukti' => [
                    'Daftar hadir pertemuan penyusunan EDM dan RKAM',
                    'Laporan hasil wawancara TPM dengan guru, tendik, komite, dan yayasan',
                    'Tangkapan layar (capture) hasil pengisian aplikasi EDM dan e-RKAM',
                ],
                'tingkat' => [
                    1 => 'Perencanaan dan pengelolaan keuangan belum disusun menggunakan aplikasi EDM dan e-RKAM.',
                    2 => 'Disusun dengan EDM & e-RKAM dan disahkan kepala madrasah, namun tidak melibatkan guru, tendik, komite, dan yayasan.',
                    3 => 'Melibatkan guru dan tendik, tetapi belum melibatkan komite madrasah dan yayasan.',
                    4 => 'Disusun, disahkan kepala madrasah, serta melibatkan guru, tendik, komite madrasah, dan yayasan.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'E2',
                'aspek' => 'E',
                'pertanyaan' => 'Madrasah menyediakan bantuan biaya bagi guru dan tendik untuk mengikuti penguatan kapasitas di luar madrasah',
                'bukti' => [
                    'Laporan penggunaan dana madrasah',
                    'Laporan hasil wawancara TPM dengan guru dan tendik',
                    'Rekapitulasi daftar guru dan tendik yang memperoleh bantuan biaya',
                    'Sertifikat dan laporan guru/tendik yang mengikuti kegiatan di luar madrasah',
                ],
                'tingkat' => [
                    1 => 'Madrasah menyediakan bantuan biaya kepada kurang dari 70% guru dan tendik yang mengikuti penguatan kapasitas di luar madrasah.',
                    2 => 'Madrasah menyediakan bantuan biaya kepada 70%–<80% guru dan tendik.',
                    3 => 'Madrasah menyediakan bantuan biaya kepada 80%–<90% guru dan tendik.',
                    4 => 'Madrasah menyediakan bantuan biaya bagi 90%–100% guru dan tendik.',
                ],
                'sinkron_eksisting' => false,
            ],
            [
                'kode' => 'E3',
                'aspek' => 'E',
                'pertanyaan' => 'Madrasah telah membuat laporan keuangan dan dilaporkan kepada orang tua siswa/masyarakat',
                'bukti' => [
                    'Tangkapan layar (capture) bukti penyampaian laporan keuangan secara elektronik',
                    'Dokumentasi (foto/tanda terima/tanda kirim) penyerahan dokumen fisik',
                ],
                'tingkat' => [
                    1 => 'Selama 1 tahun terakhir madrasah belum membuat laporan keuangan.',
                    2 => 'Telah membuat laporan keuangan, namun tidak menyampaikan kepada orang tua siswa melalui komite madrasah.',
                    3 => 'Telah membuat laporan keuangan dan menyampaikan kepada orang tua siswa melalui komite madrasah.',
                    4 => 'Telah membuat dan menyampaikan laporan keuangan kepada orang tua siswa melalui komite dan masyarakat secara elektronik maupun fisik secara rutin.',
                ],
                'sinkron_eksisting' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function opsiButir(): array
    {
        $opsi = [];
        foreach (self::semua() as $item) {
            $opsi[$item['kode']] = "{$item['kode']} — ".str($item['pertanyaan'])->limit(70);
        }

        return $opsi;
    }

    /**
     * @return array{kode: string, aspek: string, pertanyaan: string, bukti: list<string>, tingkat: array<int, string>, sinkron_eksisting: bool}|null
     */
    public static function cari(string $kode): ?array
    {
        foreach (self::semua() as $item) {
            if ($item['kode'] === $kode) {
                return $item;
            }
        }

        return null;
    }
}
