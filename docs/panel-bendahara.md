# Panel bendahara

Panel tersedia di `/bendahara`. Jalankan `php artisan migrate` dan `php artisan db:seed --class=RoleSeeder` saat memasang pembaruan. Migrasi membuat tabel baru tanpa mengubah data akademik.

Administrator menambahkan peran `bendahara` melalui Pengaturan - Pengguna - Peran. Pilih akun guru yang sudah ada untuk guru yang merangkap bendahara; akses Panel Guru tetap mengikuti GTK aktif. Super admin juga dapat membuka panel. Menu pengguna di panel admin dan guru menyediakan tautan ke panel bendahara bagi akun yang berhak.

- Ringkasan & Laporan: saldo awal, pemasukan, pengeluaran, saldo akhir per periode, rekap kategori, piutang seluruh periode, dan ekspor CSV termasuk catatan pembatalan.
- Tagihan Siswa: buat tagihan individu atau massal untuk anggota kelas pada tahun ajaran terpilih. Kombinasi siswa, tahun ajaran, nama, dan periode unik. Gunakan nama/periode yang konsisten. Pembayaran dapat dicicil; jumlah tidak boleh melebihi sisa. Status membedakan belum dibayar, dicicil, menunggak, lunas, dan dibatalkan.
- Buku Kas: pembayaran siswa otomatis menjadi pemasukan, sedangkan pemasukan lainnya dan pengeluaran dicatat manual dengan kategori, metode tunai/transfer, dan referensi bukti. Kuitansi/bukti dapat dicetak atau disimpan sebagai PDF dari browser.
- Koreksi: transaksi tidak diedit/dihapus dari panel. Batalkan dengan alasan lalu buat catatan pengganti. Pembatalan pembayaran membuka kembali sisa tagihan; tagihan hanya dapat dibatalkan setelah semua pembayarannya dibatalkan. Pembatalan bukan pencatatan pengembalian uang; jika uang benar-benar dikembalikan, catat arus kas sesuai kejadian dan hindari menghitungnya dua kali.

Semua nilai memakai rupiah penuh. Saldo mencakup transaksi tunai dan transfer. Catat saldo pembukaan sebagai pemasukan bertanggal sebelum periode laporan pertama. Laporan adalah rekap buku kas, belum mencakup rekonsiliasi bank, anggaran/RKAS, payment gateway, atau portal orang tua. Akses transparansi diberikan kepada bendahara dan super admin; data siswa tidak dibuka ke publik. Aktivitas model dicatat pada log aktivitas admin.

## Penggajian Guru

Halaman `/bendahara/penggajian-guru` mengambil daftar guru aktif dari GTK (jenis Guru), termasuk guru tanpa akun login. Pilih guru dan periode bulan, lalu isi gaji pokok, tunjangan, potongan, serta catatan rinci. Identitas guru disalin pada saat gaji dibuat untuk menjaga konsistensi slip lama. Satu guru hanya memiliki satu catatan gaji per bulan.

Draf belum memengaruhi saldo. Setelah pembayaran benar-benar dilakukan, gunakan Catat Pembayaran untuk membuat pengeluaran sebesar gaji bersih secara otomatis di Buku Kas. Fitur ini mencatat pembayaran dan tidak melakukan transfer bank. Pembayaran ganda ditolak. Gaji yang telah dibayar tidak dapat diubah; batalkan transaksi melalui Buku Kas dengan alasan sebelum mengoreksi atau mencatat ulang. Riwayat pembatalan tetap tersimpan pada slip. Slip belum dibayar diberi tanda DRAF.

Gaji bersih = gaji pokok + tunjangan - potongan, dalam rupiah penuh dan harus positif. Besaran komponen diisi bendahara; sistem belum menghitung tarif mengajar, absensi, pajak, atau BPJS secara otomatis. Hak akses penggajian dan slip hanya untuk bendahara dan super admin.

## Ringkasan Tagihan Siswa

Daftar Tagihan Siswa menampilkan satu baris per nama tagihan untuk semua periode dan tahun ajaran. Jumlah siswa menghitung penerima unik, sedangkan jumlah tagihan menghitung catatan per siswa/periode. Total tagihan, terbayar, dan sisa hanya mencakup tagihan aktif serta pembayaran yang tidak dibatalkan. Jumlah tagihan batal tetap ditampilkan untuk penelusuran. Klik baris atau Lihat Detail untuk membuka daftar siswa penerima; filter tahun ajaran dan status pada halaman detail turut memperbarui ringkasannya. Pembayaran dan pembatalan tetap dilakukan per siswa di halaman detail.

## Tampilan Panel

Seluruh tabel bendahara menggunakan paginasi awal 25 baris, dengan pilihan 10, 25, 50, dan 100. Rekap kategori menggunakan tabel Filament yang dapat dicari dan diurutkan. Kartu ringkasan dan grafik arus kas mengikuti periode terapan; tersedia pilihan Bulan ini, Bulan lalu, dan Tahun ini. Klik kartu pemasukan/pengeluaran untuk membuka Buku Kas dengan jenis dan periode yang sama. Pemasukan memakai hijau, pengeluaran abu-abu, serta sisa tagihan merah; ikon dan label tetap membedakan jenisnya. Nama siswa dan guru dalam tabel tampil satu baris tanpa subjudul nomor identitas.
