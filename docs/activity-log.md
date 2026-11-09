# Activity log

Activity mencatat perubahan data dan tindakan khusus untuk audit. Saat pembaruan, hanya atribut yang berubah yang disimpan; penyimpanan tanpa perubahan tidak menghasilkan entri baru. Password, atribut tersembunyi model, serta timestamp tidak ikut dicatat.

Daftar activity menampilkan 25 baris secara default, dengan pilihan 25/50/100 tanpa pilihan semua. Detail perubahan tetap tersedia melalui aksi lihat. Timeline per data dibatasi 25 entri dengan tautan menuju daftar lengkap bawaan plugin.

Indeks `activity_log.created_at` membantu pengurutan dan pembersihan. Filter tanggal menggunakan rentang timestamp langsung agar indeks bisa digunakan, termasuk seluruh hari pada tanggal akhir.

Log berumur lebih dari 365 hari dibersihkan setiap Minggu pukul 02.30 WIB, sebanyak maksimal 1.000 entri per operasi hapus. Jadwal memakai `--force` agar dapat berjalan tanpa konfirmasi di production dan mencegah proses pembersihan saling tumpang tindih. Scheduler server harus tetap aktif; lihat [panduan scheduler](backup.md).

Pencatatan tetap langsung ke database. Buffer tidak diaktifkan secara global; evaluasi kebutuhan buffer berdasarkan beban impor dan pengukuran query.

Saat deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
```
