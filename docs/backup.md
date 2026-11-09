# Backup data sekolah

Halaman **Pengaturan → Backup Data** menampilkan arsip dan riwayat setiap tujuan penyimpanan. Statusnya adalah menunggu, diproses, berhasil, atau gagal. Kolom Kesalahan menampilkan penyebab kegagalan dengan kredensial disembunyikan. Riwayat tetap ada setelah arsip dibersihkan; tombol unduh/hapus hanya tersedia untuk arsip yang masih ditemukan.

## Konfigurasi

```dotenv
BACKUP_DISKS=local,r2
BACKUP_QUEUE_CONNECTION=deferred
```

Aktifkan `google` hanya setelah kredensial Google Drive diisi. R2 memerlukan `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, dan `R2_ENDPOINT`. Arsip lokal berada di `storage/app/private/<BACKUP_NAME>/`. Proses PHP harus dapat menulis ke direktori ini dan `storage/app/backup-temp`.

Backup manual menggunakan antrean `deferred` bawaan Laravel: proses berjalan setelah respons HTTP tanpa memerlukan worker. Untuk server dengan worker permanen, gunakan `BACKUP_QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=1900`, dan worker `php artisan queue:work --timeout=1800 --tries=1`. Batas waktu PHP/web server harus cukup untuk menyelesaikan backup jika menggunakan `deferred`.

Database PostgreSQL memerlukan `pg_dump`, MySQL/MariaDB memerlukan `mysqldump`, dan SQLite memerlukan `sqlite3`. Isi `DB_DUMP_BINARY_PATH` dengan direktori executable PostgreSQL jika tidak tersedia pada PATH proses PHP.

## Penerapan

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Jalankan scheduler setiap menit melalui cron server (sesuaikan path):

```cron
* * * * * cd /path/aksara && php artisan schedule:run >> /dev/null 2>&1
```

Pada Termux atau pengembangan lokal, `php artisan schedule:work` dapat dipakai sebagai proses yang terus berjalan. Scheduler mencadangkan data pukul **01.30 Asia/Jakarta** melalui `aksara:backup`, membersihkan arsip lama pukul 01.00, dan memonitor kesehatan pukul 07.00. Aplikasi harus tetap berjalan pada jadwal tersebut.

Uji manual melalui tombol **Buat Backup Sekarang** atau CLI:

```bash
php artisan aksara:backup --only-db
php artisan schedule:list
```

Setiap tujuan diproses terpisah sehingga kegagalan adapter/cloud tidak menghalangi salinan lokal. Perintah keluar dengan kode gagal bila setidaknya satu tujuan gagal; rincian per tujuan ada di tabel. `backup:run` bawaan paket tetap tersedia, tetapi gunakan `aksara:backup` untuk mencatat riwayat aplikasi.

## Restore

Aksi **Restore** tersedia pada baris arsip yang masih ditemukan dan berstatus berhasil, khusus **super admin**. Pilih database saja atau aktifkan pemulihan foto/file pada backup lengkap. Ketik `RESTORE` dan masukkan password akun saat ini.

Restore mendukung PostgreSQL dengan schema `public` dan SQLite berbasis file pada satu server, dengan `APP_MAINTENANCE_DRIVER=file` (default), agar status maintenance tidak ikut tertimpa saat database diganti. PostgreSQL memerlukan `psql` pada PATH PHP atau di direktori `DB_DUMP_BINARY_PATH`, selain `pg_dump` untuk backup pengaman. Gunakan arsip ZIP yang dibuat aplikasi dengan satu dump SQL dari driver yang sama. Arsip terenkripsi memakai `BACKUP_ARCHIVE_PASSWORD`; password harus sama dengan saat arsip dibuat. Batas arsip/isi ekstraksi adalah 2 GB, dump SQL 128 MB, dan 100.000 entri.

Proses dimulai setelah respons HTTP, tanpa disimpan dalam antrean database yang akan dipulihkan. Aplikasi masuk maintenance sementara, membuat backup pengaman lengkap bernama `db-and-files-pre-restore-...zip` di disk lokal, lalu memulihkan data. Jika backup pengaman atau validasi gagal, data tidak diganti. PostgreSQL menggunakan satu transaksi untuk mengganti schema; SQLite divalidasi di file sementara sebelum dipasang. Jika langkah setelah impor gagal, sistem mencoba mengembalikan database dan file sebelumnya dari backup pengaman.

Pemulihan foto mengganti **storage/app/public** sesuai isi arsip. Dokumen privat yang disimpan terpisah di R2 tidak termasuk backup/restore ini. Restore tidak menerima unggahan SQL/ZIP sembarang; sumbernya hanya arsip pada lokasi backup yang terdaftar.

Struktur database disesuaikan melalui migrasi setelah impor. Antrean `jobs` dan sesi login lama dari snapshot dibersihkan agar pekerjaan tidak dijalankan ulang dan sesi lama tidak aktif kembali. Setelah selesai, muat ulang halaman dan login menggunakan akun/password yang ada dalam backup.

Riwayat restore beserta pesan kesalahan disimpan di `storage/app/private/restore-history`, terpisah dari database, dan tampil pada tabel **Riwayat Restore** di bawah daftar backup. Proses normal akan keluar dari maintenance otomatis. Jika pemulihan otomatis kondisi sebelumnya gagal, maintenance dan direktori `storage/app/restore-temp/<ID>` dipertahankan untuk pemulihan manual. Jangan menghapus file pemulihan tersebut; periksa pesan kesalahan dan arsip pengaman sebelum menjalankan `php artisan up`. Hal yang sama berlaku jika proses PHP dihentikan mendadak/timeout. Batas waktu web server harus cukup untuk menyelesaikan proses.
