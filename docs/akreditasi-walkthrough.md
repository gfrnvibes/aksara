# Walkthrough: Fase 1 — Panel Akreditasi BAN-PDM (`/akreditasi`)

## Perubahan yang Dilakukan

### 1. Paket & Skema Database
- Menginstal `phpoffice/phpword` (`1.4.0`) untuk mengekspor `02_Draft_Isian_DKA_14_Butir.docx` asli.
- **[2026_11_19_010000_create_akreditasi_tables.php](../database/migrations/2026_11_19_010000_create_akreditasi_tables.php)**: Membuat tabel `akreditasi_dokumens` (termasuk 3 penanda tangan: Kepala Madrasah, Ketua Tim/Komite, dan Pengawas Madrasah/Kemenag) dan `akreditasi_buktis`.
- **[AkreditasiDokumen.php](../app/Models/AkreditasiDokumen.php)**, **[AkreditasiBukti.php](../app/Models/AkreditasiBukti.php)**, **[AkreditasiDokumenPolicy.php](../app/Policies/AkreditasiDokumenPolicy.php)**, **[AkreditasiBuktiPolicy.php](../app/Policies/AkreditasiBuktiPolicy.php)**.

### 2. Panel ke-5 (`/akreditasi`) & Role `tim_akreditasi`
- **[AkreditasiPanelProvider.php](../app/Providers/Filament/AkreditasiPanelProvider.php)** terdaftar di [bootstrap/providers.php](../bootstrap/providers.php), lengkap dengan tombol *Buka Panel Akreditasi* di `AdminPanelProvider`, `GuruPanelProvider`, dan `UjianPanelProvider`.
- **[RoleSeeder.php](../database/seeders/RoleSeeder.php)** & **[User.php](../app/Models/User.php)**: Menambahkan role `tim_akreditasi` yang dapat mengakses `/akreditasi` dan `/guru` (jika GTK aktif) **tanpa** membuka akses `/admin`.

### 3. Analisis 14 Butir, AI Generator & One-Click Bundel `.zip`
- **[PetaBanPdm.php](../app/Services/Akreditasi/PetaBanPdm.php)**: Master 6 Dokumen Wajib Sispena + 14 Butir IASP BAN-PDM, agregator statistik nyata anonim dari Aksara, inisialisasi 20 dokumen standar, dan auto-tagging bukti (`GaleriKegiatan`, `SuratKeluar`, `PerangkatAjar`).
- **[PenyusunDokumenAkreditasiAi.php](../app/Services/Akreditasi/PenyusunDokumenAkreditasiAi.php)**: Menyusun draf HTML berbasis `KlienGemini::hasilkan()` yang diinjeksi JSON statistik nyata Aksara (dengan fallback templat lokal jika API tidak aktif).
- **[PembuatBundelAkreditasi.php](../app/Services/Akreditasi/PembuatBundelAkreditasi.php)**: Menghasilkan arsip `.zip` berisi:
  - `01_Dokumen_Wajib_Sispena/01_KSP_MTs.pdf` s.d. `06_Dokumentasi_Lingkungan_Belajar.pdf`
  - `02_Draft_Isian_DKA_14_Butir.docx`
  - `02_Mapping_Bukti_14_Butir.xlsx`
  - `03_Folder_Bukti_Per_Butir/Butir_01/` s.d. `Butir_14/`
  - `04_Deck_Presentasi_Kepala_Madrasah.pdf` (A4 Landscape 16:9)
  - `05_Lembar_Ceklis_Kesiapan_Lapangan.pdf`
- **[DasborAkreditasi.php](../app/Filament/Akreditasi/Pages/DasborAkreditasi.php)**, **[AkreditasiDokumenResource.php](../app/Filament/Akreditasi/Resources/AkreditasiDokumens/AkreditasiDokumenResource.php)**, **[AkreditasiBuktiResource.php](../app/Filament/Akreditasi/Resources/AkreditasiBuktis/AkreditasiBuktiResource.php)**, dan **[CetakAkreditasiController.php](../app/Http/Controllers/CetakAkreditasiController.php)**.

---

## Hasil Pengujian & Verifikasi
- **[PanelAkreditasiTest.php](../tests/Feature/Akreditasi/PanelAkreditasiTest.php)** & **[HakAksesPanelDanRoleTest.php](../tests/Feature/Auth/HakAksesPanelDanRoleTest.php)**:
  `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Akreditasi/PanelAkreditasiTest.php tests/Feature/Auth/HakAksesPanelDanRoleTest.php`
  → **11 passed** (111 assertions).
- **PHPStan**: **0 errors** pada seluruh modul Akreditasi, `User.php`, dan `RoleSeeder.php`.
