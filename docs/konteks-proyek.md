# Panduan Konteks Proyek — Aksara (Sistem Informasi Madrasah)

Dokumen ini menjadi titik awal bagi agen AI dan pengembang di perangkat mana pun agar langsung memahami arsitektur, konvensi, alur kerja, dan status fitur terbaru di repositori **Aksara**.

---

## 1. Tech Stack & Paket Inti
- **Backend:** PHP `^8.3`, Laravel `^13.17`, PostgreSQL (lokal/produksi), SQLite `:memory:` (pengujian Pest).
- **Admin & UI:** Filament `~5.0`, Livewire `^4.1`, Livewire Flux `^2.13`, Tailwind CSS, Alpine.js.
- **Otorisasi:** `spatie/laravel-permission` + `bezhansalleh/filament-shield` (`^4.3`), Policy per model.
- **Dokumen & Ekspor:** `barryvdh/laravel-dompdf` (PDF), `phpoffice/phpword` (DOCX), `openspout/openspout` (XLSX), `ZipArchive` (bundel `.zip`).
- **AI Engine:** `App\Services\Ai\KlienGemini` (Google Gemini API) + Queue `Bus::batch(...)` untuk proses latar belakang.
- **Kualitas Kode & Testing:** Pest `^5.3`, Larastan/PHPStan (`^3.9`), Laravel Pint (`^1.27`), `spatie/laravel-activitylog`.

---

## 2. Arsitektur 5 Panel Filament & Hak Akses
Aplikasi menggunakan model otorisasi **2 Lapisan** (Identitas `Gtk` aktif + Role Spatie struktural di `database/seeders/RoleSeeder.php`). Detail lengkap ada di [`docs/hak-akses-panel.md`](docs/hak-akses-panel.md).

1. **Panel Admin (`/admin`)** — `AdminPanelProvider`: Master data madrasah (Siswa, GTK, Tahun Ajaran, Kelas, Mapel, Pembagian Mengajar, Surat, Kop Surat, Backup, Activity Log).
2. **Panel Guru (`/guru`)** — `GuruPanelProvider`: Administrasi harian & pembelajaran guru aktif (Presensi Kelas, Jurnal Mengajar, Piket, ATP, Prota, Prosem, Perangkat Ajar, Nilai & Rapor).
3. **Panel Bendahara (`/bendahara`)** — `BendaharaPanelProvider`: Keuangan, tagihan siswa, transaksi kas, penggajian GTK ([`docs/panel-bendahara.md`](docs/panel-bendahara.md)).
4. **Panel Ujian (`/ujian`)** — `UjianPanelProvider`: Bank Soal (termasuk generate AI via `Bus::batch`), Jadwal/Sesi Ujian CBT, serta **Kartu, Denah & Daftar Hadir Ujian** (`KegiatanUjianResource`).
5. **Panel Akreditasi (`/akreditasi`)** — `AkreditasiPanelProvider`: Pusat Akreditasi BAN-PDM (`DasborAkreditasi`, 6 Dokumen Wajib Sispena, 14 Butir DKA, Bank Bukti, AI Generator, dan Ekspor Bundel Visitasi `.zip`).

---

## 3. Perintah Penting (Development & Verifikasi)
- **Jalankan Pengujian (Pest):**
  - Linux/macOS: `php vendor/bin/pest`
  - Windows (wajib aktifkan driver SQLite): `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest`
  - Contoh terarah: `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Akreditasi/PanelAkreditasiTest.php`
- **Analisis Statis (PHPStan / Larastan):**
  - `php -d memory_limit=1G vendor/bin/phpstan analyse`
- **Format Kode (Pint):**
  - `php vendor/bin/pint --test`
- **Queue Worker (wajib untuk Generate Draf AI massal & Soal AI):**
  - `php artisan queue:work`
- **Alur Git:**
  - Branch kerja aktif: `staging` (merge perubahan fitur ke `staging`, jangan langsung mengubah `main` kecuali diminta).

---

## 4. Status Fitur Terkini & Catatan Penting

### A. Panel Ujian — Kartu, Denah & Daftar Hadir ([`docs/kartu-ujian-dan-denah.md`](docs/kartu-ujian-dan-denah.md))
- **Cetak Lengkap (`CetakDenahDanKartuController`):**
  - Kartu Peserta (8 kartu per lembar A4), Denah Ruang, **Daftar Hadir Peserta** (A4 landscape, otomatis melewati hari Minggu), dan opsi **Semuanya** (`ujian.semuanya`).
  - Penanda tangan: **Kepala Madrasah** (diambil dari `tahun_ajarans.kepala_madrasah`) & **Ketua Panitia Ujian** (`kegiatan_ujians.ketua_panitia_id` merujuk ke tabel `gtks`).
- **UI `KelolaRuangDanDenah`:** Semua tombol aksi header sejajar dengan judul pada desktop; tombol cetak dikelompokkan dalam dropdown **Cetak** (header) dan **Cetak Ruang Ini** (kartu ruang).

### B. Panel Akreditasi BAN-PDM ([`docs/akreditasi-ban-pdm.md`](docs/akreditasi-ban-pdm.md), [`docs/akreditasi-walkthrough.md`](docs/akreditasi-walkthrough.md))
- **Loading & Progres Bar AI:**
  - `DasborAkreditasi`: Memantau batch `Bus::batch` di cache (`wire:poll.2s="cekProgresAi"`) dengan loading spinner, progres persentase, dan deteksi worker belum berjalan.
  - `AkreditasiDokumenResource` (List & Edit): Banner progres animasi (`resources/views/filament/akreditasi/partials/ai-loading-banner.blade.php`) via render hook `PanelsRenderHook::CONTENT_START` dan event `akreditasi-ai-mulai`.
- **Perhitungan Kesiapan Keseluruhan (`PetaBanPdm::analisisKesiapan`):**
  - **40% Dokumen Wajib & DKA (20 dokumen):** `final` = 1.0, `draf` = 0.5, `belum` = 0.0.
  - **35% Bank Bukti 14 Butir:** Rata-rata keterisian `butir_01` s.d. `butir_14` (≥ 2 bukti atau ada `berkas_path` = 1.0; 1 bukti tanpa berkas = 0.5).
  - **25% Administrasi Guru:** Kelengkapan `ATP`, `Prota`, `Prosem`, `PerangkatAjar`, dan `JurnalMengajar` terhadap penugasan `PembagianMengajar` pada tahun ajaran aktif.
- **Pekerjaan Lanjutan (Backlog Akreditasi):**
  - Perluasan `PetaBanPdm::autoTagBukti()` untuk menarik data nyata yang sudah ada (`Rapor`, `CatatanPiket`, `ATP`/`Prota`/`Prosem`/`JurnalMengajar`, `Ekstrakurikuler`, `TransaksiKeuangan`, `PelanggaranSiswa`).
  - Modul/tabel baru yang belum ada untuk menghindari narasi kosong/generik: Supervisi Akademik & PKG (Butir 05), Sarpras/KIR (Butir 08), Layanan Inklusif (Butir 11), Ceklis Keselamatan/P3K (Butir 13), dan UKS/Kantin Sehat (Butir 14).

---

## 5. Daftar Dokumentasi di `docs/`
- [`docs/akreditasi-ban-pdm.md`](docs/akreditasi-ban-pdm.md) — Master plan & pemetaan 6 DW + 14 Butir BAN-PDM.
- [`docs/akreditasi-walkthrough.md`](docs/akreditasi-walkthrough.md) — Ringkasan implementasi Fase 1 Panel Akreditasi.
- [`docs/kartu-ujian-dan-denah.md`](docs/kartu-ujian-dan-denah.md) — Arsitektur Kartu Ujian, Denah Ruang & Daftar Hadir.
- [`docs/hak-akses-panel.md`](docs/hak-akses-panel.md) — Analisis & desain otorisasi 5 panel dan role GTK/Spatie.
- [`docs/administrasi-harian.md`](docs/administrasi-harian.md) — Presensi, jurnal mengajar, dan piket.
- [`docs/panel-bendahara.md`](docs/panel-bendahara.md) — Modul keuangan & penggajian.
- [`docs/kop-surat.md`](docs/kop-surat.md) — Pengaturan kop surat dinas/cetak.
- [`docs/backup.md`](docs/backup.md) — Konfigurasi backup & Google Drive.
- [`docs/activity-log.md`](docs/activity-log.md) — Audit log aktivitas pengguna.
