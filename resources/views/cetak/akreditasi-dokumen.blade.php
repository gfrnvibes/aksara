@php
    /** @var \App\Models\AkreditasiDokumen $dokumen */
    /** @var array<string, mixed> $kop */
    $untukPdf = $untukPdf ?? true;
    $kepala = $dokumen->kepalaMadrasah;
    $ketua = $dokumen->ketuaTim;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dokumen->judul }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 15mm 18mm; }
        body { font-family: "Times New Roman", serif; font-size: 11pt; line-height: 1.45; color: #111; margin: 0; @if (! $untukPdf) background: #f3f4f6; @endif }
        .toolbar { position: sticky; top: 0; z-index: 10; background: #111827; color: #fff; padding: 8px 16px; display: flex; align-items: center; justify-content: space-between; font-family: sans-serif; font-size: 13px; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar button, .toolbar a { background: #10b981; color: #fff; border: 0; border-radius: 6px; padding: 6px 14px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .toolbar a.pdf { background: #4f46e5; }
        .halaman { @if (! $untukPdf) width: 210mm; min-height: 297mm; margin: 14px auto; background: #fff; padding: 15mm; box-sizing: border-box; box-shadow: 0 2px 8px rgba(0,0,0,0.08); @endif }
        h1.judul-dok { text-align: center; font-size: 13pt; text-transform: uppercase; margin: 4mm 0 1mm; }
        .sub-dok { text-align: center; font-size: 10pt; color: #374151; margin-bottom: 5mm; }
        .konten h2 { font-size: 12pt; margin: 4mm 0 2mm; }
        .konten h3 { font-size: 11pt; margin: 3mm 0 1.5mm; }
        .konten table { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 10pt; }
        .konten th, .konten td { border: 1px solid #333; padding: 1.5mm 2.5mm; vertical-align: top; }
        .konten th { background: #f3f4f6; }
        .pengesahan { width: 100%; border-collapse: collapse; margin-top: 12mm; page-break-inside: avoid; }
        .pengesahan td { width: 33.33%; vertical-align: top; text-align: center; padding: 0 3mm; font-size: 10pt; }
        .ttd-nama { margin-top: 18mm; font-weight: bold; text-decoration: underline; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .halaman { width: 100%; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    @if (! $untukPdf)
        <div class="toolbar">
            <div><strong>{{ strtoupper($dokumen->kode) }}</strong> — {{ $dokumen->judul }}</div>
            <div class="toolbar-actions">
                <a class="pdf" href="{{ route('akreditasi.dokumen.cetak', ['dokumen' => $dokumen, 'format' => 'pdf']) }}">Unduh PDF</a>
                <button type="button" onclick="window.print()">Cetak / Print (Ctrl+P)</button>
            </div>
        </div>
    @endif

    <div class="halaman">
        @include('cetak.partials.kop', ['kop' => $kop])

    <h1 class="judul-dok">{{ $dokumen->judul }}</h1>
    <div class="sub-dok">Tahun Ajaran {{ $dokumen->tahunAjaran?->nama }} · Kode: {{ strtoupper($dokumen->kode) }}</div>

    <div class="konten">
        @if (filled($dokumen->isi_html))
            {!! str($dokumen->isi_html)->sanitizeHtml() !!}
        @else
            <p><em>Dokumen ini belum diisi atau di-generate.</em></p>
        @endif
    </div>

    <table class="pengesahan">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div>{{ $kepala?->jabatan ?? 'Kepala Madrasah' }}</div>
                <div class="ttd-nama">{{ $kepala?->nama ?? '____________________' }}</div>
                @if (filled($kepala?->nip))
                    <div>NIP. {{ $kepala->nip }}</div>
                @endif
            </td>
            <td>
                <div>Mengetahui / Mengesahkan,</div>
                <div>{{ $dokumen->jabatan_pengawas ?: 'Pengawas Madrasah / Kemenag' }}</div>
                <div class="ttd-nama">{{ $dokumen->nama_pengawas ?: '____________________' }}</div>
                @if (filled($dokumen->nip_pengawas))
                    <div>NIP. {{ $dokumen->nip_pengawas }}</div>
                @endif
            </td>
            <td>
                <div>Disusun oleh,</div>
                <div>{{ $ketua?->jabatan ?? 'Ketua Tim Akreditasi / Komite' }}</div>
                <div class="ttd-nama">{{ $ketua?->nama ?? '____________________' }}</div>
                @if (filled($ketua?->nip))
                    <div>NIP. {{ $ketua->nip }}</div>
                @endif
            </td>
        </tr>
    </table>
    </div>
</body>
</html>
