# Administrasi harian guru

## Mengaktifkan

1. Jalankan `php artisan migrate --force` pada server setelah mengambil perubahan kode. Migrasi hanya menambahkan tabel administrasi harian dan kolom penguncian rapor.
2. Jalankan `php artisan shield:generate --all --panel=admin --option=permissions --no-interaction` untuk mendaftarkan izin halaman baru. Berikan izin `View:PenugasanPiket` hanya kepada pengelola yang boleh melihat seluruh presensi dan menugaskan guru piket. Super admin otomatis memiliki akses.
3. Gemini memakai konfigurasi `GEMINI_API_KEY`, `GEMINI_MODEL`, dan timeout yang sudah tersedia. Jurnal manual tetap berfungsi tanpa Gemini. Tidak memerlukan worker atau layanan AI baru.
4. Pastikan tahun ajaran sesuai tanggal tersedia, wali kelas sudah ditentukan, GTK terhubung akun aktif, dan jadwal semester sudah diterbitkan. Admin menugaskan guru piket melalui **Kepegawaian → Penugasan Piket**, per tanggal.

## Alur harian

- **Presensi Kelas:** wali kelas atau guru piket pada tanggal tugas memilih kelas/tanggal, menandai siswa hadir/sakit/izin/alpa, lalu menyimpan. Tombol tandai semua hadir hanya mengisi baris yang belum diisi. Terlambat adalah hadir dengan durasi keterlambatan. Data kosong tidak dianggap alpa. Semua siswa dalam daftar harus diisi saat menyimpan.
- **Kehadiran GTK:** guru menekan “Saya hadir hari ini”. Piket mencatat sakit/izin/dinas luar/alpa dan koreksi pada tanggal tugasnya. Kehadiran harian berbeda dari pelaksanaan mengajar; jurnal pengganti tidak menandai guru asli hadir.
- **Jurnal Mengajar:** pertemuan muncul dari jadwal terbit. JP berurutan dalam satu sesi digabung. Guru dapat menekan “Hadir mengajar”, kemudian melengkapi materi dan kegiatan. Guru piket dapat mencatat guru pelaksana pengganti atau pertemuan yang tidak terlaksana, dengan alasan. Nama guru, kelas, mapel, dan JP disalin agar jurnal tetap terbaca setelah jadwal berubah.
- **Buku Piket:** kejadian guru tidak hadir, kelas kosong, keterlambatan, izin keluar, dan lainnya beserta penanganannya. Catatan keterlambatan memperbarui presensi siswa sebagai hadir. Ketidakhadiran GTK dan pelaksanaan pengganti dicatat pada menu masing-masing, bukan disimpulkan otomatis dari teks kejadian.
- **Rekap & Cetakan:** presensi siswa berbentuk matriks bulanan dengan total H/S/I/A/T; kehadiran GTK, jurnal, dan buku piket dapat dicetak untuk rentang maksimal satu tahun, melalui browser atau PDF A4 landscape. Guru hanya dapat mengekspor kelas perwaliannya dan data GTK/jurnal sendiri. Pengelola dapat mengekspor seluruh GTK. Guru piket melihat buku piket pada tanggal tugasnya dan catatan yang dibuatnya.

## Koreksi dan rapor

Satu siswa dan satu GTK hanya memiliki satu presensi harian. Pengisian kelas memakai pemeriksaan versi untuk menolak formulir yang tertinggal dari koreksi petugas lain. Koreksi serta input tanggal lampau membutuhkan alasan. Tanggal mendatang ditolak; presensi belajar dan jurnal juga menolak Minggu serta agenda kalender berjenis **Libur**.

S/I/A rapor yang belum dikunci mengikuti presensi siswa pada tahun ajaran dan semester yang sama. Rapor baru juga mengambil rekap tersebut. Jika belum ada data harian, isian manual rapor tetap tersedia. **Ketika mulai menggunakan presensi harian di tengah semester, lengkapi presensi tanggal sebelumnya terlebih dahulu:** rekap otomatis menghitung hari yang tercatat dan tidak menambahkan angka manual lama sebagai saldo awal, agar tidak menghitung ketidakhadiran dua kali.

Pada **Rapor Kelas Saya** dan **Edit Rapor** tersedia “Kunci presensi” dan “Buka koreksi presensi”. Kunci membekukan angka rapor; koreksi data harian tetap tersimpan. Pembukaan kunci wajib disertai alasan dan menyinkronkan rekap kembali. Kunci ini hanya membekukan presensi, bukan nilai atau seluruh rapor.

## Gemini dan kinerja

Tombol “Rapikan dengan Gemini” menyusun draf materi, kegiatan, refleksi, dan usulan tindak lanjut dari catatan nyata guru. AI tidak menetapkan kehadiran dan tidak menyimpan jurnal otomatis. Guru harus memeriksa draf lalu menyimpan. Jika gagal, teks asli tetap ada dan jurnal bisa diisi manual.

Jangan memasukkan identitas atau informasi pribadi siswa dalam catatan AI. Sistem menyamarkan nama lengkap/NIS/NISN siswa pada kelas yang dikenali dan deret angka identitas, tetapi penyamaran tidak menjamin seluruh data pribadi terdeteksi. Hanya mapel dan catatan yang telah diproses dikirim ke Gemini; daftar siswa, presensi, dan nilai tidak dikirim.

Permintaan identik disimpan dalam cache satu hari per pengguna; permintaan baru dibatasi 10 per 10 menit. Tidak ada pemanggilan AI otomatis saat membuka dashboard. Widget pintasan tidak melakukan polling. Tabel memakai pagination 25 dan indeks tanggal/kelas/guru; perubahan mengikuti activity log yang sudah tersedia.

Cetakan merupakan bukti administrasi yang diisi sekolah dan menyediakan ruang tanda tangan. Penggunaan format ini tidak dengan sendirinya menjamin pemenuhan instrumen atau hasil akreditasi BAN-PDM.

## Pengujian aman

Tes wajib menggunakan environment `testing`, SQLite `:memory:`, dan tanpa config cache. `tests/TestCase.php` menolak database lain sebelum RefreshDatabase dijalankan.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Guru/AdministrasiHarianTest.php --compact
```
