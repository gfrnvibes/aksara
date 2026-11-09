# Kustomisasi kop surat

Menu **Pengaturan → Kop Surat** berisi template kop. Form tersedia dalam halaman buat/edit, dengan tab Identitas, Logo & Gambar, serta Tata Letak. Tidak diperlukan editor kode atau perubahan `.env`.

## Aktivasi

1. Jalankan `php artisan migrate --force` setelah mengambil perubahan kode. Migrasi menambahkan tabel template/default dan kolom snapshot pada surat keluar. Surat lama disalin menggunakan kop bawaan dari konfigurasi sekolah saat migrasi; sistem tidak dapat memulihkan identitas historis yang sudah berubah sebelum fitur ini dipasang.
2. Jalankan `php artisan shield:generate --all --panel=admin --option=permissions --no-interaction` dan berikan izin `ViewAny:KopSurat`, `View:KopSurat`, `Create:KopSurat`, serta `Update:KopSurat` pada pengelola yang ditunjuk. Super admin mengikuti akses penuh yang sudah tersedia. Pengguna dengan izin Update dapat menerapkan kop default.
3. `php artisan optimize:clear` untuk membersihkan cache konfigurasi/tampilan setelah deploy. Kop kustom memakai CSS cetak di template sehingga tidak memerlukan build frontend khusus.

## Penggunaan

- **Terstruktur:** masukkan yayasan/instansi, nama madrasah, alamat, wilayah, telepon, email, website, NPSN dan NSM. Baris opsional yang kosong disembunyikan. Logo kiri/kanan dapat diunggah, disembunyikan, dan diatur ukurannya.
- **Gambar:** unggah PNG/JPG kop lengkap dan pilih tinggi maksimum. Rasio gambar dipertahankan. Pilih tanpa garis jika gambar sudah memiliki garis pembatas.
- Ukuran font, perataan, jarak antarbaris, garis pembatas, dan jarak setelah kop dapat diubah melalui tab Tata Letak.
- Pratinjau mengikuti perubahan isian. **Pratinjau PDF** tersedia pada halaman buat maupun edit, menggunakan isian saat ini tanpa menyimpan template atau mengubah default. Gambar yang digunakan untuk pratinjau PDF disimpan di disk sebelum dirender.
- **Simpan Draf** menyimpan template saja. Pada halaman edit, **Jadikan Default** menerapkan **draf yang sudah tersimpan** untuk jenis dokumen yang dipilih: surat keluar; surat keterangan/pindah/transkrip; atau rapor. Simpan perubahan terlebih dahulu sebelum menerapkan default.
- Mengubah draf tidak mengubah kop aktif. Penerapan hanya memengaruhi jenis yang dipilih, meskipun sebelumnya beberapa jenis memakai template yang sama. Kolom Default untuk pada daftar menunjukkan jenis dokumen yang memakai template tersebut; versi aktif bisa berbeda dengan draf terbaru.

## Arsip dan file

Surat keluar menyimpan salinan pengaturan kop ketika dibuat. Perubahan isi surat ataupun pergantian kop default tidak mengganti salinan itu. Halaman pratinjau, cetak browser, dan PDF memakai snapshot yang sama. Surat keterangan/transkrip/pindah yang dibuat langsung dari data siswa serta rapor memakai default pada saat dicetak.

Logo/gambar disimpan di disk `public`, dalam direktori `kop-surat`, dengan nama file unik. Gambar dibaca dan disematkan sebagai data URI agar PDF tidak bergantung pada URL publik, storage link, atau koneksi internet. PNG/JPG maksimal 2 MB dapat diunggah tanpa batas rasio maupun dimensi piksel. Tampilan logo mengikuti batas ukuran kiri/kanan, dengan lebar dan tinggi otomatis agar rasio asli tetap terjaga, tanpa pemotongan. File asli dipertahankan; penyesuaian ukuran dilakukan saat ditampilkan di pratinjau, cetak, dan PDF. SVG dan URL gambar eksternal tidak diterima.

File gambar lama sengaja tidak dihapus ketika dilepas dari template, karena masih mungkin digunakan oleh snapshot surat. Jangan menimpa atau menghapus `storage/app/public/kop-surat` secara manual. Template tidak menyediakan aksi hapus. Pencadangan **database + file** mencakup gambar kop pada disk public; backup database saja tidak mencakup gambar.

Perubahan draf dicatat melalui activity log, dan penerapan default dicatat sebagai event `kop_diterapkan` beserta jenis dokumen serta versi yang diterapkan.
