@php
    /** @var \App\Models\KegiatanUjian $kegiatan */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RuangUjian> $ruangList */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\PenempatanUjian> $peserta */
    /** @var list<array{hari: string, tanggal: string}> $daftarTanggal */
    /** @var array<string, mixed> $kop */
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Paket Cetak Lengkap - {{ $kegiatan->nama }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f3f4f6; }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #111827;
            color: #fff;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .toolbar-links { display: flex; gap: 8px; }
        .toolbar a, .toolbar button {
            background: #10b981;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .toolbar a { background: #374151; }
        .bagian-frame {
            width: 210mm;
            margin: 16px auto;
            background: #fff;
            padding: 8mm;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .bagian-judul {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #065f46;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        @media print {
            .toolbar, .bagian-judul { display: none; }
            body { background: #fff; }
            .bagian-frame { width: 100%; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>Paket Cetak Lengkap — {{ $kegiatan->nama }}</strong> (Kartu · Denah · Daftar Hadir)</div>
        <div class="toolbar-links">
            <a href="{{ route('ujian.kartu', $kegiatan) }}" target="_blank">Kartu Saja</a>
            <a href="{{ route('ujian.denah', $kegiatan) }}" target="_blank">Denah Saja</a>
            <a href="{{ route('ujian.daftar-hadir', $kegiatan) }}" target="_blank">Daftar Hadir Saja</a>
            <button type="button" onclick="window.print()">Cetak Semua (Ctrl+P)</button>
        </div>
    </div>

    <div class="bagian-frame">
        <div class="bagian-judul">1. Daftar Hadir Peserta ({{ $ruangList->count() }} Ruang)</div>
        <iframe src="{{ route('ujian.daftar-hadir', ['kegiatan' => $kegiatan, 'ruang_id' => request('ruang_id')]) }}" style="width:100%; height:420px; border:1px solid #e5e7eb;" onload="this.contentDocument.querySelector('.toolbar')?.remove()"></iframe>
    </div>

    <div class="bagian-frame">
        <div class="bagian-judul">2. Denah Tempat Duduk ({{ $ruangList->count() }} Ruang)</div>
        <iframe src="{{ route('ujian.denah', ['kegiatan' => $kegiatan, 'ruang_id' => request('ruang_id')]) }}" style="width:100%; height:500px; border:1px solid #e5e7eb;" onload="this.contentDocument.querySelector('.toolbar')?.remove()"></iframe>
    </div>

    <div class="bagian-frame">
        <div class="bagian-judul">3. Kartu Peserta Ujian ({{ $peserta->count() }} Siswa)</div>
        <iframe src="{{ route('ujian.kartu', ['kegiatan' => $kegiatan, 'ruang_id' => request('ruang_id')]) }}" style="width:100%; height:600px; border:1px solid #e5e7eb;" onload="this.contentDocument.querySelector('.toolbar')?.remove()"></iframe>
    </div>
</body>
</html>
