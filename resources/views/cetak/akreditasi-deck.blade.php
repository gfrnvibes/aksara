@php
    /** @var \App\Models\TahunAjaran $tahun */
    /** @var array<string, mixed> $statistik */
    /** @var array<string, int> $kesiapan */
    /** @var array<string, mixed> $kop */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Presentasi Akreditasi - {{ $statistik['madrasah'] ?? 'Madrasah' }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: "Arial", sans-serif; color: #111827; margin: 0; }
        .slide { page-break-after: always; border: 2px solid #059669; border-radius: 8px; padding: 14mm; height: 158mm; box-sizing: border-box; }
        .slide:last-child { page-break-after: auto; }
        .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 2mm 4mm; border-radius: 4px; font-size: 9pt; font-weight: bold; }
        h1 { font-size: 22pt; color: #065f46; margin: 6mm 0 2mm; }
        h2 { font-size: 16pt; color: #065f46; border-bottom: 2px solid #10b981; padding-bottom: 2mm; margin: 0 0 6mm; }
        .grid-4 { width: 100%; border-collapse: separate; border-spacing: 4mm; }
        .kpi { border: 1px solid #d1d5db; border-radius: 6px; padding: 5mm; text-align: center; background: #f9fafb; width: 25%; }
        .kpi .angka { font-size: 22pt; font-weight: bold; color: #059669; }
        .kpi .label { font-size: 10pt; color: #4b5563; margin-top: 2mm; }
        ul { font-size: 11.5pt; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="slide">
        <span class="badge">PAPARAN KEPALA MADRASAH · VISITASI BAN-PDM</span>
        <h1>{{ $statistik['madrasah'] ?? config('sekolah.nama') }}</h1>
        <p style="font-size: 13pt; color: #374151;">{{ $statistik['wilayah'] ?? '' }} · NPSN: {{ $statistik['npsn'] ?: '-' }}</p>
        <p style="font-size: 12pt; margin-top: 12mm;"><strong>Tahun Ajaran:</strong> {{ $tahun->nama }}</p>
        <p style="font-size: 12pt;"><strong>Kesiapan Dokumen & DKA:</strong> {{ $kesiapan['persen_keseluruhan'] }}% ({{ $kesiapan['dw_selesai'] }}/6 DW · {{ $kesiapan['dka_selesai'] }}/14 Butir DKA)</p>
    </div>

    <div class="slide">
        <h2>Profil Data Operasional Madrasah (Real-Time)</h2>
        <table class="grid-4">
            <tr>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_siswa_aktif'] }}</div><div class="label">Siswa Aktif</div></td>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_rombel_aktif'] }}</div><div class="label">Rombongan Belajar</div></td>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_gtk_aktif'] }}</div><div class="label">GTK Aktif</div></td>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_perangkat_ajar'] }}</div><div class="label">Perangkat Ajar</div></td>
            </tr>
            <tr>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_jurnal_mengajar'] }}</div><div class="label">Jurnal Mengajar</div></td>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_bank_soal'] }}</div><div class="label">Bank Soal</div></td>
                <td class="kpi"><div class="angka">{{ $statistik['jumlah_prestasi_siswa'] }}</div><div class="label">Prestasi Siswa</div></td>
                <td class="kpi"><div class="angka">{{ $kesiapan['bukti_total'] }}</div><div class="label">Bukti Ter-tag</div></td>
            </tr>
        </table>
    </div>

    <div class="slide">
        <h2>Ringkasan Pemenuhan 3 Komponen Utama BAN-PDM</h2>
        <ul>
            <li><strong>Komponen 1 (Kinerja Pendidik):</strong> Perangkat ajar Kurikulum Merdeka/KMA ({{ $statistik['jumlah_perangkat_ajar'] }} dokumen), jurnal mengajar ({{ $statistik['jumlah_jurnal_mengajar'] }} entri), asesmen formatif &amp; sumatif berbasis bank soal ({{ $statistik['jumlah_bank_soal'] }} soal).</li>
            <li><strong>Komponen 2 (Kepemimpinan Kepala Madrasah):</strong> KSP MTs, RKT, RKAS/transparansi anggaran (Pemasukan Rp {{ number_format((int) $statistik['total_pemasukan_rp'], 0, ',', '.') }}), agenda akademik ({{ $statistik['jumlah_agenda_kalender'] }} kegiatan).</li>
            <li><strong>Komponen 3 (Iklim Lingkungan Belajar):</strong> Dokumentasi kegiatan ({{ $statistik['jumlah_galeri_kegiatan'] }} galeri), korespondensi kemitraan ({{ $statistik['jumlah_surat_keluar'] }} surat keluar), dan pembinaan karakter positif.</li>
        </ul>
    </div>
</body>
</html>
